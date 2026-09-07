<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Events\AttendanceDeviceSyncFailed;
use Easybdit\LaravelEasyAttendance\Events\AttendanceRecorded;
use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Easybdit\LaravelEasyAttendance\Services\AttendanceDeviceSyncService;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class DeviceSyncTest extends TestCase
{
    public function test_ingest_push_matches_a_known_pin_and_skips_an_unknown_one(): void
    {
        Event::fake([AttendanceRecorded::class]);

        $user = User::create(['name' => 'Heidi', 'device_user_id' => '1001']);
        $device = AttendanceDevice::create(['name' => 'Gate', 'serial_number' => 'SN-TEST-1', 'status' => 'active']);

        $lines = [
            "1001\t2026-09-07 09:00:00\t0",  // matches Heidi -> check_in
            "9999\t2026-09-07 09:05:00\t0",  // no such PIN -> skipped, not dropped silently
        ];

        $imported = (new AttendanceDeviceSyncService)->ingestPush($device, $lines);

        $this->assertSame(1, $imported);
        $this->assertSame(1, $user->attendances()->count());

        $row = $user->attendances()->first();
        $this->assertSame('check_in', $row->type);
        $this->assertSame('device', $row->source);
        $this->assertSame($device->id, $row->device_id);
        $this->assertSame('1001', $row->device_user_id);

        Event::assertDispatchedTimes(AttendanceRecorded::class, 1);
    }

    public function test_ingest_push_is_idempotent_against_the_same_line_twice(): void
    {
        $user = User::create(['name' => 'Ivan', 'device_user_id' => '2002']);
        $device = AttendanceDevice::create(['name' => 'Gate', 'serial_number' => 'SN-TEST-2', 'status' => 'active']);

        $line = "2002\t2026-09-07 09:00:00\t0";
        $sync = new AttendanceDeviceSyncService;

        $first = $sync->ingestPush($device, [$line]);
        $second = $sync->ingestPush($device, [$line]); // device/network resent the same batch

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, $user->attendances()->count());
    }

    public function test_check_out_state_is_recognised(): void
    {
        $user = User::create(['name' => 'Judy', 'device_user_id' => '3003']);
        $device = AttendanceDevice::create(['name' => 'Gate', 'serial_number' => 'SN-TEST-3', 'status' => 'active']);

        (new AttendanceDeviceSyncService)->ingestPush($device, ["3003\t2026-09-07 18:00:00\t1"]);

        $this->assertSame('check_out', $user->attendances()->first()->type);
    }

    public function test_pull_fails_cleanly_and_escalates_after_repeated_failures(): void
    {
        // coding-libs/zkteco-php is intentionally NOT a require-dev of this
        // package (it's suggest-only — push mode needs nothing extra), so
        // ZKService's guard deterministically fails every pull() call here.
        // That happens to make this the exact same code path a genuinely
        // unreachable device takes, which is what we're really testing:
        // no fatal error, sync_fail_count climbs, and the failure event
        // fires on the configured schedule (2nd failure, then every 5th).
        Event::fake([AttendanceDeviceSyncFailed::class]);

        $device = AttendanceDevice::create(['name' => 'Unreachable', 'ip' => '10.255.255.1', 'status' => 'active']);
        $sync = new AttendanceDeviceSyncService;

        foreach (range(1, 3) as $i) {
            $result = $sync->pull($device);
            $this->assertFalse($result['success']);
        }

        $this->assertSame(3, $device->fresh()->sync_fail_count);

        Event::assertDispatchedTimes(AttendanceDeviceSyncFailed::class, 1);
        Event::assertDispatched(AttendanceDeviceSyncFailed::class, fn ($e) => $e->consecutiveFailures === 2);
    }

    public function test_is_online_reflects_recent_heartbeat(): void
    {
        $fresh = AttendanceDevice::create(['name' => 'A', 'serial_number' => 'SN-A', 'last_seen_at' => now()]);
        $stale = AttendanceDevice::create(['name' => 'B', 'serial_number' => 'SN-B', 'last_seen_at' => now()->subMinutes(10)]);
        $never = AttendanceDevice::create(['name' => 'C', 'serial_number' => 'SN-C']);

        $this->assertTrue($fresh->is_online);
        $this->assertFalse($stale->is_online);
        $this->assertFalse($never->is_online);
    }
}
