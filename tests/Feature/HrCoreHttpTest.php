<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\Shift;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;

/**
 * These management endpoints all take an explicit {employee} — they're
 * HR/admin actions behind review_middleware, not "my own" self-service
 * (see LeaveController's docblock for why). Any authenticated subject can
 * call them here; your app supplies the real admin gate via
 * config('attendance.routes.review_middleware').
 */
class HrCoreHttpTest extends TestCase
{
    private function actor(): User
    {
        return User::create(['name' => 'Admin']);
    }

    public function test_employee_crud_over_http(): void
    {
        $admin = $this->actor();

        $created = $this->actingAs($admin)->postJson('/attendance/employees', [
            'employee_code' => 'E-500', 'name' => 'Rafiq', 'basic_salary' => 20000, 'status' => 'active',
        ])->assertCreated()->json();

        $this->actingAs($admin)->putJson("/attendance/employees/{$created['id']}", [
            'employee_code' => 'E-500', 'name' => 'Rafiq Islam', 'basic_salary' => 22000, 'status' => 'active',
        ])->assertOk()->assertJsonFragment(['name' => 'Rafiq Islam']);

        $this->actingAs($admin)->getJson("/attendance/employees/{$created['id']}")->assertOk();

        $this->actingAs($admin)->deleteJson("/attendance/employees/{$created['id']}")->assertOk();
        $this->assertNull(Employee::find($created['id']));
    }

    public function test_shift_crud_over_http(): void
    {
        $admin = $this->actor();

        $created = $this->actingAs($admin)->postJson('/attendance/shifts', [
            'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'late_grace_minutes' => 5, 'off_days' => ['Friday'],
        ])->assertCreated()->json();

        $this->actingAs($admin)->putJson("/attendance/shifts/{$created['id']}", [
            'name' => 'Night Shift', 'start_time' => '22:00', 'end_time' => '06:00',
        ])->assertOk()->assertJsonFragment(['name' => 'Night Shift']);

        $this->actingAs($admin)->deleteJson("/attendance/shifts/{$created['id']}")->assertOk();
        $this->assertNull(Shift::find($created['id']));
    }

    public function test_assigning_and_listing_a_schedule_over_http(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-501', 'name' => 'Shafiq', 'basic_salary' => 20000, 'status' => 'active']);
        $shift = Shift::create(['name' => 'General', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'off_days' => ['Friday']]);

        $this->actingAs($admin)->postJson("/attendance/employees/{$employee->id}/schedule", [
            'shift_id' => $shift->id, 'start_date' => '2026-11-01',
        ])->assertCreated();

        $this->actingAs($admin)->getJson("/attendance/employees/{$employee->id}/schedule")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_requesting_listing_and_approving_a_leave_over_http(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-502', 'name' => 'Nasrin', 'basic_salary' => 20000, 'status' => 'active']);

        $leave = $this->actingAs($admin)->postJson("/attendance/employees/{$employee->id}/leaves", [
            'start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'reason' => 'personal',
        ])->assertCreated()->json();

        $this->actingAs($admin)->getJson("/attendance/employees/{$employee->id}/leaves")
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($admin)->postJson("/attendance/leaves/{$leave['id']}/approve", ['note' => 'ok'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'approved']);
    }
}
