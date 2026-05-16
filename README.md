# Uptime Monitor

A Laravel API that monitors a set of URLs, records check history, and sends email notifications when sites transition between up and down states.

---

## Stack

- **Laravel 13.x** on **PHP 8.4+**
- **MySQL** for primary storage
- **Database queue driver** for background jobs
- **SMTP mail driver** (configurable; switch to `log` for fast local testing — see below)

---

## Setup

### 1. Clone and install

```bash
git clone https://github.com/Waris10/UptimeMonitor.git uptime-monitor
cd uptime-monitor
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure the database

Edit `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uptime_monitor
DB_USERNAME=root
DB_PASSWORD=
```

Create the database, then:

```bash
php artisan migrate
```

### 3. Configure the queue

The queue uses the `database` driver and shares the same MySQL database. The migration above already created the `jobs` and `failed_jobs` tables.

Confirm in `.env`:

```
QUEUE_CONNECTION=database
```

### 4. Configure mail

For the assessment, SMTP via Mailtrap (or any provider) is recommended:

```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=monitor@uptime.test
MAIL_FROM_NAME="Uptime Monitor"

MONITOR_NOTIFICATION_EMAIL=ops@example.test
```

**For faster local review**, switch the mailer to `log` — outgoing mail is written to `storage/logs/laravel.log` instead of being sent:

```
MAIL_MAILER=log
```

If `MONITOR_NOTIFICATION_EMAIL` is unset, the dispatcher silently no-ops on transitions. This is deliberate so the system runs without mail configuration during early review.

### 5. Run it

You need three processes:

```bash
# The HTTP server
php artisan serve

# The scheduler (dispatches due monitors every minute)
php artisan schedule:work

# The queue worker (executes check jobs and sends mail)
php artisan queue:work
```

In production these would be a cron entry for the scheduler, a supervisord-managed worker for the queue, and your usual PHP-FPM/nginx setup for HTTP.

---

## API contract

All endpoints are prefixed with `/api`.

### `POST /api/monitors` — register a URL

**Request body:**

```json
{
    "url": "https://example.com",
    "check_interval": 5,
    "threshold": 3
}
```

| Field            | Type   | Required | Description                                                 |
| ---------------- | ------ | -------- | ----------------------------------------------------------- |
| `url`            | string | yes      | Valid HTTP/HTTPS URL, unique                                |
| `check_interval` | int    | no       | Minutes between checks (default 5, min 1, max 60)           |
| `threshold`      | int    | no       | Consecutive failures before marking down (default 3, min 1) |

**Success — 201 Created:**

```json
{
    "data": {
        "id": 1,
        "url": "https://example.com",
        "check_interval": 5,
        "threshold": 3,
        "status": "pending",
        "last_checked_at": null,
        "uptime_percentage": null,
        "created_at": "2026-05-13T10:00:00.000000Z"
    }
}
```

**Validation error — 422 Unprocessable Entity:**

```json
{
    "message": "The url field is required.",
    "errors": {
        "url": ["The url field is required."]
    }
}
```

Duplicate URLs are rejected with a 422.

### `GET /api/monitors` — list all monitors

**Success — 200 OK:**

```json
{
    "data": [
        {
            "id": 1,
            "url": "https://example.com",
            "check_interval": 5,
            "threshold": 3,
            "status": "up",
            "last_checked_at": "2026-05-13T10:05:00.000000Z",
            "uptime_percentage": 99.5,
            "created_at": "2026-05-13T10:00:00.000000Z"
        }
    ]
}
```

`status` is one of `pending`, `up`, `down`.

### `GET /api/monitors/{id}/history` — check history

**Query parameters:**

| Param      | Type | Required | Description                            |
| ---------- | ---- | -------- | -------------------------------------- |
| `page`     | int  | no       | Page number (default 1)                |
| `per_page` | int  | no       | Results per page (default 15, max 100) |

**Success — 200 OK:**

```json
{
    "data": [
        {
            "id": 1,
            "monitor_id": 1,
            "status_code": 200,
            "response_time_ms": 245,
            "is_up": true,
            "checked_at": "2026-05-13T10:05:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 50
    }
}
```

Results are ordered by `checked_at` descending. `is_up` is `true` for 2xx and 3xx responses. On connection failure or timeout, `status_code` is `0` and `response_time_ms` is `null`.

**Not found — 404:**

```json
{ "message": "Monitor not found." }
```

---

## Architecture

```
HTTP layer            POST /api/monitors        ─┐
                      GET  /api/monitors         │  → MonitorController
                      GET  /api/monitors/{id}/history ─┘

Background flow       Scheduler (every minute)
                        └─ monitors:dispatch-due (DispatchDueMonitorsCommand)
                              └─ for each due monitor: dispatch CheckMonitorJob
                                    └─ UptimeChecker::probe        → records Check row
                                    └─ StatusEvaluator::evaluate   → pure function
                                    └─ persist monitor state       → transaction
                                    └─ NotificationDispatcher      → queues mail on transitions
```

### Components

- **`MonitorController`** — thin; validates via `StoreMonitorRequest`, returns `MonitorResource` / `CheckResource`. No business logic.
- **`Monitor::scopeWithUptime`** — adds uptime aggregates (last 24h) as subquery columns, eliminating N+1 on the list endpoint. The `uptime_percentage` accessor reads from these without a second query.
- **`Monitor::scopeDue`** — sargable SQL form so the `last_checked_at` index is usable at scale: `last_checked_at <= DATE_SUB(NOW(), INTERVAL check_interval MINUTE)`.
- **`UptimeChecker`** — performs the HTTP probe, records the `Check` row. Records timeouts/connection failures as `status_code = 0, response_time_ms = null` per spec.
- **`StatusEvaluator`** — **pure function**. Given a monitor's pre-check state and a new check, returns a `StatusTransition` enum (`NoChange`, `WentDown`, `CameUp`). No mutation, no I/O. This is the system's state machine.
- **`CheckMonitorJob`** — orchestrates: probe → evaluate → mutate (in a transaction) → dispatch notification. `tries = 1`: a failed probe is data, not a transient error to retry.
- **`NotificationDispatcher`** — exhaustive `match` on `StatusTransition` → queues the right mailable, or no-ops on `NoChange`.

### State machine

| Current state | New check                | Transition                                                                 |
| ------------- | ------------------------ | -------------------------------------------------------------------------- |
| Pending       | up                       | NoChange (status flips to Up, no mail — first confirmation isn't recovery) |
| Pending       | down (under threshold)   | NoChange                                                                   |
| Pending       | down (threshold reached) | WentDown                                                                   |
| Up            | up                       | NoChange (failure streak resets)                                           |
| Up            | down (under threshold)   | NoChange                                                                   |
| Up            | down (threshold reached) | WentDown                                                                   |
| Down          | up                       | CameUp                                                                     |
| Down          | down                     | NoChange                                                                   |

Notifications fire **only on transitions**, never on steady state.

---

## Design decisions

A handful of choices that warrant explanation.

### Response format

This project's house standard uses an `{ error, message, data }` envelope (see `app/helpers.php` and `app/Http/Controllers/Api/BaseApiController.php`) for frontend uniformity across all endpoints. **For this assessment**, the four required endpoints return responses matching the contract's exact shapes (no envelope) per the explicit _"This is a minimum requirement during the review of your work"_ instruction. The helpers and base controller remain in place as the project's pattern for any future endpoints outside the assessment scope.

### Configuration over magic numbers

Defaults, limits, and tunable thresholds live in `config/monitoring.php`. Limits required by the spec (`check_interval` 1–60, `threshold` ≥ 1, `per_page` ≤ 100) are hardcoded — they're contractual. Defaults and the check timeout are env-driven so they can be tuned per-environment without code changes.

### Uptime computed via subquery scope, not accessor query

The naive approach — an accessor that queries on read — produces an N+1 on the list endpoint. Instead, `Monitor::withUptime()` attaches two correlated subquery columns (`uptime_total_window`, `uptime_ups_window`) covering the configured rolling window (default 24h). The accessor reads from the loaded attributes. One query for the index, regardless of monitor count.

At very large scale (tens of thousands of monitors) the subqueries become expensive and the right move is to denormalize the aggregates onto the monitor row, updated as checks are written. For this assessment, the subquery approach is the right trade — clean, testable, and the optimization path is clearly documented for later.

### `consecutive_failures` stored on the monitor

Rather than computing it from check history on every probe, the counter lives on the monitor row and is updated in the check job's transaction. Two reasons: cheaper (no aggregation query per probe), and the threshold logic becomes a simple integer comparison.

### Notifications fire on transitions only

The state machine separates `consecutive_failures` (the raw counter that advances on every failure) from `StatusTransition` (the derived event). Mail goes out exactly once per transition — once on `Up → Down` after threshold, once on `Down → Up` on recovery. Steady-state failure or steady-state success sends nothing. This is the explicit reading of the spec's _"sends a mail notification"_ language and matches operational expectations for an uptime system.

### Pending → Up is not a recovery

A monitor transitioning from `Pending` (never confirmed) to `Up` on its first successful probe does **not** send a "back up" notification. `CameUp` is reserved for `Down → Up`. Sending a recovery alert for a monitor that was never reported down would be noise.

### Probe records `checked_at` at probe-start

`checked_at` reflects when the HTTP probe was initiated, not when the database row was inserted. If the DB write is briefly queued, the timestamp still reflects when the URL was actually reached. This is the timestamp consumers should treat as authoritative.

### `tries = 1` on the check job

A failed probe is a real signal — that's what the threshold mechanism uses. Retrying the job on probe failure would record duplicate checks and corrupt the threshold count. The job runs once; if it crashes hard (DB unreachable, etc.), it's logged via the `failed()` handler and the next scheduler tick will dispatch a fresh one.

### Notification recipient

The spec doesn't define a recipient and the data model has no user concept, so the recipient is a single configurable address (`MONITOR_NOTIFICATION_EMAIL`). In a real system this would live on a user or on the monitor itself.

### TLS verification disabled on probes

`Http::withoutVerifying()` is used so the system can probe staging URLs with self-signed certs. For a production deployment this would be removed (and probes against bad-cert sites would correctly be reported as down). Flagged here rather than silently fudged.

### Effective check interval

Because the scheduler dispatches every minute and the queue worker executes shortly after, the effective interval between successive checks for a given monitor is the configured `check_interval` plus the queue's drain latency (typically a few seconds). The spec describes `check_interval` as minutes _between_ checks, which this honors as a floor. Trading slight under-probing for non-blocking dispatch is the correct shape for an uptime system — we don't want a slow probe to delay the rest of the schedule.

---

## What I'd add with more time

- **Tests.** A `StatusEvaluatorTest` covering the eight rows of the state-machine table (pure unit test, no DB), and feature tests asserting each endpoint's response shape matches the contract.
- **Eliminate the N+1 alternative path at scale.** Denormalize uptime aggregates onto the monitor row, updated transactionally in `CheckMonitorJob`. Document the staleness window (none, since it's updated atomically with each check).
- **Rate limiting on `POST /api/monitors`.** Currently unlimited; in production, throttle.
- **Per-monitor recipients.** A `notification_email` column plus per-monitor opt-out.
- **Webhook notifications** alongside email. A `notification_channels` JSON column with `["email", "webhook"]` and per-channel config.
- **A small status-history table** distinct from `checks` — a record per _transition_ rather than per _probe_. Cheaper to query for "how long was this monitor down?" reports.
- **Backoff on chronically-down monitors.** A site that's been down for 6 hours doesn't need probing every minute. Exponential backoff on long-running outages, returning to normal cadence on recovery.

---

## Sample cURL

```bash
# Create a monitor
curl -X POST http://localhost:8000/api/monitors \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"url": "https://example.com", "check_interval": 1, "threshold": 2}'

# List all monitors
curl http://localhost:8000/api/monitors -H "Accept: application/json"

# Fetch history
curl "http://localhost:8000/api/monitors/1/history?per_page=10" \
  -H "Accept: application/json"
```

---

## Repository conventions

- All business logic lives in `app/Services/`; controllers are thin.
- Magic numbers are not allowed — anything tunable goes in `config/monitoring.php`.
