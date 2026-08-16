<?php

declare(strict_types = 1);

namespace Centrex\Hr\Commands;

use Centrex\Hr\Models\ZktecoDevice;
use Centrex\Hr\Support\ZktecoSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ZktecoSyncCommand extends Command
{
    public $signature = 'hr:zkteco:sync
        {--device=* : Sync only these ZktecoDevice IDs; default is all active devices}
        {--from= : Only sync punches on or after this date (Y-m-d); default is no lower bound}
        {--to= : Only sync punches on or before this date (Y-m-d); default is no upper bound}';

    public $description = 'Pull attendance punches from ZKTeco device(s) into hr_attendances';

    public function handle(ZktecoSync $sync): int
    {
        $this->info('Syncing ZKTeco devices...');

        try {
            $fromOption = $this->option('from');
            $toOption = $this->option('to');
            $from = is_string($fromOption) && $fromOption !== '' ? Carbon::parse($fromOption) : null;
            $to = is_string($toOption) && $toOption !== '' ? Carbon::parse($toOption) : null;
        } catch (\Throwable) {
            $this->error('Invalid --from/--to date. Use a format like 2026-04-01.');

            return self::FAILURE;
        }

        if ($from && $to && $from->gt($to)) {
            $this->error('--from date must not be after --to date.');

            return self::FAILURE;
        }

        $deviceIds = $this->option('device');

        $devices = $deviceIds !== []
            ? ZktecoDevice::whereIn('id', $deviceIds)->get()
            : ZktecoDevice::where('is_active', true)->get();

        if ($devices->isEmpty()) {
            $this->warn('No matching ZKTeco devices to sync.');

            return self::SUCCESS;
        }

        $this->info('Syncing ' . $devices->count() . ' device(s)...');
        $this->newLine();

        $rows = [];
        $hadFailure = false;

        foreach ($devices as $device) {
            $summary = null;
            $error = null;

            $this->components->task(
                "Device {$device->id} ({$device->name}) on {$device->ip_address}",
                function () use ($sync, $device, $from, $to, &$summary, &$error): bool {
                    try {
                        $summary = $sync->syncDevice($device, from: $from, to: $to);

                        return true;
                    } catch (\Throwable $e) {
                        $error = $e;

                        return false;
                    }
                },
            );

            if ($summary === null) {
                $hadFailure = true;
                $message = $error?->getMessage() ?? 'Unknown error';
                $rows[] = [$device->id, $device->name, 'ERROR', $message];

                Log::error('hr:zkteco:sync failed for device', [
                    'device_id'   => $device->id,
                    'device_name' => $device->name,
                    'ip_address'  => $device->ip_address,
                    'error'       => $message,
                ]);

                continue;
            }

            $rows[] = [$device->id, $device->name, $summary['synced'], array_sum($summary['unmatched'])];
        }

        $this->newLine();
        $this->table(['Device ID', 'Name', 'Synced', 'Unmatched/Error'], $rows);
        $this->info('Sync completed.');

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }
}
