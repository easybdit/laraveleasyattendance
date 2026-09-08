<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk;

use Easybdit\LaravelEasyAttendance\Support\Zk\Internal\ZkAttendance;
use Easybdit\LaravelEasyAttendance\Support\Zk\Internal\ZkConnect;
use Easybdit\LaravelEasyAttendance\Support\Zk\Internal\ZkDevice;
use Easybdit\LaravelEasyAttendance\Support\Zk\Internal\ZkUser;
use Easybdit\LaravelEasyAttendance\Support\Zk\Internal\ZkUtil;

/**
 * A minimal ZKTeco UDP protocol client — connect, fetch attendance logs,
 * fetch enrolled users, set the device's push comm key. Ported (trimmed to
 * just what pull-mode device sync needs — no fingerprint/face/LCD/time/
 * device-management surface) from coding-libs/zkteco-php (MIT license,
 * see THIRD-PARTY-NOTICES.md) so this package has zero external
 * dependencies for pull-mode sync. The wire-protocol logic itself
 * (packet framing, checksum, record parsing) is unchanged from upstream
 * on purpose — it's what's been validated against real ZKTeco hardware.
 *
 * @internal Not a public API of this package — use
 *           Easybdit\LaravelEasyAttendance\Services\ZKService instead.
 */
class ZkClient
{
    public $_ip;

    public $_port;

    /** @var resource|\Socket */
    public $_zkclient;

    public $_data_recv = '';

    public $_session_id = 0;

    public $_section = '';

    public $_requiredPing = false;

    public $_silentPing = true;

    public $_password = 0;

    public function __construct(string $ip, int $port = 4370, int $timeout = 10)
    {
        $this->_ip = $ip;
        $this->_port = $port;

        $this->_zkclient = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        socket_set_option($this->_zkclient, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 500000]);
    }

    public function _command(string $command, string $command_string, string $type = ZkUtil::COMMAND_TYPE_GENERAL)
    {
        $chksum = 0;
        $session_id = $this->_session_id;

        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->_data_recv, 0, 8));
        $reply_id = hexdec($u['h8'].$u['h7']);

        $buf = ZkUtil::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($this->_zkclient, $buf, strlen($buf), 0, $this->_ip, $this->_port);

        try {
            @socket_recvfrom($this->_zkclient, $this->_data_recv, 1024, 0, $this->_ip, $this->_port);

            $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($this->_data_recv, 0, 8));

            $ret = false;
            $session = hexdec($u['h6'].$u['h5']);

            if ($type === ZkUtil::COMMAND_TYPE_GENERAL && $session_id === $session) {
                $ret = substr($this->_data_recv, 8);
            } elseif ($type === ZkUtil::COMMAND_TYPE_DATA && ! empty($session)) {
                $ret = $session;
            }

            return $ret;
        } catch (\ErrorException|\Exception $e) {
            return false;
        }
    }

    public function connect(): bool
    {
        return (bool) ZkConnect::connect($this);
    }

    public function disconnect(): bool
    {
        return (bool) ZkConnect::disconnect($this);
    }

    /**
     * @return array<int, array{uid: int, user_id: int, state: int, record_time: string, type: int, device_ip: string}>
     */
    public function getAttendances(): array
    {
        return ZkAttendance::get($this);
    }

    /**
     * @return array<int|string, array{uid: int, user_id: int, name: string, role: int, password: string, card_no: string, device_ip: string}>
     */
    public function getUsers(): array
    {
        return ZkUser::get($this);
    }

    public function setPushCommKey($value)
    {
        return ZkDevice::setPushCommKey($this, $value);
    }
}
