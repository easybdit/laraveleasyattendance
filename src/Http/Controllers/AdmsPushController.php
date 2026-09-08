<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Easybdit\LaravelEasyAttendance\Services\AttendanceDeviceSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Receives attendance pushed by ZKTeco/compatible devices running in
 * "Cloud Server / ADMS" mode — the reverse of the pull direction
 * (AttendanceDeviceController::pull(), which connects OUT to a device's IP).
 * Use push mode for a device the server can't reach directly (remote site,
 * no static IP, no VPN) — the device dials home to us instead.
 *
 * No Laravel auth is possible here — the device firmware just calls these
 * fixed paths with its serial number (SN) as a query param, so the main
 * gate is that SN must match an already-registered attendance_devices row
 * (optionally also its registered IP — see
 * config('attendance.device_sync.adms_verify_ip')). These routes are
 * registered WITHOUT the 'web' middleware group but WITH `throttle` (see
 * LaravelEasyAttendanceServiceProvider) — no CSRF check to work around,
 * and a rate limit against a flood of requests to a route nothing else
 * gates.
 */
class AdmsPushController extends Controller
{
    /**
     * GET /iclock/cdata — handshake the device performs on connect/interval.
     */
    public function handshake(Request $request): Response
    {
        $sn = $request->query('SN');
        $device = $this->resolveDevice($sn, $request);

        if (! $device) {
            return $this->text('ERROR');
        }

        $body = implode("\n", [
            "GET OPTION FROM: {$sn}",
            'Stamp=9999',
            'OpStamp=9999',
            'ErrorDelay=30',
            'Delay=30',
            'TransTimes=00:00;12:05',
            'TransInterval=1',
            'TransFlag=1111000000',
            'Realtime=1',
            'Encrypt=0',
        ])."\n";

        return $this->text($body);
    }

    /**
     * POST /iclock/cdata — device pushes a batch of ATTLOG/OPERLOG rows.
     * Must reply "OK: {n}" or the device will resend the batch.
     */
    public function store(Request $request, AttendanceDeviceSyncService $sync): Response
    {
        $sn = $request->query('SN');
        $table = $request->query('table');
        $device = $this->resolveDevice($sn, $request);

        if (! $device) {
            return $this->text('ERROR');
        }

        if ($table !== 'ATTLOG') {
            // OPERLOG (user/photo sync) etc. — acknowledged, not processed.
            return $this->text('OK');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($request->getContent())) ?: [];
        $count = $sync->ingestPush($device, $lines);

        return $this->text("OK: {$count}");
    }

    /**
     * GET /iclock/getrequest — device polls for pending remote commands.
     * We never issue any, so always "no commands" — still a heartbeat.
     */
    public function pendingCommands(Request $request): Response
    {
        $this->resolveDevice($request->query('SN'), $request);

        return $this->text('OK');
    }

    /**
     * POST /iclock/devicecmd — device reports back a command result.
     * Nothing to do since we never send any.
     */
    public function commandAck(Request $request): Response
    {
        $this->resolveDevice($request->query('SN'), $request);

        return $this->text('OK');
    }

    private function resolveDevice(?string $sn, Request $request): ?AttendanceDevice
    {
        if (! $sn) {
            return null;
        }

        $device = AttendanceDevice::where('serial_number', $sn)->first();

        if (! $device) {
            Log::warning('ADMS push from unregistered device serial', ['sn' => $sn, 'ip' => $request->ip()]);

            return null;
        }

        // Optional extra gate — off by default (see the config's own
        // comment on why): a device's serial number is the *only* thing
        // ADMS push can authenticate with, since the firmware carries
        // nothing else. If you know a device's source IP is stable and
        // reaches you directly, this catches a push claiming a serial
        // number it doesn't actually own.
        if (config('attendance.device_sync.adms_verify_ip', false) && $device->ip && $device->ip !== $request->ip()) {
            Log::warning('ADMS push IP mismatch for registered device', [
                'sn' => $sn, 'expected_ip' => $device->ip, 'actual_ip' => $request->ip(),
            ]);

            return null;
        }

        AttendanceDevice::where('id', $device->id)->update(['last_seen_at' => now()]);
        $device->last_seen_at = now();

        return $device;
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}
