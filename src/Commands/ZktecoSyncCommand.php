<?php

declare(strict_types = 1);

namespace Centrex\Hr\Commands;

use Centrex\Hr\Models\ZktecoDevice;
use Centrex\Hr\Support\ZktecoSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ZktecoSyncCommand extends Command
{
    public $signature = 'hr:zkteco:sync {--device=* : Sync only these ZktecoDevice IDs; default is all active devices}';

    public $description = 'Pull attendance punches from ZKTeco device(s) into hr_attendances';

    public function handle(ZktecoSync $sync): int
    {
        $this->info('Syncing ZKTeco devices...');

        $deviceIds = $this->option('device');

        $devices = $deviceIds !== []
            ? ZktecoDevice::whereIn('id', $deviceIds)->get()
            : ZktecoDevice::where('is_active', true)->get();

        if ($devices->isEmpty()) {
            $this->warn('No matching ZKTeco devices to sync.');

            return self::SUCCESS;
        }

        $this->info('Syncing ' . $devices->count() . ' device(s)...');

        $rows = [];
        $hadFailure = false;

        foreach ($devices as $device) {
            try {
                $this->info("Syncing device {$device->id} ({$device->name})... on {$device->ip_address}");

                $summary = $sync->syncDevice($device);
                $rows[] = [
                    $device->id,
                    $device->name,
                    $summary['synced'],
                    array_sum($summary['unmatched']),
                ];

                $this->info("Synced device {$device->id} ({$device->name}): {$summary['synced']} punches, " . array_sum($summary['unmatched']) . ' unmatched.');
            } catch (\Throwable $e) {
                $hadFailure = true;
                $rows[] = [$device->id, $device->name, 'ERROR', $e->getMessage()];

                Log::error('hr:zkteco:sync failed for device', [
                    'device_id'   => $device->id,
                    'device_name' => $device->name,
                    'ip_address'  => $device->ip_address,
                    'error'       => $e->getMessage(),
                ]);
            }

            $this->newLine();
        }

        $this->table(['Device ID', 'Name', 'Synced', 'Unmatched/Error'], $rows);
        $this->info('Sync completed.');

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }
}
