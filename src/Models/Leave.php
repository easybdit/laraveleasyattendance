<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Events\LeaveReviewed;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
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
     * Is $date covered by an APPROVED leave for this employee — the check
     * AttendanceSummaryService uses when deciding a day's status.
     */
    public static function covers(int $employeeId, string $date): bool
    {
        return static::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
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
