<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a correction is approved or rejected — check
 * $correction->status to tell which. Listen for this to notify the
 * requester, sync the approved times back into `attendances`, etc.
 */
class AttendanceCorrectionReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public AttendanceCorrection $correction) {}
}
