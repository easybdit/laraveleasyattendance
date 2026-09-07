<?php

namespace Easybdit\LaravelEasyAttendance\Services;

use Easybdit\LaravelEasyAttendance\Events\AttendanceDeviceSyncFailed;
use Easybdit\LaravelEasyAttendance\Events\AttendanceRecorded;
use Easybdit\LaravelEasyAttendance\Models\Attendance;
use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Shared ingestion logic for both sync directions:
 *   - pull(): we connect out to the device (ZKService) and fetch its log
 *   - ingestPush(): the device pushed a batch of raw ATTLOG lines to us
 *
 * Both end up calling ingestLogs() with the same [pin, time, state] shape,
 * so a punch is handled identically no matter which direction it arrived
 * from — one place to get the matching/dedup/event logic right instead of
 * two copies that can quietly drift apart.
 */
class AttendanceDeviceSyncService
{
    public function pull(AttendanceDevice $device): array
    {
        if (! $device->ip) {
            return ['success' => false, 'message' => 'This device has no IP configured — it looks like a push/ADMS device, which sends attendance here automatically and has nothing to pull.'];
        }

        try {
            $svc = new ZKService($device->ip, (int) ($device->port ?: 4370), $device->comm_key, 15);

            if (! $svc->connect()) {
                $message = "Cannot connect to {$device->ip}:{$device->port}";
                $this->recordSyncFailure($device, $message);

                return ['success' => false, 'message' => $message];
            }

            $logs = $svc->getAttendanceLogs();
            $svc->disconnect();
        } catch (\Throwable $e) {
            $this->recordSyncFailure($device, $e->getMessage());

            return ['success' => false, 'message' => 'Device error: '.$e->getMessage()];
        }

        $lines = [];
        foreach ($logs as $log) {
            $lines[] = [
                'pin' => $log['user_id'] ?? null,
                'time' => $log['record_time'] ?? null,
                'state' => $log['state'] ?? null,
            ];
        }

        $result = $this->ingestLogs($device, $lines);

        $device->update(['last_synced_at' => now(), 'sync_fail_count' => 0]);

        $message = count($logs)." logs fetched · {$result['imported']} new";
        if ($result['unmatched']) {
            $sample = implode(', ', array_slice(array_keys($result['unmatched']), 0, 5));
            $message .= ' · '.count($result['unmatched'])." PIN(s) not matched to any subject (e.g. {$sample}) — set that person's device PIN";
        }

        return [
            'success' => true,
            'message' => $message,
            'total' => count($logs),
            'imported' => $result['imported'],
            'unmatched' => array_keys($result['unmatched']),
        ];
    }

    /**
     * @param  string[]  $rawLines  Tab-separated ADMS ATTLOG lines: "PIN\tTIME\tSTATE\t..."
     */
    public function ingestPush(AttendanceDevice $device, array $rawLines): int
    {
        $lines = [];
        foreach ($rawLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $fields = preg_split('/\t+/', $line);
            $lines[] = [
                'pin' => $fields[0] ?? null,
                'time' => $fields[1] ?? null,
                'state' => $fields[2] ?? null,
            ];
        }

        $result = $this->ingestLogs($device, $lines);

        $device->forceFill(['last_synced_at' => now(), 'sync_fail_count' => 0])->save();

        return $result['imported'];
    }

    /**
     * @param  array<int, array{pin: ?string, time: ?string, state: ?string}>  $lines
     * @return array{imported: int, unmatched: array<string, true>}
     */
    protected function ingestLogs(AttendanceDevice $device, array $lines): array
    {
        $subjectModel = config('attendance.subject_model');
        $pinColumn = config('attendance.device_sync.pin_column', 'device_user_id');

        $subjectMap = $subjectModel::query()
            ->whereNotNull($pinColumn)
            ->pluck((new $subjectModel)->getKeyName(), $pinColumn);

        $imported = 0;
        $unmatched = [];

        foreach ($lines as $line) {
            $pin = $line['pin'] ?? null;
            $time = $line['time'] ?? null;

            if (! $pin || ! $time) {
                continue;
            }

            $subjectId = $subjectMap[$pin] ?? null;
            if (! $subjectId) {
                $unmatched[$pin] = true;

                continue;
            }

            try {
                $ts = Carbon::parse($time);
            } catch (\Throwable) {
                continue;
            }

            // ZK device convention: state 1 = check-out, anything else
            // (0, missing, fingerprint/card variants) = check-in.
            $type = ($line['state'] ?? null) == 1 ? 'check_out' : 'check_in';

            try {
                $attendance = Attendance::firstOrCreate([
                    'device_id' => $device->id,
                    'device_user_id' => $pin,
                    'time' => $ts,
                ], [
                    'subject_type' => $subjectModel,
                    'subject_id' => $subjectId,
                    'type' => $type,
                    'source' => 'device',
                    'is_manual' => false,
                ]);
            } catch (QueryException $e) {
                // Pull and push can race on the exact same punch (a scheduled
                // pull overlapping a manual "Sync now", or push resending a
                // batch the device hasn't seen acknowledged yet) — the loser
                // hits attendances_device_punch_unique. That's fine: the row
                // already exists, nothing left to do for this line.
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                continue;
            }

            if ($attendance->wasRecentlyCreated) {
                $imported++;
                event(new AttendanceRecorded($attendance));
            }
        }

        return ['imported' => $imported, 'unmatched' => $unmatched];
    }

    /**
     * Track a failed pull() attempt and fire AttendanceDeviceSyncFailed once
     * it looks like more than a one-off blip — see config('attendance.device_sync')
     * for the escalation thresholds. Listen for the event to notify however
     * your app does notifications; the package has no opinion on that.
     */
    protected function recordSyncFailure(AttendanceDevice $device, string $reason): void
    {
        $device->increment('sync_fail_count');
        $count = $device->sync_fail_count;

        $after = config('attendance.device_sync.notify_after_failures', 2);
        $every = config('attendance.device_sync.notify_every', 5);

        $shouldNotify = $count === $after || ($count > $after && $every > 0 && ($count - $after) % $every === 0);

        if ($shouldNotify) {
            event(new AttendanceDeviceSyncFailed($device, $reason, $count));
        }
    }
}
