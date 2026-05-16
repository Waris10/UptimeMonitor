<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitors:dispatch-due')]
#[Description('Queue check jobs for monitors whose next check is due.')]
class DispatchDueMonitorsCommand extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        Monitor::query()
            ->due()
            ->select(['id'])
            ->chunkById(500, function ($monitors) use (&$dispatched) {
                foreach ($monitors as $monitor) {
                    CheckMonitorJob::dispatch($monitor->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} check job(s).");

        return self::SUCCESS;
    }
}
