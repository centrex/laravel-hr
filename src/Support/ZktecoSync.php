<?php

declare(strict_types = 1);

namespace Centrex\Hr\Support;

use Centrex\Hr\Contracts\ZktecoClient;
use Centrex\Hr\Exceptions\{ZktecoConnectionException, ZktecoNotConfiguredException};
use Centrex\Hr\Facades\Hr;
use Centrex\Hr\Models\{Employee, ZktecoDevice};
use Centrex\Hr\Support\Zkteco\JmrashedZktecoClient;
use Illuminate\Support\Carbon;

/**
 * Pulls attendance punches from a ZKTeco device and writes them through the same
 * Hr::recordAttendance() upsert path the HR/admin manual-entry UI already uses, so worked
 * hours computation and the (employee_id, work_date) idempotency guarantee are inherited
 * for free. Punches are grouped per employee per day: earliest punch = check_in, latest
 * (if there's more than one) = check_out — the standard punch-clock convention.
 */
class ZktecoSync
{
    /**
     * The device itself has no way to filter its log by date — getAttendanceLogs() always
     * returns the full on-device log — so $from/$to are applied client-side once the log has
     * been pulled, before punches are grouped into per-employee-per-day check-in/check-out
     * pairs. $from is compared from its start of day and $to through its end of day, so
     * passing the same date for both re-syncs exactly that one day.
     *
     * @return array{device_id: int, synced: int, unmatched: array<string, int>}
     */
    public function syncDevice(
        ZktecoDevice $device,
        ?ZktecoClient $client = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $client ??= $this->makeClient($device);

        if (!$client->connect()) {
            throw new ZktecoConnectionException(
                "Could not connect to ZKTeco device \"{$device->name}\" at {$device->ip_address}:{$device->port}. " .
                'Check that the device is powered on, reachable on the network, and that the IP/port are correct.',
            );
        }

        try {
            $logs = $client->getAttendanceLogs();
        } finally {
            $client->disconnect();
        }

        if ($from) {
            $from = $from->clone()->startOfDay();
            $logs = array_values(array_filter(
                $logs,
                static fn (array $log): bool => Carbon::parse($log['timestamp'])->gte($from),
            ));
        }

        if ($to) {
            $to = $to->clone()->endOfDay();
            $logs = array_values(array_filter(
                $logs,
                static fn (array $log): bool => Carbon::parse($log['timestamp'])->lte($to),
            ));
        }

        $synced = 0;
        $unmatched = [];

        $punchesByEmployeeAndDay = collect($logs)->groupBy(
            fn (array $log): string => $log['user_id'] . '|' . Carbon::parse($log['timestamp'])->toDateString(),
        );

        foreach ($punchesByEmployeeAndDay as $key => $punches) {
            [$deviceUserId, $workDate] = explode('|', $key, 2);

            $employee = Employee::where('zkteco_device_id', $device->id)
                ->where('zkteco_user_id', $deviceUserId)
                ->first();

            if (!$employee) {
                $unmatched[$deviceUserId] = ($unmatched[$deviceUserId] ?? 0) + $punches->count();

                continue;
            }

            $timestamps = $punches->pluck('timestamp')
                ->map(fn ($timestamp): Carbon => Carbon::parse($timestamp))
                ->sort()
                ->values();

            $checkIn = $timestamps->first();
            $checkOut = $timestamps->count() > 1 ? $timestamps->last() : null;

            Hr::recordAttendance($employee, [
                'work_date' => $workDate,
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'status'    => $this->statusFor($checkIn),
            ]);

            $synced++;
        }

        $device->forceFill(['last_synced_at' => now()])->save();

        return [
            'device_id' => $device->id,
            'synced'    => $synced,
            'unmatched' => $unmatched,
        ];
    }

    private function statusFor(?Carbon $checkIn): string
    {
        $lateAfter = config('hr.zkteco.late_after');

        if ($checkIn && $lateAfter && $checkIn->format('H:i') > $lateAfter) {
            return 'late';
        }

        return 'present';
    }

    private function makeClient(ZktecoDevice $device): ZktecoClient
    {
        if (!class_exists('\\Jmrashed\\Zkteco\\Lib\\ZKTeco')) {
            throw new ZktecoNotConfiguredException(
                'The jmrashed/zkteco package is not installed. Run `composer require jmrashed/zkteco` to sync from ZKTeco devices.',
            );
        }

        return new JmrashedZktecoClient(
            $device->ip_address,
            $device->port,
            (int) config('hr.zkteco.connect_timeout', 5),
            (int) config('hr.zkteco.transfer_timeout', 20),
        );
    }
}
