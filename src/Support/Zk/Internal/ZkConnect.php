<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk\Internal;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * @internal
 */
class ZkConnect
{
    public static function connect(ZkClient $self)
    {
        ZkPing::run($self);

        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_CONNECT;
        $command_string = '';
        $chksum = 0;
        $session_id = 0;
        $reply_id = -1 + ZkUtil::USHRT_MAX;

        $buf = ZkUtil::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($self->_zkclient, $buf, strlen($buf), 0, $self->_ip, $self->_port);

        try {
            @socket_recvfrom($self->_zkclient, $self->_data_recv, 1024, 0, $self->_ip, $self->_port);

            if (strlen($self->_data_recv) > 0) {
                $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($self->_data_recv, 0, 8));

                $session = hexdec($u['h6'].$u['h5']);
                if (empty($session)) {
                    return false;
                }
                $self->_session_id = $session;
                $result = ZkUtil::checkValid($self->_data_recv);

                if ($result == ZkUtil::CMD_ACK_UNAUTH) {
                    $command_string = ZkUtil::makeCommKey($self->_password, $self->_session_id);
                    $buf = ZkUtil::createHeader(ZkUtil::CMD_ACK_AUTH, 0, $self->_session_id, $reply_id, $command_string);
                    socket_sendto($self->_zkclient, $buf, strlen($buf), 0, $self->_ip, $self->_port);
                    @socket_recvfrom($self->_zkclient, $self->_data_recv, 1024, 0, $self->_ip, $self->_port);

                    if (strlen($self->_data_recv) > 0) {
                        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($self->_data_recv, 0, 8));
                        $session = hexdec($u['h6'].$u['h5']);
                        if (empty($session)) {
                            return false;
                        }

                        $result = ZkUtil::checkValid($self->_data_recv);
                        if ($result == ZkUtil::CMD_ACK_UNAUTH) {
                            return false;
                        }
                        $self->_session_id = $session;
                    }
                }

                return $result;
            }

            return false;
        } catch (\ErrorException|\Exception $e) {
            return false;
        }
    }

    public static function disconnect(ZkClient $self)
    {
        if (! ZkPing::run($self)) {
            return true;
        }

        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_EXIT;
        $command_string = '';
        $chksum = 0;
        $session_id = $self->_session_id;

        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($self->_data_recv, 0, 8));
        $reply_id = hexdec($u['h8'].$u['h7']);

        $buf = ZkUtil::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($self->_zkclient, $buf, strlen($buf), 0, $self->_ip, $self->_port);

        try {
            @socket_recvfrom($self->_zkclient, $self->_data_recv, 1024, 0, $self->_ip, $self->_port);
            $self->_session_id = 0;

            return ZkUtil::checkValid($self->_data_recv);
        } catch (\ErrorException|\Exception $e) {
            return false;
        }
    }
}
