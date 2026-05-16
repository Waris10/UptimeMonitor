<?php

namespace App\Services;

use App\Enums\StatusTransitionEnum;
use App\Mail\MonitorBackUpMail;
use App\Mail\MonitorWentDownMail;
use App\Models\Monitor;
use Illuminate\Support\Facades\Mail;

class NotificationDispatcher
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public function dispatch(Monitor $monitor, StatusTransitionEnum $transition): void
    {
        $recipient = config('monitoring.notifications.recipient');

        if (! $recipient) {
            return;
        }

        match ($transition) {
            StatusTransitionEnum::WENT_DOWN => Mail::to($recipient)->queue(new MonitorWentDownMail($monitor)),
            StatusTransitionEnum::CAME_UP => Mail::to($recipient)->queue(new MonitorBackUpMail($monitor)),
            StatusTransitionEnum::NO_CHANGE => null,
        };
    }
}
