<?php

declare(strict_types = 1);

namespace Centrex\Hr\Support\Zkteco;

use Centrex\Hr\Contracts\ZktecoClient;
use Illuminate\Support\Carbon;

/**
 * Wraps the `jmrashed/zkteco` package (a suggested, not required, dependency — see
 * composer.json `suggest` and ZktecoSync::makeClient()) so the rest of the package never
 * references the vendor SDK directly. Instantiated dynamically (not type-hinted against
 * \Jmrashed\Zkteco\Lib\ZKTeco) so this class — and `composer test:types` — stays valid even
 * when the package isn't installed; ZktecoSync::makeClient() is the only place that checks
 * class_exists() before ever constructing this.
 */
class JmrashedZktecoClient implements ZktecoClient
{
    private object $device;

    /**
     * $connectTimeoutSeconds overrides the vendor SDK's hardcoded 60.5s UDP socket receive
     * timeout (Jmrashed\Zkteco\Lib\ZKTeco::__construct()) — without this, an unreachable device
     * makes connect() block for a full minute before returning false, which is indistinguishable
     * from `hr:zkteco:sync` hanging. $device->_zkclient is the vendor class's public `Socket`
     * resource; overriding SO_RCVTIMEO on it directly is the only way to shorten this, since
     * the vendor constructor doesn't accept a timeout parameter.
     */
    public function __construct(string $ipAddress, int $port = 4370, int $connectTimeoutSeconds = 5)
    {
        $this->configureVendorLogPath();

        $class = '\\Jmrashed\\Zkteco\\Lib\\ZKTeco';
        $this->device = new $class($ipAddress, $port);

        if (isset($this->device->_zkclient) && $this->device->_zkclient !== false) {
            socket_set_option($this->device->_zkclient, SOL_SOCKET, SO_RCVTIMEO, [
                'sec'  => $connectTimeoutSeconds,
                'usec' => 0,
            ]);
        }
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

    /**
     * jmrashed/zkteco hardcodes its own error log to a `logs/` subdirectory inside its own
     * package folder (vendor/jmrashed/zkteco/src/Lib/logs/error.log) — a directory Composer
     * never creates, since an empty dir isn't shipped in the package's git history. It's
     * called from inside the UDP retry loop that recData() falls back to whenever the device
     * drops packets mid-transfer (a real condition, not just a config error), so leaving this
     * unset turns an ordinary packet-loss retry into a fatal "Failed to open stream" once the
     * retry budget is exhausted. Util::logger() checks defined('ZK_LIB_LOG') first and uses
     * that path instead when set — must be defined before the vendor class is ever
     * constructed, and only once per process (redefining a constant is a fatal error).
     */
    private function configureVendorLogPath(): void
    {
        if (defined('ZK_LIB_LOG')) {
            return;
        }

        $logPath = storage_path('logs/zkteco.log');

        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        define('ZK_LIB_LOG', $logPath);
    }
}
