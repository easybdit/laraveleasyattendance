<?php

namespace Easybdit\LaravelEasyAttendance\Events;

use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on the Nth consecutive sync failure for a device, then again every
 * M failures after that (see config('attendance.device_sync')) — not on
 * every single failure, so a temporary blip doesn't spam whoever listens.
 */
class AttendanceDeviceSyncFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AttendanceDevice $device,
        public string $reason,
        public int $consecutiveFailures,
    ) {}
}
