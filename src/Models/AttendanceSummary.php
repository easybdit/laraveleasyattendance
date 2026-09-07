<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One pre-computed daily status row per employee — present/late/absent/
 * leave/holiday/day_off. Built by AttendanceSummaryService from the raw
 * Attendance punch log plus Shift/Leave/Holiday; rebuild after any sync
 * or manual punch that touches the date, and during salary generation.
 */
class AttendanceSummary extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'shift_id', 'status',
        'first_in', 'last_out', 'punch_count',
        'late_minutes', 'ot_minutes',
        'is_holiday', 'is_day_off', 'is_on_leave', 'holiday_name',
        'shift_start', 'shift_end', 'shift_source',
    ];

    protected $casts = [
        'date' => 'date',
        'first_in' => 'datetime',
        'last_out' => 'datetime',
        'is_holiday' => 'boolean',
        'is_day_off' => 'boolean',
        'is_on_leave' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function getWorkHoursAttribute(): ?float
    {
        if (! $this->first_in || ! $this->last_out) {
            return null;
        }

        return round($this->first_in->diffInMinutes($this->last_out) / 60, 2);
    }
}
