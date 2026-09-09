<?php

namespace Easybdit\LaravelEasyAttendance\Support\Zk\Internal;

use Easybdit\LaravelEasyAttendance\Support\Zk\ZkClient;

/**
 * ZK communication protocol constants and byte-level packet helpers.
 *
 * Ported (trimmed to what ZkClient actually calls) from
 * coding-libs/zkteco-php (MIT — see THIRD-PARTY-NOTICES.md), so pull-mode
 * device sync needs no external package. Byte-manipulation logic is
 * unchanged from upstream on purpose — it's what's been validated against
 * real ZKTeco hardware.
 *
 * @internal
 */
class ZkUtil
{
    const USHRT_MAX = 65535;

    const CMD_CONNECT = 1000;

    const CMD_EXIT = 1001;

    const CMD_ACK_OK = 2000;

    const CMD_ACK_UNAUTH = 2005;

    const CMD_ACK_AUTH = 1102;

    const CMD_PREPARE_DATA = 1500;

    const CMD_USER_TEMP_RRQ = 9;

    const CMD_OPTIONS_WRQ = 12;

    const CMD_ATT_LOG_RRQ = 13;

    const FCT_USER = 5;

    const COMMAND_TYPE_GENERAL = 'general';

    const COMMAND_TYPE_DATA = 'data';

    public static function trimDeviceData($data, $command = '')
    {
        if (! $command || ! $data) {
            return trim($data);
        }

        return trim(str_replace($command.'=', '', $data));
    }

    /**
     * Decode a timestamp retrieved from the timeclock.
     *
     * @param  int|string  $t
     * @return false|string Format: "Y-m-d H:i:s"
     */
    public static function decodeTime($t)
    {
        $second = floor($t % 60);
        $t = floor($t / 60);

        $minute = floor($t % 60);
        $t = floor($t / 60);

        $hour = floor($t % 24);
        $t = floor($t / 24);

        $day = floor($t % 31 + 1);
        $t = floor($t / 31);

        $month = floor($t % 12 + 1);
        $t = floor($t / 12);

        $year = floor($t + 2000);

        return date('Y-m-d H:i:s', strtotime(
            $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second
        ));
    }

    public static function reverseHex($hex)
    {
        $tmp = '';

        for ($i = strlen($hex); $i >= 0; $i--) {
            $tmp .= substr($hex, $i, 2);
            $i--;
        }

        return $tmp;
    }

    /**
     * Checks a returned packet to see if it returned CMD_PREPARE_DATA,
     * indicating that data packets are to be sent. Returns the amount of
     * bytes that are going to be sent.
     */
    public static function getSize(ZkClient $self)
    {
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($self->_data_recv, 0, 8));
        $command = hexdec($u['h2'].$u['h1']);

        if ($command == self::CMD_PREPARE_DATA) {
            $u = unpack('H2h1/H2h2/H2h3/H2h4', substr($self->_data_recv, 8, 4));

            return hexdec($u['h4'].$u['h3'].$u['h2'].$u['h1']);
        }

        return false;
    }

    /**
     * Calculates the checksum of the packet to be sent to the time clock.
     * Copied from zkemsdk.c.
     */
    public static function createChkSum($p)
    {
        $l = count($p);
        $chksum = 0;
        $i = $l;
        $j = 1;
        while ($i > 1) {
            $u = unpack('S', pack('C2', $p['c'.$j], $p['c'.($j + 1)]));

            $chksum += $u[1];

            if ($chksum > self::USHRT_MAX) {
                $chksum -= self::USHRT_MAX;
            }
            $i -= 2;
            $j += 2;
        }

        if ($i) {
            $chksum = $chksum + $p['c'.strval(count($p))];
        }

        while ($chksum > self::USHRT_MAX) {
            $chksum -= self::USHRT_MAX;
        }

        $chksum = $chksum > 0 ? -$chksum : abs($chksum);

        $chksum -= 1;
        while ($chksum < 0) {
            $chksum += self::USHRT_MAX;
        }

        return pack('S', $chksum);
    }

    /**
     * Puts the parts that make up a packet together and packs them into
     * a byte string.
     */
    public static function createHeader($command, $chksum, $session_id, $reply_id, $command_string)
    {
        $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id).$command_string;

        $buf = unpack('C'.(8 + strlen($command_string)).'c', $buf);

        $u = unpack('S', self::createChkSum($buf));
        $chksum = is_array($u) ? reset($u) : $u;

        $reply_id += 1;
        if ($reply_id >= self::USHRT_MAX) {
            $reply_id -= self::USHRT_MAX;
        }

        $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id);

        return $buf.$command_string;
    }

    public static function makeCommKey(int $key, int $session_id, $ticks = 50)
    {
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            $k = ($key & (1 << $i)) ? ($k << 1 | 1) : $k << 1;
        }
        $k += $session_id;
        $k = pack('I', $k);
        $k = unpack('C4', $k);

        $k = pack(
            'C4',
            $k[1] ^ ord('Z'),
            $k[2] ^ ord('K'),
            $k[3] ^ ord('S'),
            $k[4] ^ ord('O')
        );

        $k = unpack('S2', $k);
        $k = pack('S2', $k[2], $k[1]);
        $B = 0xFF & 50;
        $k = unpack('C4', $k);

        return pack('C4', $k[1] ^ $B, $k[2] ^ $B, $B, $k[4] ^ $B);
    }

    /**
     * Checks a returned packet to see if it returned CMD_ACK_OK,
     * indicating success (or one of the other ack codes we care about).
     */
    public static function checkValid($reply)
    {
        $u = unpack('H2h1/H2h2', substr($reply, 0, 8));
        $command = hexdec($u['h2'].$u['h1']);

        return in_array($command, [self::CMD_ACK_AUTH, self::CMD_ACK_OK, self::CMD_ACK_UNAUTH], true)
            ? $command
            : false;
    }

    /**
     * Receive (possibly multi-packet) data from the device.
     *
     * @param  bool  $first  if false, strip the first 8 bytes for the first chunk too
     */
    public static function recData(ZkClient $self, $maxErrors = 10, $first = true)
    {
        $data = '';
        $bytes = self::getSize($self);

        if ($bytes) {
            $received = 0;
            $errors = 0;

            while ($bytes > $received) {
                $ret = @socket_recvfrom($self->_zkclient, $dataRec, 1032, 0, $self->_ip, $self->_port);

                if ($ret === false) {
                    if ($errors < $maxErrors) {
                        $errors++;
                        sleep(1);

                        continue;
                    }

                    return '';
                }

                if ($first === false) {
                    $dataRec = substr($dataRec, 8);
                }

                $data .= $dataRec;
                $received += strlen($dataRec);

                unset($dataRec);
                $first = false;
            }

            // flush socket
            @socket_recvfrom($self->_zkclient, $dataRec, 1024, 0, $self->_ip, $self->_port);
            unset($dataRec);
        }

        return $data;
    }
}
