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

    protected function recordPunch(string $type, array $attributes = []): Attendance
    {
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
