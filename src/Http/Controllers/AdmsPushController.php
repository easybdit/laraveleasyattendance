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
 * fixed paths with its serial number (SN) as a query param, so the only
 * gate is that SN must match an already-registered attendance_devices row.
 * These routes are registered WITHOUT the 'web' middleware group (see
 * LaravelEasyAttendanceServiceProvider), so there's no CSRF check to work
 * around either — nothing to configure in your app for this to work.
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

        AttendanceDevice::where('id', $device->id)->update(['last_seen_at' => now()]);
        $device->last_seen_at = now();

        return $device;
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}
