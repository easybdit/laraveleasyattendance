<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Models\Department;
use Easybdit\LaravelEasyAttendance\Models\Designation;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\LeaveType;
use Easybdit\LaravelEasyAttendance\Notifications\AttendanceMarkedLateNotification;
use Easybdit\LaravelEasyAttendance\Notifications\AttendanceDeviceSyncFailedNotification;
use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Easybdit\LaravelEasyAttendance\Tests\Fixtures\User;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;

class NewcomerFeaturesTest extends TestCase
{
    private function actor(): User
    {
        return User::create(['name' => 'Admin']);
    }

    // ── Leave balance ────────────────────────────────────────────────────────

    public function test_leave_balance_reflects_approved_leave_only(): void
    {
        $employee = Employee::create(['employee_code' => 'E-950', 'name' => 'Balance Test', 'basic_salary' => 20000, 'status' => 'active']);
        $type = LeaveType::create(['name' => 'Casual', 'days_allowed_per_year' => 10]);

        $approved = $employee->requestLeave(['leave_type_id' => $type->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-03', 'reason' => 'x']);
        $approved->approve();

        $pending = $employee->requestLeave(['leave_type_id' => $type->id, 'start_date' => '2026-04-01', 'end_date' => '2026-04-01', 'reason' => 'y']);
        // left pending — must not count against the balance

        $balance = $type->balanceForEmployee($employee, 2026);

        $this->assertSame(10, $balance['allowed']);
        $this->assertSame(3, $balance['used']); // Mar 1-3 inclusive
        $this->assertSame(7, $balance['remaining']);
    }

    public function test_leave_balance_does_not_go_negative_when_over_allowance(): void
    {
        $employee = Employee::create(['employee_code' => 'E-951', 'name' => 'Overused', 'basic_salary' => 20000, 'status' => 'active']);
        $type = LeaveType::create(['name' => 'Sick', 'days_allowed_per_year' => 2]);

        $leave = $employee->requestLeave(['leave_type_id' => $type->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-10', 'reason' => 'x']);
        $leave->approve();

        $balance = $type->balanceForEmployee($employee, 2026);

        $this->assertSame(10, $balance['used']);
        $this->assertSame(0, $balance['remaining']); // clamped, not negative
    }

    public function test_leave_balance_endpoint_over_http(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-952', 'name' => 'HTTP Balance', 'basic_salary' => 20000, 'status' => 'active']);
        $type = LeaveType::create(['name' => 'Casual', 'days_allowed_per_year' => 10]);
        $employee->requestLeave(['leave_type_id' => $type->id, 'start_date' => '2026-05-01', 'end_date' => '2026-05-01', 'reason' => 'x'])->approve();

        $response = $this->actingAs($admin)->getJson("/attendance/employees/{$employee->id}/leave-balance?year=2026");

        $response->assertOk()->assertJsonPath('year', 2026);
        $balances = $response->json('balances');
        $this->assertSame(1, $balances[0]['used']);
    }

    // ── Department / Designation ────────────────────────────────────────────

    public function test_department_and_designation_crud_over_http(): void
    {
        $admin = $this->actor();

        $dept = $this->actingAs($admin)->postJson('/attendance/departments', ['name' => 'Engineering'])
            ->assertCreated()->json();

        $designation = $this->actingAs($admin)->postJson('/attendance/designations', [
            'name' => 'Software Engineer', 'department_id' => $dept['id'],
        ])->assertCreated()->json();

        $this->assertSame($dept['id'], $designation['department_id']);

        $this->actingAs($admin)->putJson("/attendance/departments/{$dept['id']}", ['name' => 'Engineering & IT'])
            ->assertOk()->assertJsonFragment(['name' => 'Engineering & IT']);

        $this->actingAs($admin)->deleteJson("/attendance/designations/{$designation['id']}")->assertOk();
        $this->actingAs($admin)->deleteJson("/attendance/departments/{$dept['id']}")->assertOk();
    }

    public function test_employee_can_be_assigned_a_department_and_designation(): void
    {
        $dept = Department::create(['name' => 'Sales']);
        $designation = Designation::create(['name' => 'Sales Executive', 'department_id' => $dept->id]);

        $employee = Employee::create([
            'employee_code' => 'E-953', 'name' => 'Dept Test', 'basic_salary' => 20000, 'status' => 'active',
            'department_id' => $dept->id, 'designation_id' => $designation->id,
        ]);

        $this->assertSame('Sales', $employee->department->name);
        $this->assertSame('Sales Executive', $employee->designationRecord->name);
        $this->assertTrue($dept->employees->contains($employee));
    }

    public function test_deleting_a_department_nulls_out_designations_and_employees_instead_of_blocking(): void
    {
        $dept = Department::create(['name' => 'Temp']);
        $designation = Designation::create(['name' => 'Temp Role', 'department_id' => $dept->id]);
        $employee = Employee::create([
            'employee_code' => 'E-954', 'name' => 'Orphan Test', 'basic_salary' => 20000, 'status' => 'active',
            'department_id' => $dept->id,
        ]);

        $dept->delete();

        $this->assertNull($designation->fresh()->department_id);
        $this->assertNull($employee->fresh()->department_id);
    }

    // ── Localization ─────────────────────────────────────────────────────────

    public function test_notification_content_is_translatable_via_lang_files(): void
    {
        app()->setLocale('en');
        $employee = Employee::create(['employee_code' => 'E-955', 'name' => 'Lang Test', 'basic_salary' => 20000, 'status' => 'active']);

        $notification = new AttendanceMarkedLateNotification($employee, '2026-05-01', 15);
        $mail = $notification->toMail($this->actor());

        $this->assertSame(__('attendance::notifications.late.subject', ['name' => 'Lang Test']), $mail->subject);
    }

    public function test_device_sync_failed_notification_does_not_mangle_ip_and_port(): void
    {
        $device = AttendanceDevice::create(['name' => 'Gate', 'ip' => '192.168.1.50', 'port' => 4370, 'status' => 'active']);

        $notification = new AttendanceDeviceSyncFailedNotification($device, 'timeout', 2);
        $mail = $notification->toMail($this->actor());

        $this->assertTrue(collect($mail->introLines)->contains(fn ($line) => str_contains($line, '192.168.1.50:4370')));
    }

    public function test_print_views_use_translated_labels(): void
    {
        $admin = $this->actor();
        $employee = Employee::create(['employee_code' => 'E-956', 'name' => 'Print Lang', 'basic_salary' => 20000, 'allowances' => [], 'status' => 'active']);
        $slip = (new \Easybdit\LaravelEasyAttendance\Services\SalaryService)->generate($employee, 2026, 5);

        $response = $this->actingAs($admin)->get("/attendance/salary/{$slip->id}/print");

        $response->assertOk();
        $response->assertSee(__('attendance::print.payslip.net_salary'));
        $response->assertSee(__('attendance::print.payslip.basic_salary'));
    }

    public function test_status_short_labels_come_from_lang_file_not_a_hardcoded_map(): void
    {
        $this->assertSame('P', __('attendance::attendance.status_short.present'));
        $this->assertSame('LV', __('attendance::attendance.status_short.leave'));
    }
}
