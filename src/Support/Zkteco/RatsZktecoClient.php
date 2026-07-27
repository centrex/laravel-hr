<?php

declare(strict_types = 1);

namespace Centrex\Hr\Support\Zkteco;

use Centrex\Hr\Contracts\ZktecoClient;
use Illuminate\Support\Carbon;

/**
 * Wraps the `rats/zkteco` package (a suggested, not required, dependency — see
 * composer.json `suggest` and ZktecoSync::make()) so the rest of the package never
 * references the vendor SDK directly. Instantiated dynamically (not type-hinted against
 * \Rats\Zkteco\Lib\ZKTeco) so this class — and `composer test:types` — stays valid even
 * when the package isn't installed; ZktecoSync::make() is the only place that checks
 * class_exists() before ever constructing this.
 */
class RatsZktecoClient implements ZktecoClient
{
    private object $device;

    public function __construct(string $ipAddress, int $port = 4370)
    {
        $class = '\\Rats\\Zkteco\\Lib\\ZKTeco';
        $this->device = new $class($ipAddress, $port);
    }

    public function connect(): bool
    {
        return (bool) $this->device->connect();
    }

    public function disconnect(): void
    {
        $this->device->disconnect();
    }

    public function getAttendanceLogs(): array
    {
        $logs = $this->device->getAttendance() ?: [];

        return array_map(static fn (array $row): array => [
            'user_id'   => (string) $row['id'],
            'timestamp' => Carbon::parse($row['timestamp']),
            'state'     => (int) ($row['state'] ?? 0),
        ], $logs);
    }
}
