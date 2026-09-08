<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk\Internal;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * @internal
 */
class ZkAttendance
{
    /**
     * @return array<int, array{uid: int, user_id: int, state: int, record_time: string, type: int, device_ip: string}>
     */
    public static function get(ZkClient $self): array
    {
        ZkPing::run($self);

        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_ATT_LOG_RRQ;
        $command_string = '';

        $session = $self->_command($command, $command_string, ZkUtil::COMMAND_TYPE_DATA);
        if ($session === false) {
            return [];
        }

        $attData = ZkUtil::recData($self);

        $attendance = [];
        if (! empty($attData)) {
            $attData = substr($attData, 10);

            while (strlen($attData) > 40) {
                $u = unpack('H78', substr($attData, 0, 39));

                $u1 = hexdec(substr($u[1], 4, 2));
                $u2 = hexdec(substr($u[1], 6, 2));
                $uid = $u1 + ($u2 * 256);

                $id = hex2bin(substr($u[1], 8, 18));
                $id = str_replace(chr(0), '', $id);

                $state = hexdec(substr($u[1], 56, 2));
                $timestamp = ZkUtil::decodeTime(hexdec(ZkUtil::reverseHex(substr($u[1], 58, 8))));
                $type = hexdec(ZkUtil::reverseHex(substr($u[1], 66, 2)));

                $attendance[] = [
                    'uid' => $uid,
                    'user_id' => intval($id),
                    'state' => $state,
                    'record_time' => $timestamp,
                    'type' => $type,
                    'device_ip' => $self->_ip,
                ];

                $attData = substr($attData, 40);
            }
        }

        return $attendance;
    }
}
