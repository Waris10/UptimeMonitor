<?php

namespace App\Services;

use App\Enums\MonitorStatusEnum;
use App\Enums\StatusTransitionEnum;
use App\Models\Check;
use App\Models\Monitor;

class StatusEvaluator
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    /**
     * Determine the transition implied by a new check, given the monitor's
     * CURRENT state (i.e. as it was BEFORE this check was recorded).
     *
     * Returns the transition only. Mutating the monitor is the caller's job.
     */
    public function evaluate(Monitor $monitor, Check $newCheck): StatusTransitionEnum
    {
        if ($newCheck->is_up) {
            // A successful check resets the failure streak. We only fire
            // CameUp if the monitor was actually marked down. Recovering
            // from Pending → Up is a routine first-success, not a "back up".
            return $monitor->status === MonitorStatusEnum::DOWN
                ? StatusTransitionEnum::CAME_UP
                : StatusTransitionEnum::NO_CHANGE;
        }

        // Failure path. consecutive_failures here is the value BEFORE this
        // failure is counted. After adding this one, it becomes +1.
        $failuresAfter = $monitor->consecutive_failures + 1;

        if ($failuresAfter >= $monitor->threshold && $monitor->status !== MonitorStatusEnum::DOWN) {
            return StatusTransitionEnum::WENT_DOWN;
        }

        return StatusTransitionEnum::NO_CHANGE;
    }
}
