<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One pre-computed daily status row per employee — present/late/absent/
 * leave/holiday/day_off. Built by AttendanceSummaryService from the raw
 * Attendance punch log plus Shift/Leave/Holiday; rebuild after any sync
 * or manual punch that touches the date, and during salary generation.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property ?int $shift_id
 * @property string $status
 * @property ?Carbon $first_in
 * @property ?Carbon $last_out
 * @property ?int $punch_count
 * @property ?int $late_minutes
 * @property ?int $ot_minutes
 * @property bool $is_holiday
 * @property bool $is_day_off
 * @property bool $is_on_leave
 * @property ?string $holiday_name
 * @property ?string $shift_start
 * @property ?string $shift_end
 * @property ?string $shift_source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?float $work_hours
 * @property-read Employee $employee
 * @property-read ?Shift $shift
 */
class AttendanceSummary extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'attendance_summaries';
    }

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

    public function employee(): BelongsTo
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
