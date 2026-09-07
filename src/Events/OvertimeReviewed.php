<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an overtime record is approved or rejected — check
 * $record->status to tell which. Only approved OT reaches a salary slip.
 */
class OvertimeReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public OvertimeRecord $record)
    {
    }
}
