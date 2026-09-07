<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;

class HttpRoutesTest extends TestCase
{
    public function test_authenticated_user_can_check_in_and_out_over_http(): void
    {
        $user = User::create(['name' => 'Karl']);

        $this->actingAs($user)->postJson('/attendance/check-in')->assertCreated();
        $this->actingAs($user)->postJson('/attendance/check-out')->assertCreated();

        $this->assertSame(2, $user->attendances()->count());
    }

    public function test_guest_is_redirected_away_from_check_in(): void
    {
        $this->post('/attendance/check-in')->assertRedirect();
    }

    public function test_authenticated_user_can_submit_and_list_corrections_over_http(): void
    {
        $user = User::create(['name' => 'Liam']);

        $this->actingAs($user)->postJson('/attendance/corrections', [
            'date' => '2026-09-07',
            'requested_in' => '09:05',
            'reason' => 'forgot to punch',
        ])->assertCreated();

        $this->actingAs($user)->getJson('/attendance/corrections')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
