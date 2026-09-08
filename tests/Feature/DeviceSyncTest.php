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
        // A genuinely unreachable IP — the socket recv simply times out.
        // Config'd to a short timeout (default is 15s) so this test doesn't
        // take 45+ seconds; what's actually under test is that a real
        // connect failure doesn't fatal, sync_fail_count climbs, and the
        // failure event fires on the configured schedule (2nd failure,
        // then every 5th), not the timeout duration itself.
        config(['attendance.device_sync.pull_timeout_seconds' => 1]);

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

    public function test_ingest_push_caps_lines_per_batch(): void
    {
        config(['attendance.device_sync.adms_max_lines_per_push' => 2]);

        $user = User::create(['name' => 'Trent', 'device_user_id' => '5005']);
        $device = AttendanceDevice::create(['name' => 'Gate', 'serial_number' => 'SN-TEST-5', 'status' => 'active']);

        $lines = [
            "5005\t2026-09-07 09:00:00\t0",
            "5005\t2026-09-07 09:05:00\t0",
            "5005\t2026-09-07 09:10:00\t0", // beyond the cap — dropped, not processed
        ];

        $imported = (new AttendanceDeviceSyncService)->ingestPush($device, $lines);

        $this->assertSame(2, $imported);
        $this->assertSame(2, $user->attendances()->count());
        $this->assertNull($user->attendances()->whereDate('time', '2026-09-07')->where('time', '2026-09-07 09:10:00')->first());
    }

    public function test_adms_push_over_http_is_rejected_when_source_ip_does_not_match_the_registered_device(): void
    {
        config(['attendance.device_sync.adms_verify_ip' => true]);

        $user = User::create(['name' => 'Mallory', 'device_user_id' => '4004']);
        $device = AttendanceDevice::create(['name' => 'Gate', 'serial_number' => 'SN-TEST-6', 'ip' => '203.0.113.9', 'status' => 'active']);

        $response = $this->call('POST', '/iclock/cdata?SN=SN-TEST-6&table=ATTLOG', [], [], [], [], "4004\t2026-09-07 09:00:00\t0");

        $response->assertOk();
        $this->assertSame('ERROR', $response->getContent());
        $this->assertSame(0, $user->attendances()->count());
    }

    public function test_adms_push_over_http_succeeds_when_source_ip_matches_the_registered_device(): void
    {
        config(['attendance.device_sync.adms_verify_ip' => true]);

        $user = User::create(['name' => 'Oscar', 'device_user_id' => '6006']);
        // The test client's default request IP is 127.0.0.1.
        $device = AttendanceDevice::create(['name' => 'Gate', 'serial_number' => 'SN-TEST-7', 'ip' => '127.0.0.1', 'status' => 'active']);

        $response = $this->call('POST', '/iclock/cdata?SN=SN-TEST-7&table=ATTLOG', [], [], [], [], "6006\t2026-09-07 09:00:00\t0");

        $response->assertOk();
        $this->assertSame('OK: 1', $response->getContent());
        $this->assertSame(1, $user->attendances()->count());
    }
}
