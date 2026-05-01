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
    'web_middleware' => ['web', 'auth'],
    'web_prefix'     => 'hr',

    'api_enabled'    => env('HR_API_ENABLED', true),
    'api_middleware' => ['api', 'auth:sanctum'],
    'api_prefix'     => 'api/hr',

    'currency' => env('HR_CURRENCY', env('PAYROLL_CURRENCY', 'BDT')),

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
