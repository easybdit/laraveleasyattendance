<?php

namespace Easybdit\LaravelEasyAttendance\Services;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * Thin wrapper around the package's own built-in ZK protocol client
 * (Support\Zk\ZkClient) — kept as its own class, separate from
 * AttendanceDeviceSyncService, so a different pull-mode implementation
 * could be swapped in later without touching the sync logic.
 *
 * No external package needed — push/ADMS mode (AdmsPushController)
 * never did, and pull mode doesn't either now (see ZkClient's docblock
 * for why: the wire protocol is ported in-package under MIT license).
 */
class ZKService
{
    protected ZkClient $zk;

    public function __construct(string $ip, int $port = 4370, ?string $commKey = null, int $timeout = 10)
    {
        $this->zk = new ZkClient($ip, $port, $timeout);

        if ($commKey) {
            $this->zk->setPushCommKey($commKey);
        }
    }

    public function connect(): bool
    {
        return $this->zk->connect();
    }

    public function disconnect(): void
    {
        $this->zk->disconnect();
    }

    public function getAttendanceLogs(): array
    {
        return $this->zk->getAttendances();
    }

    public function getUsers(): array
    {
        return $this->zk->getUsers();
    }
}
