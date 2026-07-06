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
