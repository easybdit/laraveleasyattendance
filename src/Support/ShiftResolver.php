<?php

namespace Easybdit\LaravelEasyAttendance\Support;

use Carbon\Carbon;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\EmployeeShift;

/**
 * Resolves which shift (working hours + off days) applies to an employee
 * on a given date — from their roster if one covers that date, otherwise
 * config('attendance.default_shift') so summaries/salary work immediately,
 * before anyone has set up a single shift assignment.
 */
class ShiftResolver
{
    /**
     * @return array{shift_id: ?int, start_time: string, end_time: string, late_grace_minutes: int, is_off_day: bool, source: string}
     */
    public function resolve(Employee $employee, string $date): array
    {
        $assignment = EmployeeShift::with('shift')
            ->where('employee_id', $employee->id)
            ->covering($date)
            ->latest('start_date')
            ->first();

        if ($assignment && $assignment->shift) {
            $shift = $assignment->shift;

            return [
                'shift_id' => $shift->id,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'late_grace_minutes' => $shift->late_grace_minutes,
                'is_off_day' => $shift->isOffDay(Carbon::parse($date)),
                'source' => 'roster',
            ];
        }

        $default = config('attendance.default_shift');
        $weekOffDay = $default['week_off_day'] ?? null;

        return [
            'shift_id' => null,
            'start_time' => $default['start_time'],
            'end_time' => $default['end_time'],
            'late_grace_minutes' => $default['late_grace_minutes'],
            'is_off_day' => $weekOffDay !== null && Carbon::parse($date)->format('l') === $weekOffDay,
            'source' => 'default',
        ];
    }
}
