<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Events\OvertimeReviewed;
use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property ?string $shift_end_time
 * @property ?string $actual_out_time
 * @property string $ot_hours
 * @property string $ot_rate
 * @property string $ot_amount
 * @property ?string $source
 * @property string $status
 * @property ?int $approved_by
 * @property ?Carbon $approved_at
 * @property ?string $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 */
class OvertimeRecord extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'overtime_records';
    }

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

    public function employee(): BelongsTo
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

        // whereDate(), not where() — see Holiday::onDate()'s comment for
        // why an exact-string comparison against a `date`-cast column
        // (like $summary->date, itself a Carbon instance here) is
        // MySQL-only. whereDate() is safe on any driver, and accepts a
        // Carbon instance directly.
        $existing = static::where('employee_id', $employee->id)->whereDate('date', $summary->date)->first();
        if ($existing && ! ($existing->status === 'pending' && $existing->source === 'auto')) {
            return $existing;
        }

        $maxHours = (float) config('attendance.overtime.max_hours_per_day', 2);
        $otHours = round(min($summary->ot_minutes / 60, $maxHours), 2);
        $otRate = static::hourlyRate($employee);

        $attributes = [
            'shift_end_time' => $summary->shift_end,
            'actual_out_time' => $summary->last_out?->format('H:i:s'),
            'ot_hours' => $otHours,
            'ot_rate' => $otRate,
            'ot_amount' => round($otHours * $otRate, 2),
            'source' => 'auto',
            'status' => 'pending',
        ];

        // Reuse $existing (same row detectFromSummary would otherwise
        // have to re-look-up via updateOrCreate's own exact-match query,
        // which is exactly the lookup that's unreliable across drivers).
        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return static::create(array_merge(['employee_id' => $employee->id, 'date' => $summary->date], $attributes));
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
