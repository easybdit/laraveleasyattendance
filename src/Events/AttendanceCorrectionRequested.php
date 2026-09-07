<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceCorrectionRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public AttendanceCorrection $correction)
    {
    }
}
