<?php

namespace Easybdit\LaravelEasyAttendance\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Easybdit\LaravelEasyAttendance\Events\AttendanceMarkedLate;
use Easybdit\LaravelEasyAttendance\Models\Attendance;
use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\Holiday;
use Easybdit\LaravelEasyAttendance\Models\Leave;
use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Easybdit\LaravelEasyAttendance\Support\ShiftResolver;

/**
 * Builds/rebuilds daily per-employee attendance_summaries rows from the
 * raw punch log plus Shift/Leave/Holiday. Call buildForDate() after any
 * device sync or manual punch; buildForMonth() before salary generation.
 */
class AttendanceSummaryService
{
    public function buildForDate(string $date): int
    {
        $built = 0;

        // Resolved once, not once per employee — Holiday::onDate($date)
        // returns the same row for every employee on a given date, so
        // re-querying it inside the loop below would be a pure duplicate
        // query per employee for no reason (real cost on a large roster).
        $holiday = Holiday::onDate($date);

        Employee::where('status', 'active')->each(function (Employee $employee) use ($date, $holiday, &$built) {
            $this->buildOne($employee, $date, $holiday);
            $built++;
        });

        return $built;
    }

    public function buildForMonth(Employee $employee, int $year, int $month): void
    {
        $start = Carbon::create($year, $month, 1)->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $this->buildOne($employee, $date->toDateString());
        }
    }

    /**
     * $holiday: pass the already-resolved Holiday (or null) when the
     * caller already knows it for this $date — e.g. buildForDate() looping
     * many employees over the same date — to skip a redundant lookup.
     * `false` (the default) means "not supplied, resolve it here".
     */
    public function buildOne(Employee $employee, string $date, Holiday|false|null $holiday = false): AttendanceSummary
    {
        $shiftInfo = (new ShiftResolver)->resolve($employee, $date);
        $holiday = $holiday === false ? Holiday::onDate($date) : $holiday;
        $onLeave = Leave::covers($employee->id, $date);

        $punches = $employee->attendances()->whereDate('time', $date)->orderBy('time')->get();
        $window = Attendance::resolveDayWindow($punches);
        $firstIn = $window['first_in'];
        $lastOut = $window['last_out'];
        $hasAttendance = $punches->isNotEmpty();

        $status = $this->resolveStatus($shiftInfo['is_off_day'], $holiday, $onLeave, $hasAttendance, $firstIn, $date, $shiftInfo);

        $lateMinutes = 0;
        if ($hasAttendance && $firstIn && in_array($status, ['present', 'late'], true)) {
            $shiftStart = Carbon::parse($date.' '.$shiftInfo['start_time']);
            $graceEnd = $shiftStart->copy()->addMinutes($shiftInfo['late_grace_minutes']);
            if ($firstIn->gt($graceEnd)) {
                $lateMinutes = (int) round($firstIn->diffInMinutes($shiftStart, true));
            }
        }

        $otMinutes = 0;
        if ($hasAttendance && $lastOut) {
            $shiftEnd = Carbon::parse($date.' '.$shiftInfo['end_time']);
            if ($lastOut->gt($shiftEnd)) {
                $otMinutes = (int) round($lastOut->diffInMinutes($shiftEnd, true));
            }
        }

        // whereDate(), and a manual find-then-save instead of
        // updateOrCreate() — see Holiday::onDate()'s comment. updateOrCreate()
        // would look up the existing row via an exact-string match on the
        // `date`-cast column, which only reliably finds it on MySQL; on
        // SQLite it wouldn't find the row this same method previously
        // wrote (same corrupted-format quirk) and would attempt a second
        // INSERT, colliding with the (employee_id, date) unique index.
        $existing = AttendanceSummary::where('employee_id', $employee->id)->whereDate('date', $date)->first();
        $wasAlreadyLate = $existing?->status === 'late';

        $attributes = [
            'employee_id' => $employee->id,
            'date' => $date,
            'shift_id' => $shiftInfo['shift_id'],
            'status' => $status,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'punch_count' => $punches->count(),
            'late_minutes' => $lateMinutes,
            'ot_minutes' => $otMinutes,
            'is_holiday' => (bool) $holiday,
            'is_day_off' => $shiftInfo['is_off_day'],
            'is_on_leave' => $onLeave,
            'holiday_name' => $holiday?->name,
            'shift_start' => $shiftInfo['start_time'],
            'shift_end' => $shiftInfo['end_time'],
            'shift_source' => $shiftInfo['source'],
        ];

        if ($existing) {
            $existing->update($attributes);
            $summary = $existing;
        } else {
            $summary = AttendanceSummary::create($attributes);
        }

        if ($status === 'late' && ! $wasAlreadyLate) {
            event(new AttendanceMarkedLate($employee, $date, $lateMinutes));
        }

        if (config('attendance.features.overtime', false) && config('attendance.overtime.auto_detect', true)) {
            OvertimeRecord::detectFromSummary($employee, $summary);
        }

        return $summary;
    }

    private function resolveStatus(
        bool $isDayOff,
        ?Holiday $holiday,
        bool $onLeave,
        bool $hasAttendance,
        ?Carbon $firstIn,
        string $date,
        array $shiftInfo
    ): string {
        if ($onLeave) {
            return 'leave';
        }
        if ($holiday && ! $hasAttendance) {
            return 'holiday';
        }
        if ($isDayOff && ! $hasAttendance) {
            return 'day_off';
        }
        if (! $hasAttendance) {
            return 'absent';
        }

        if ($firstIn) {
            $shiftStart = Carbon::parse($date.' '.$shiftInfo['start_time']);
            $graceEnd = $shiftStart->copy()->addMinutes($shiftInfo['late_grace_minutes']);
            if ($firstIn->gt($graceEnd)) {
                return 'late';
            }
        }

        return 'present';
    }
}
