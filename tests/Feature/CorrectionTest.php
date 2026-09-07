<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Events\AttendanceCorrectionRequested;
use Easybdit\LaravelEasyAttendance\Events\AttendanceCorrectionReviewed;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CorrectionTest extends TestCase
{
    public function test_requesting_a_correction_creates_a_pending_row_and_fires_an_event(): void
    {
        Event::fake([AttendanceCorrectionRequested::class]);

        $user = User::create(['name' => 'Dana']);

        $correction = $user->requestAttendanceCorrection([
            'date' => '2026-09-07',
            'requested_in' => '09:05',
            'requested_out' => '18:10',
            'reason' => 'forgot to punch',
        ]);

        $this->assertSame('pending', $correction->status);
        $this->assertSame(0, $user->attendances()->count());

        Event::assertDispatched(AttendanceCorrectionRequested::class);
    }

    public function test_approving_a_correction_creates_real_punches_that_resolve_correctly(): void
    {
        Event::fake([AttendanceCorrectionReviewed::class]);

        $user = User::create(['name' => 'Eve']);

        $correction = $user->requestAttendanceCorrection([
            'date' => '2026-09-07',
            'requested_in' => '09:05',
            'requested_out' => '18:10',
            'reason' => 'forgot to punch',
        ]);

        $correction->approve(99, 'looks fine');

        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame(99, $correction->fresh()->reviewed_by);

        $rows = $user->attendances()->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r->source === 'correction' && $r->is_manual));

        $window = $user->attendanceOn('2026-09-07');
        $this->assertSame('2026-09-07 09:05:00', $window['first_in']->toDateTimeString());
        $this->assertSame('2026-09-07 18:10:00', $window['last_out']->toDateTimeString());

        Event::assertDispatched(AttendanceCorrectionReviewed::class, fn ($e) => $e->correction->status === 'approved');
    }

    public function test_rejecting_a_correction_creates_no_punches(): void
    {
        $user = User::create(['name' => 'Frank']);

        $correction = $user->requestAttendanceCorrection([
            'date' => '2026-09-07',
            'requested_in' => '09:05',
            'reason' => 'forgot to punch',
        ]);

        $correction->reject(99, 'not plausible');

        $this->assertSame('rejected', $correction->fresh()->status);
        $this->assertSame(0, $user->attendances()->count());
    }

    public function test_an_approved_correction_overrides_a_stray_device_punch(): void
    {
        $user = User::create(['name' => 'Grace']);

        $user->attendances()->create([
            'time' => '2026-09-07 06:00:00',
            'type' => 'check_in',
            'source' => 'device',
            'is_manual' => false,
        ]);

        $correction = $user->requestAttendanceCorrection([
            'date' => '2026-09-07',
            'requested_in' => '09:05',
            'reason' => 'device punch was a fluke',
        ]);
        $correction->approve();

        $window = $user->attendanceOn('2026-09-07');
        $this->assertSame('2026-09-07 09:05:00', $window['first_in']->toDateTimeString());
    }
}
