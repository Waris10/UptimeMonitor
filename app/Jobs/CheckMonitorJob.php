<?php

namespace App\Jobs;

use App\Enums\MonitorStatusEnum;
use App\Enums\StatusTransitionEnum;
use App\Models\Monitor;
use App\Services\NotificationDispatcher;
use App\Services\StatusEvaluator;
use App\Services\UptimeChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Do not retry — a failed probe is a real data point, not a transient
     * job error. Threshold semantics handle "is it really down".
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $monitorId) {}

    /**
     * Execute the job.
     */
    public function handle(UptimeChecker $checker, StatusEvaluator $evaluator, NotificationDispatcher $dispatcher): void
    {
        $monitor = Monitor::find($this->monitorId); // I didn't pass the model from the caller to always get a clean and fresh state anytime the concerned monitor is called

        if (! $monitor) {
            return;
        }

        $check = $checker->probe($monitor);

        // Evaluate BEFORE mutating: evaluator reads the pre-check state.
        $transition = $evaluator->evaluate($monitor, $check);

        DB::transaction(function () use ($monitor, $check, $transition) {
            $monitor->last_checked_at = $check->checked_at;

            if ($check->is_up) {
                $monitor->consecutive_failures = 0;
                $monitor->status = MonitorStatusEnum::UP;
            } else {
                $monitor->consecutive_failures += 1;
                if ($transition === StatusTransitionEnum::WENT_DOWN) {
                    $monitor->status = MonitorStatusEnum::DOWN;
                }
                // else: stay in whatever status — Pending stays Pending until threshold,
                // Down stays Down, Up stays Up until threshold flips it.
            }

            $monitor->save();
        });

        $dispatcher->dispatch($monitor, $transition);
    }

    public function failed(Throwable $e): void
    {
        Log::error('CheckMonitorJob failed', [
            'monitor_id' => $this->monitorId,
            'exception' => $e->getMessage(),
        ]);
    }
}
