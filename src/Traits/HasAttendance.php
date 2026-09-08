<?php

namespace Easybdit\LaravelEasyAttendance\Traits;

use Easybdit\LaravelEasyAttendance\Events\AttendanceCorrectionRequested;
use Easybdit\LaravelEasyAttendance\Events\AttendanceRecorded;
use Easybdit\LaravelEasyAttendance\Models\Attendance;
use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add to your subject model (e.g. App\Models\User or App\Models\Employee)
 * to get attendance relations + a couple of convenience helpers.
 */
trait HasAttendance
{
    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'subject');
    }

    public function attendanceCorrections(): MorphMany
    {
        return $this->morphMany(AttendanceCorrection::class, 'subject');
    }

    public function checkIn(array $attributes = []): Attendance
    {
        return $this->recordPunch('check_in', $attributes);
    }

    public function checkOut(array $attributes = []): Attendance
    {
        return $this->recordPunch('check_out', $attributes);
    }

    /**
     * $attributes is filtered to a small safe whitelist before it ever
     * reaches the merge below — this is a public API a downstream app's
     * own controller might naively forward $request->all() into, and
     * 'type'/'source'/'is_manual' left open to override would let a
     * caller quietly mislabel a manual punch as a device one (or the
     * reverse). Add a key here only if it's meant to be caller-set.
     */
    private const PUNCH_ATTRIBUTE_ALLOWLIST = ['time', 'meta'];

    protected function recordPunch(string $type, array $attributes = []): Attendance
    {
        $attributes = array_intersect_key($attributes, array_flip(self::PUNCH_ATTRIBUTE_ALLOWLIST));

        $attendance = $this->attendances()->create(array_merge([
            'time' => now(),
            'type' => $type,
            'source' => 'manual',
            'is_manual' => true,
        ], $attributes));

        event(new AttendanceRecorded($attendance));

        return $attendance;
    }

    /**
     * Submit a correction request for a given date, pending review.
     * Fires AttendanceCorrectionRequested — listen for that to notify
     * whoever reviews corrections in your app.
     */
    public function requestAttendanceCorrection(array $attributes): AttendanceCorrection
    {
        $correction = $this->attendanceCorrections()->create(array_merge([
            'status' => 'pending',
        ], $attributes));

        event(new AttendanceCorrectionRequested($correction));

        return $correction;
    }

    public function attendanceOn(string $date): array
    {
        $punches = $this->attendances()->whereDate('time', $date)->orderBy('time')->get();

        return Attendance::resolveDayWindow($punches);
    }
}
