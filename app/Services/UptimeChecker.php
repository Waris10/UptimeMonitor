<?php

namespace App\Services;

use App\Models\Check;
use App\Models\Monitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UptimeChecker
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    /**
     * Probe the monitor's URL and persist a Check row.
     *
     * Records:
     *   - status_code: the HTTP status, or 0 on connection failure / timeout
     *   - response_time_ms: elapsed ms, or null on connection failure / timeout
     *   - is_up: true for 2xx/3xx, false otherwise
     *   - checked_at: the moment the probe started (authoritative, not insert time)
     */
    public function probe(Monitor $monitor): Check
    {
        $timeout = (int) config('monitoring.check.timeout_seconds');
        $startedAt = now();
        $startedNs = hrtime(true);

        $statusCode = 0;
        $responseTimeMs = null;
        $isUp = false;

        try {
            $response = Http::timeout($timeout)
                ->withoutVerifying() // permissive for the assessment; see note in README
                ->get($monitor->url);

            $responseTimeMs = (int) ((hrtime(true) - $startedNs) / 1_000_000);
            $statusCode = $response->status();
            $isUp = $statusCode >= Response::HTTP_OK && $statusCode < Response::HTTP_BAD_REQUEST;
        } catch (ConnectionException $e) {
            // Timeout, DNS failure, connection refused — leave defaults (0, null, false).
        } catch (RequestException $e) {
            // Got a response but Laravel raised — capture what we can.
            $responseTimeMs = (int) ((hrtime(true) - $startedNs) / 1_000_000);
            $statusCode = $e->response?->status() ?? 0;
            $isUp = false;
        } catch (Throwable $e) {
            // Defensive: anything else, fall through as a failed check.
        }

        return Check::create([
            'monitor_id' => $monitor->id,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_up' => $isUp,
            'checked_at' => $startedAt,
        ]);
    }
}
