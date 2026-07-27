<?php

declare(strict_types = 1);

namespace Centrex\Hr\Commands;

use Centrex\Hr\Models\ZktecoDevice;
use Centrex\Hr\Support\ZktecoSync;
use Illuminate\Console\Command;

class ZktecoSyncCommand extends Command
{
    public $signature = 'hr:zkteco:sync {--device=* : Sync only these ZktecoDevice IDs; default is all active devices}';

    public $description = 'Pull attendance punches from ZKTeco device(s) into hr_attendances';

    public function handle(ZktecoSync $sync): int
    {
        $deviceIds = $this->option('device');

        $devices = $deviceIds !== []
            ? ZktecoDevice::whereIn('id', $deviceIds)->get()
            : ZktecoDevice::where('is_active', true)->get();

        if ($devices->isEmpty()) {
            $this->warn('No matching ZKTeco devices to sync.');

            return self::SUCCESS;
        }

        $rows = [];
        $hadFailure = false;

        foreach ($devices as $device) {
            try {
                $summary = $sync->syncDevice($device);
                $rows[] = [
                    $device->id,
                    $device->name,
                    $summary['synced'],
                    array_sum($summary['unmatched']),
                ];
            } catch (\Throwable $e) {
                $hadFailure = true;
                $rows[] = [$device->id, $device->name, 'ERROR', $e->getMessage()];
            }
        }

        $this->table(['Device ID', 'Name', 'Synced', 'Unmatched/Error'], $rows);

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }
}
