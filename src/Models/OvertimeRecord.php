<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Events\OvertimeReviewed;
use Illuminate\Database\Eloquent\Model;

class OvertimeRecord extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'shift_end_time', 'actual_out_time',
        'ot_hours', 'ot_rate', 'ot_amount', 'source', 'status',
        'approved_by', 'approved_at', 'note',
    ];

    protected $casts = [
        'date' => 'date',
        'ot_hours' => 'decimal:2',
        'ot_rate' => 'decimal:4',
        'ot_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    /**
     * hourly_rate = basic_salary / (salary_divisor × 8); OT pays
     * rate_multiplier × that. See config('attendance.overtime').
     */
    public static function hourlyRate(Employee $employee): float
    {
        $divisor = max(1, (int) config('attendance.overtime.salary_divisor', 26));
        $multiplier = (float) config('attendance.overtime.rate_multiplier', 2);

        return round(((float) $employee->basic_salary / ($divisor * 8)) * $multiplier, 4);
    }

    /**
     * Auto-detect OT from a day's already-built AttendanceSummary
     * (its ot_minutes — last_out past the shift's end_time) and land it
     * as a 'pending' record. Never overwrites a record someone has
     * already reviewed, or one entered manually — only ever touches its
     * own untouched auto-detected rows, so rebuilding a summary can't
     * silently reopen or reshape OT a manager already approved/rejected.
     */
    public static function detectFromSummary(Employee $employee, AttendanceSummary $summary): ?self
    {
        if ($summary->ot_minutes <= 0) {
            return null;
        }

        $existing = static::where('employee_id', $employee->id)->where('date', $summary->date)->first();
        if ($existing && ! ($existing->status === 'pending' && $existing->source === 'auto')) {
            return $existing;
        }

        $maxHours = (float) config('attendance.overtime.max_hours_per_day', 2);
        $otHours = round(min($summary->ot_minutes / 60, $maxHours), 2);
        $otRate = static::hourlyRate($employee);

        return static::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $summary->date],
            [
                'shift_end_time' => $summary->shift_end,
                'actual_out_time' => $summary->last_out?->format('H:i:s'),
                'ot_hours' => $otHours,
                'ot_rate' => $otRate,
                'ot_amount' => round($otHours * $otRate, 2),
                'source' => 'auto',
                'status' => 'pending',
            ]
        );
    }

    public function approve(?int $reviewerId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $reviewerId,
            'approved_at' => now(),
            'note' => $note ?? $this->note,
        ]);

        event(new OvertimeReviewed($this));
    }

    public function reject(?int $reviewerId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $reviewerId,
            'approved_at' => now(),
            'note' => $note ?? $this->note,
        ]);

        event(new OvertimeReviewed($this));
    }
}
