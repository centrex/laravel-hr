<?php

declare(strict_types = 1);

return [

    'drivers' => [
        'database' => [
            'connection' => env('HR_DB_CONNECTION'),
        ],
    ],

    'table_prefix' => env('HR_TABLE_PREFIX', 'hr_'),

    'web_enabled'    => env('HR_WEB_ENABLED', true),
    'web_middleware' => ['web', 'auth', 'can:hr.employees.view'],
    'web_prefix'     => 'hr',

    // Self-service pages (My Attendance, My Leave, Leave Approvals) are open to any
    // authenticated user — access to one's own records isn't gated behind admin roles,
    // unlike the master-data routes above.
    'self_service_middleware' => ['web', 'auth'],

    'api_enabled'    => env('HR_API_ENABLED', true),
    'api_middleware' => ['api', 'auth:sanctum', 'can:hr.employees.view'],
    'api_prefix'     => 'api/hr',

    'currency' => env('HR_CURRENCY', env('PAYROLL_CURRENCY', 'BDT')),

    // Days of the week (Carbon dayOfWeek: Sun=0 .. Sat=6) treated as non-working, and
    // therefore never counted as "absent" even without attendance or leave. Defaults to
    // Friday/Saturday for the Bangladesh work week.
    'weekend_days' => [5, 6],

    // Mirrors HR employees into laravel-payroll's own employee record when both packages
    // are installed. Off by default so HR works fully standalone until you opt in.
    'payroll_sync' => [
        'enabled' => env('HR_PAYROLL_SYNC_ENABLED', false),
    ],

    // Pulls attendance punches from ZKTeco biometric devices via `hr:zkteco:sync`. Requires
    // `composer require jmrashed/zkteco` (suggested, not required, dependency) and at least one
    // ZktecoDevice record with employees linked via zkteco_device_id/zkteco_user_id.
    'zkteco' => [
        'enabled' => env('HR_ZKTECO_ENABLED', false),
        // Mark an employee 'late' if their earliest punch of the day is after this time
        // (24h "H:i", e.g. "09:15"). Null disables late-marking — status stays 'present'.
        'late_after' => env('HR_ZKTECO_LATE_AFTER'),
        // jmrashed/zkteco talks UDP and defaults to a 60.5s socket receive timeout, reused
        // for both the initial connect() probe and every chunk read during the attendance
        // log transfer. connect() only ever does a single read, so a short timeout here
        // fails fast against an unreachable device (powered off, wrong IP, firewalled)
        // instead of blocking `hr:zkteco:sync` for a full minute per dead device.
        'connect_timeout' => (int) env('HR_ZKTECO_CONNECT_TIMEOUT', 5),
        // The data transfer retries up to 10 times per stalled chunk, so it needs more
        // per-attempt patience than the connect probe — a large attendance log (hundreds of
        // KB) over a lossy link can genuinely need more than a few seconds per chunk even
        // though the device is alive and responding. Applied only after connect() succeeds.
        'transfer_timeout' => (int) env('HR_ZKTECO_TRANSFER_TIMEOUT', 20),
    ],

    'per_page' => [
        'employees'      => 25,
        'departments'    => 25,
        'designations'   => 25,
        'leave_requests' => 15,
        'attendances'    => 31,
    ],

    'admin_roles'          => ['admin', 'hr-admin'],
    'admin_role_attribute' => null,

];
