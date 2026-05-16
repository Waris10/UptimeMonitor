<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitor defaults & limits
    |--------------------------------------------------------------------------
    |
    | Default and boundary values applied when a monitor is registered.
    | The defaults are applied in StoreMonitorRequest::prepareForValidation()
    | and the limits in its rules().
    |
    */

    'defaults' => [
        'check_interval' => env('MONITOR_DEFAULT_CHECK_INTERVAL', 5),
        'threshold' => env('MONITOR_DEFAULT_THRESHOLD', 3),
    ],

    'limits' => [
        'check_interval_min' => 1,
        'check_interval_max' => 60,
        'threshold_min' => 1,
        'url_max_length' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'history_per_page' => 15,
        'history_max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Check engine
    |--------------------------------------------------------------------------
    |
    | HTTP timeout applied when probing a URL, in seconds. The uptime window
    | controls the rolling period used by Monitor::withUptime() and the
    | uptime_percentage accessor.
    |
    */

    'check' => [
        'timeout_seconds' => env('MONITOR_CHECK_TIMEOUT', 10),
        'uptime_window_hours' => env('MONITOR_UPTIME_WINDOW_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The spec doesn't define a notification recipient and the data model
    | has no user. The recipient is therefore a single configurable address.
    |
    */

    'notifications' => [
        'recipient' => env('MONITOR_NOTIFICATION_EMAIL'),
    ],

];
