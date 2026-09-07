<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\Leave;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public Leave $leave)
    {
    }
}
