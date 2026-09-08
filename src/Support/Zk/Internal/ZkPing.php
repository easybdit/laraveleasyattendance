<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk\Internal;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * Optional ICMP pre-check before talking to a device over UDP — off by
 * default (ZkClient's $shouldPing constructor arg), matching how this
 * package's ZKService has always used it.
 *
 * @internal
 */
class ZkPing
{
    public static function run(ZkClient $self, $throw = false)
    {
        if (! $self->_requiredPing) {
            return true;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $pingCommand = $isWindows
            ? 'ping -n 1 '.escapeshellarg($self->_ip)
            : 'ping -c 1 -W 5 '.escapeshellarg($self->_ip);

        $output = null;
        $resultCode = null;
        exec($pingCommand, $output, $resultCode);

        $result = $resultCode === 0;

        if (! $result && ($throw || ! $self->_silentPing)) {
            throw new \RuntimeException("can't reach device ({$self->_ip})");
        }

        return $result;
    }
}
