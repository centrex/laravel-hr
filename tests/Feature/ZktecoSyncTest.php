<?php

declare(strict_types = 1);

use Centrex\Hr\Contracts\ZktecoClient;
use Centrex\Hr\Exceptions\{ZktecoConnectionException, ZktecoNotConfiguredException};
use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\{Attendance, Employee, ZktecoDevice};
use Centrex\Hr\Support\ZktecoSync;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('migrate', ['--database' => 'testing']);
});

if (!function_exists('fakeZktecoClient')) {
    function fakeZktecoClient(array $logs): ZktecoClient
    {
        return new class($logs) implements ZktecoClient
        {
            public bool $connected = false;

            public function __construct(private array $logs) {}

            public function connect(): bool
            {
                return $this->connected = true;
            }

            public function disconnect(): void
            {
                $this->connected = false;
            }

            public function getAttendanceLogs(): array
            {
                return $this->logs;
            }
        };
    }
}

if (!function_exists('makeZktecoEmployee')) {
    function makeZktecoEmployee(ZktecoDevice $device, string $deviceUserId, string $code = 'E-1'): Employee
    {
        return Employee::query()->create([
            'code'             => $code,
            'name'             => 'Amina Rahman',
            'employment_type'  => 'full_time',
            'status'           => 'active',
            'zkteco_device_id' => $device->id,
            'zkteco_user_id'   => $deviceUserId,
        ]);
    }
}

it('syncs punches into attendance and reports unmatched device users', function (): void {
    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);
    $employee = makeZktecoEmployee($device, '7');

    $client = fakeZktecoClient([
        ['user_id' => '7', 'timestamp' => '2026-04-01 09:00:00', 'state' => 1],
        ['user_id' => '7', 'timestamp' => '2026-04-01 18:00:00', 'state' => 1],
        ['user_id' => '99', 'timestamp' => '2026-04-01 09:05:00', 'state' => 1], // unenrolled device user
    ]);

    $summary = app(ZktecoSync::class)->syncDevice($device, $client);

    expect($summary['synced'])->toBe(1)
        ->and($summary['unmatched'])->toBe(['99' => 1]);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('work_date', '2026-04-01')->first();

    expect($attendance)->not->toBeNull()
        ->and(Carbon::parse($attendance->check_in)->format('H:i'))->toBe('09:00')
        ->and(Carbon::parse($attendance->check_out)->format('H:i'))->toBe('18:00')
        ->and((float) $attendance->worked_hours)->toBe(9.0)
        ->and($attendance->status)->toBe('present');

    expect($device->fresh()->last_synced_at)->not->toBeNull();
});

it('leaves check_out null for a single punch and does not mark late by default', function (): void {
    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);
    $employee = makeZktecoEmployee($device, '7');

    $client = fakeZktecoClient([
        ['user_id' => '7', 'timestamp' => '2026-04-01 10:30:00', 'state' => 1],
    ]);

    app(ZktecoSync::class)->syncDevice($device, $client);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('work_date', '2026-04-01')->first();

    expect($attendance->check_out)->toBeNull()
        ->and($attendance->status)->toBe('present');
});

it('marks status late when check-in is after the configured threshold', function (): void {
    config()->set('hr.zkteco.late_after', '09:00');

    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);
    $employee = makeZktecoEmployee($device, '7');

    $client = fakeZktecoClient([
        ['user_id' => '7', 'timestamp' => '2026-04-01 09:30:00', 'state' => 1],
    ]);

    app(ZktecoSync::class)->syncDevice($device, $client);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('work_date', '2026-04-01')->first();

    expect($attendance->status)->toBe('late');
});

it('is idempotent when the same device is synced twice', function (): void {
    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);
    makeZktecoEmployee($device, '7');

    $logs = [
        ['user_id' => '7', 'timestamp' => '2026-04-01 09:00:00', 'state' => 1],
        ['user_id' => '7', 'timestamp' => '2026-04-01 18:00:00', 'state' => 1],
    ];

    $sync = app(ZktecoSync::class);
    $sync->syncDevice($device, fakeZktecoClient($logs));
    $sync->syncDevice($device, fakeZktecoClient($logs));

    expect(Attendance::count())->toBe(1);
});

it('preserves manual notes already recorded for a day when syncing', function (): void {
    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);
    $employee = makeZktecoEmployee($device, '7');

    Hr::recordAttendance($employee, [
        'work_date' => '2026-04-01',
        'notes'     => 'Manually backfilled by HR before device was linked',
    ]);

    $client = fakeZktecoClient([
        ['user_id' => '7', 'timestamp' => '2026-04-01 09:00:00', 'state' => 1],
        ['user_id' => '7', 'timestamp' => '2026-04-01 18:00:00', 'state' => 1],
    ]);

    app(ZktecoSync::class)->syncDevice($device, $client);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('work_date', '2026-04-01')->first();

    expect($attendance->notes)->toBe('Manually backfilled by HR before device was linked')
        ->and(Carbon::parse($attendance->check_in)->format('H:i'))->toBe('09:00');
});

it('throws when jmrashed/zkteco is not installed and no client is supplied', function (): void {
    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);

    app(ZktecoSync::class)->syncDevice($device);
})->throws(ZktecoNotConfiguredException::class);

it('throws a clear connection error naming the device when connect() fails, without touching attendance logs', function (): void {
    $device = ZktecoDevice::query()->create([
        'name'       => 'Warehouse Gate',
        'ip_address' => '192.168.1.250',
        'port'       => 4370,
    ]);

    $client = new class implements ZktecoClient
    {
        public bool $logsFetched = false;

        public function connect(): bool
        {
            return false;
        }

        public function disconnect(): void {}

        public function getAttendanceLogs(): array
        {
            $this->logsFetched = true;

            return [];
        }
    };

    try {
        app(ZktecoSync::class)->syncDevice($device, $client);
        $this->fail('Expected ZktecoConnectionException to be thrown.');
    } catch (ZktecoConnectionException $e) {
        expect($e->getMessage())
            ->toContain('Warehouse Gate')
            ->toContain('192.168.1.250:4370');
    }

    expect($client->logsFetched)->toBeFalse();
});

it('enforces one device-user-id per device via a unique constraint', function (): void {
    $device = ZktecoDevice::query()->create(['name' => 'Main Gate', 'ip_address' => '192.168.1.201']);
    makeZktecoEmployee($device, '7', 'E-1');

    makeZktecoEmployee($device, '7', 'E-2');
})->throws(Illuminate\Database\QueryException::class);
