<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\Leave;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a leave request is approved or rejected — check
 * $leave->status to tell which.
 */
class LeaveReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Leave $leave) {}
}
