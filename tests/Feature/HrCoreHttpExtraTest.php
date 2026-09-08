<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\EmployeeShift;
use Easybdit\LaravelEasyAttendance\Models\Holiday;
use Easybdit\LaravelEasyAttendance\Models\Shift;
use Easybdit\LaravelEasyAttendance\Services\AttendanceSummaryService;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;

class HrCoreHttpExtraTest extends TestCase
{
    private function actor(): User
    {
        return User::create(['name' => 'Admin']);
    }

    public function test_holiday_crud_over_http(): void
    {
        $admin = $this->actor();

        $created = $this->actingAs($admin)->postJson('/attendance/holidays', [
            'name' => 'Test Holiday', 'date' => '2026-12-25',
        ])->assertCreated()->json();

        $this->actingAs($admin)->putJson("/attendance/holidays/{$created['id']}", [
            'name' => 'Christmas', 'date' => '2026-12-25',
        ])->assertOk()->assertJsonFragment(['name' => 'Christmas']);

        $this->actingAs($admin)->getJson('/attendance/holidays')->assertOk()->assertJsonCount(1);

        $this->actingAs($admin)->deleteJson("/attendance/holidays/{$created['id']}")->assertOk();
        $this->assertNull(Holiday::find($created['id']));
    }

    public function test_leave_type_crud_over_http(): void
    {
        $admin = $this->actor();

        $created = $this->actingAs($admin)->postJson('/attendance/leave-types', [
            'name' => 'Sick Leave', 'days_allowed_per_year' => 14,
        ])->assertCreated()->json();

        $this->actingAs($admin)->putJson("/attendance/leave-types/{$created['id']}", [
            'name' => 'Sick Leave', 'days_allowed_per_year' => 20,
        ])->assertOk()->assertJsonFragment(['days_allowed_per_year' => 20]);

        $this->actingAs($admin)->deleteJson("/attendance/leave-types/{$created['id']}")->assertOk();
    }

    public function test_overtime_index_and_approve_over_http(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-600', 'name' => 'Tanvir', 'basic_salary' => 26000, 'status' => 'active']);
        $shift = Shift::create(['name' => 'General', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'off_days' => ['Friday']]);
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-10-01']);

        $employee->checkIn(['time' => '2026-10-08 09:00:00']);
        $employee->checkOut(['time' => '2026-10-08 19:30:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-10-08');

        $list = $this->actingAs($admin)->getJson("/attendance/employees/{$employee->id}/overtime")
            ->assertOk()->assertJsonCount(1)->json();

        $this->actingAs($admin)->postJson("/attendance/overtime/{$list[0]['id']}/approve", ['note' => 'ok'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'approved']);
    }

    public function test_special_working_day_store_update_destroy_over_http(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-601', 'name' => 'Rumana', 'basic_salary' => 26000, 'status' => 'active']);
        $shift = Shift::create(['name' => 'General', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'off_days' => ['Friday']]);
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-10-01']);

        $created = $this->actingAs($admin)->postJson("/attendance/employees/{$employee->id}/special-working-days", [
            'date' => '2026-10-09', 'is_payable' => true,
        ])->assertCreated()->json();

        $this->assertSame('day_off', $created['type']);

        $this->actingAs($admin)->getJson("/attendance/employees/{$employee->id}/special-working-days")
            ->assertOk()->assertJsonCount(1);

        $this->actingAs($admin)->putJson("/attendance/special-working-days/{$created['id']}", [
            'payment_amount' => 2000,
        ])->assertOk()->assertJsonFragment(['payment_amount' => '2000.00']);

        $this->actingAs($admin)->deleteJson("/attendance/special-working-days/{$created['id']}")->assertOk();
    }
}
