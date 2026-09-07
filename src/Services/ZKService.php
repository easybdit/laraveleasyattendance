<?php

namespace Easybdit\LaravelEasyAttendance\Services;

/**
 * Thin wrapper around codinglibs/zkteco-php's ZKTeco client — kept as its
 * own class (rather than calling the library directly from
 * AttendanceDeviceSyncService) so a different pull-mode library can be
 * swapped in later without touching the sync logic.
 *
 * composer require codinglibs/zkteco-php to use pull-mode devices; push/ADMS
 * mode (AdmsPushController) doesn't need this at all.
 */
class ZKService
{
    protected $zk;

    public function __construct(string $ip, int $port = 4370, ?string $commKey = null, int $timeout = 10)
    {
        if (! class_exists(\CodingLibs\ZktecoPhp\Libs\ZKTeco::class)) {
            throw new \RuntimeException(
                'Pull-mode device sync needs codinglibs/zkteco-php. Install it with: composer require codinglibs/zkteco-php'
            );
        }

        $this->zk = new \CodingLibs\ZktecoPhp\Libs\ZKTeco($ip, $port);

        if (property_exists($this->zk, 'timeout')) {
            $this->zk->timeout = $timeout;
        }

        if ($commKey) {
            $this->zk->setPushCommKey($commKey);
        }
    }

    public function connect(): bool
    {
        return (bool) $this->zk->connect();
    }

    public function disconnect(): void
    {
        $this->zk->disconnect();
    }

    public function getAttendanceLogs(): array
    {
        if (method_exists($this->zk, 'getAttendances')) {
            return $this->zk->getAttendances();
        }

        throw new \Exception('No valid attendance method found in the ZKTeco library');
    }

    public function getUsers(): array
    {
        return method_exists($this->zk, 'getUsers') ? $this->zk->getUsers() : [];
    }
}
