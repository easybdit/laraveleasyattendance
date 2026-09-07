<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Events\AttendanceRecorded;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CheckInCheckOutTest extends TestCase
{
    public function test_check_in_and_check_out_create_punches_and_fire_events(): void
    {
        Event::fake([AttendanceRecorded::class]);

        $user = User::create(['name' => 'Alice']);

        $in = $user->checkIn();
        $out = $user->checkOut();

        $this->assertSame('check_in', $in->type);
        $this->assertSame('check_out', $out->type);
        $this->assertTrue($in->is_manual);
        $this->assertSame(User::class, $in->subject_type);
        $this->assertSame($user->id, $in->subject_id);
        $this->assertCount(2, $user->attendances()->get());

        Event::assertDispatchedTimes(AttendanceRecorded::class, 2);
    }

    public function test_attendance_on_resolves_earliest_check_in_and_latest_check_out(): void
    {
        $user = User::create(['name' => 'Bob']);

        $user->checkIn(['time' => '2026-09-07 09:15:00']);
        $user->checkIn(['time' => '2026-09-07 09:20:00']); // stray double punch
        $user->checkOut(['time' => '2026-09-07 17:00:00']);
        $user->checkOut(['time' => '2026-09-07 18:30:00']); // latest wins

        $window = $user->attendanceOn('2026-09-07');

        $this->assertSame('2026-09-07 09:15:00', $window['first_in']->toDateTimeString());
        $this->assertSame('2026-09-07 18:30:00', $window['last_out']->toDateTimeString());
    }

    public function test_manual_punch_overrides_a_stray_device_punch_for_the_same_direction(): void
    {
        $user = User::create(['name' => 'Carol']);

        // A bad early device punch, then an admin's correct manual one —
        // the manual entry must win as "first in", not the earlier device one.
        $user->checkIn(['time' => '2026-09-07 06:00:00', 'source' => 'device', 'is_manual' => false]);
        $user->checkIn(['time' => '2026-09-07 09:05:00', 'source' => 'manual', 'is_manual' => true]);

        $window = $user->attendanceOn('2026-09-07');

        $this->assertSame('2026-09-07 09:05:00', $window['first_in']->toDateTimeString());
    }
}
