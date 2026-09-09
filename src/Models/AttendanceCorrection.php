<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Events\AttendanceCorrectionReviewed;
use Easybdit\LaravelEasyAttendance\Events\AttendanceRecorded;
use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject_type
 * @property int|string $subject_id
 * @property ?int $submitted_by
 * @property Carbon $date
 * @property ?string $requested_in
 * @property ?string $requested_out
 * @property string $reason
 * @property string $status
 * @property ?int $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property ?string $review_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $subject
 */
class AttendanceCorrection extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'attendance_corrections';
    }

    protected $fillable = [
        'subject_type', 'subject_id', 'submitted_by',
        'date', 'requested_in', 'requested_out', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function approve(?int $reviewerId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        // Turn the approved request into real attendance punches so it
        // flows through the same resolveDayWindow() logic as any other
        // punch, instead of living only as a "note" on the side.
        if ($this->requested_in) {
            event(new AttendanceRecorded($this->subject->attendances()->create([
                'time' => $this->date->toDateString().' '.$this->requested_in,
                'type' => 'check_in',
                'source' => 'correction',
                'is_manual' => true,
            ])));
        }

        if ($this->requested_out) {
            event(new AttendanceRecorded($this->subject->attendances()->create([
                'time' => $this->date->toDateString().' '.$this->requested_out,
                'type' => 'check_out',
                'source' => 'correction',
                'is_manual' => true,
            ])));
        }

        event(new AttendanceCorrectionReviewed($this));
    }

    public function reject(?int $reviewerId = null, ?string $note = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        event(new AttendanceCorrectionReviewed($this));
    }
}
