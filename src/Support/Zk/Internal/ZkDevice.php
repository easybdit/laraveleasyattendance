<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk\Internal;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * @internal
 */
class ZkDevice
{
    public static function setPushCommKey(ZkClient $self, $value)
    {
        ZkPing::run($self);

        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_OPTIONS_WRQ;
        $command_string = "pushcommkey={$value}";

        return $self->_command($command, $command_string);
    }
}
