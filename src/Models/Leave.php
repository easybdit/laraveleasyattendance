<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Events\LeaveReviewed;
use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $reason
 * @property string $status
 * @property ?int $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property ?string $review_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
class Leave extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'leaves';
    }

    protected $fillable = [
        'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Inclusive day count (start and end both count) — whole days only,
     * no half-day support yet.
     */
    public function daysCount(): int
    {
        // Carbon's diffInDays() is typed to return float (it supports
        // sub-day precision on datetimes); start_date/end_date are
        // date-only casts, so the result is always a whole number here —
        // the cast just makes that already-true fact match the return type.
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Is $date covered by an APPROVED leave for this employee — the check
     * AttendanceSummaryService uses when deciding a day's status.
     */
    public static function covers(int $employeeId, string $date): bool
    {
        // whereDate(), not where() — see Holiday::onDate()'s comment for
        // why an exact-string comparison against a `date`-cast column is
        // MySQL-only. whereDate() is safe on any driver.
        return static::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    public function approve(?int $reviewerId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        event(new LeaveReviewed($this));
    }

    public function reject(?int $reviewerId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        event(new LeaveReviewed($this));
    }
}
