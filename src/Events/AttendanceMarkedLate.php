<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a day's summary is (re)built as 'late' and wasn't already
 * — a rebuild of an already-late day doesn't re-fire this. Listen to
 * notify however your app does notifications; the package sends none.
 */
class AttendanceMarkedLate
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $date,
        public int $lateMinutes,
    ) {}
}
