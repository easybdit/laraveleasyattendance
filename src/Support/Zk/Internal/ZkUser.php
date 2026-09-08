<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk\Internal;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * @internal
 */
class ZkUser
{
    /**
     * @return array<int|string, array{uid: int, user_id: int, name: string, role: int, password: string, card_no: string, device_ip: string}>
     */
    public static function get(ZkClient $self): array
    {
        ZkPing::run($self);

        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_USER_TEMP_RRQ;
        $command_string = chr(ZkUtil::FCT_USER);

        $session = $self->_command($command, $command_string, ZkUtil::COMMAND_TYPE_DATA);
        if ($session === false) {
            return [];
        }

        $userData = ZkUtil::recData($self);

        $users = [];
        if (! empty($userData)) {
            $userData = substr($userData, 11);

            while (strlen($userData) > 72) {
                $u = unpack('H144', substr($userData, 0, 72));

                $u1 = hexdec(substr($u[1], 2, 2));
                $u2 = hexdec(substr($u[1], 4, 2));
                $uid = $u1 + ($u2 * 256);
                $cardno = hexdec(substr($u[1], 78, 2).substr($u[1], 76, 2).substr($u[1], 74, 2).substr($u[1], 72, 2)).' ';
                $role = hexdec(substr($u[1], 6, 2)).' ';
                $password = hex2bin(substr($u[1], 8, 16)).' ';
                $name = hex2bin(substr($u[1], 24, 74)).' ';
                $userid = hex2bin(substr($u[1], 98, 72)).' ';

                $password = explode(chr(0), $password, 2)[0];
                $userid = explode(chr(0), $userid, 2)[0];
                $name = explode(chr(0), $name, 3);
                $name = mb_convert_encoding($name[0], 'UTF-8', 'ISO-8859-1');
                $cardno = str_pad($cardno, 11, '0', STR_PAD_LEFT);

                if ($name == '') {
                    $name = $userid;
                }

                $data = [
                    'uid' => $uid,
                    'user_id' => intval($userid),
                    'name' => $name,
                    'role' => intval($role),
                    'password' => $password,
                    'card_no' => $cardno,
                    'device_ip' => $self->_ip,
                ];

                $users[$userid] = $data;

                $userData = substr($userData, 72);
            }
        }

        return $users;
    }
}
