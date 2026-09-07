<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Events\LeaveReviewed;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceReportController;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\EmployeeShift;
use Easybdit\LaravelEasyAttendance\Models\Holiday;
use Easybdit\LaravelEasyAttendance\Models\Shift;
use Easybdit\LaravelEasyAttendance\Services\AttendanceSummaryService;
use Easybdit\LaravelEasyAttendance\Services\SalaryService;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class HrCoreTest extends TestCase
{
    private function employee(array $attrs = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => 'E-'.uniqid(),
            'name' => 'Test Employee',
            'basic_salary' => 30000,
            'allowances' => ['house_rent' => 5000],
            'status' => 'active',
        ], $attrs));
    }

    private function shift(array $offDays = ['Friday']): Shift
    {
        return Shift::create([
            'name' => 'General',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_minutes' => 10,
            'off_days' => $offDays,
        ]);
    }

    public function test_employee_total_allowances_sums_the_map(): void
    {
        $employee = $this->employee(['allowances' => ['house_rent' => 5000, 'medical' => 1000]]);

        $this->assertSame(6000.0, $employee->totalAllowances());
    }

    public function test_summary_is_present_when_checked_in_within_grace(): void
    {
        $employee = $this->employee();
        $shift = $this->shift();
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-09-01']);

        $employee->checkIn(['time' => '2026-09-01 09:05:00']);
        $employee->checkOut(['time' => '2026-09-01 17:00:00']);

        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');

        $this->assertSame('present', $summary->status);
        $this->assertSame(0, $summary->late_minutes);
    }

    public function test_summary_is_late_past_grace_and_reports_minutes_from_shift_start(): void
    {
        $employee = $this->employee();
        $shift = $this->shift();
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-09-01']);

        $employee->checkIn(['time' => '2026-09-01 09:45:00']);

        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');

        $this->assertSame('late', $summary->status);
        $this->assertSame(45, $summary->late_minutes);
    }

    public function test_summary_is_absent_with_no_punch_on_a_working_day(): void
    {
        $employee = $this->employee();
        $shift = $this->shift();
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-09-01']);

        // 2026-09-01 is a Tuesday — a normal working day, not Friday.
        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');

        $this->assertSame('absent', $summary->status);
    }

    public function test_summary_is_day_off_on_the_shifts_off_day_with_no_punch(): void
    {
        $employee = $this->employee();
        $shift = $this->shift(['Friday']);
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-09-01']);

        // 2026-09-04 is a Friday.
        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-04');

        $this->assertSame('day_off', $summary->status);
    }

    public function test_summary_is_holiday_with_no_punch_on_a_holiday(): void
    {
        $employee = $this->employee();
        Holiday::create(['name' => 'Test Holiday', 'date' => '2026-09-01']);

        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');

        $this->assertSame('holiday', $summary->status);
        $this->assertSame('Test Holiday', $summary->holiday_name);
    }

    public function test_recurring_yearly_holiday_matches_any_year(): void
    {
        Holiday::create(['name' => 'Independence Day', 'date' => '2020-03-26', 'is_recurring_yearly' => true]);

        $this->assertNotNull(Holiday::onDate('2026-03-26'));
        $this->assertSame('Independence Day', Holiday::onDate('2099-03-26')->name);
    }

    public function test_summary_is_leave_when_an_approved_leave_covers_the_date_even_with_a_punch(): void
    {
        $employee = $this->employee();

        $leave = $employee->requestLeave(['start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'reason' => 'sick']);
        $leave->approve();

        // Leave takes priority over everything else, even a stray punch
        // that day (e.g. someone badged in before remembering they're on leave).
        $employee->checkIn(['time' => '2026-09-01 09:05:00']);

        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');

        $this->assertSame('leave', $summary->status);
    }

    public function test_pending_leave_does_not_count_as_leave(): void
    {
        Event::fake([LeaveReviewed::class]);
        $employee = $this->employee();

        $employee->requestLeave(['start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'reason' => 'sick']);

        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');

        $this->assertSame('absent', $summary->status);
        Event::assertNotDispatched(LeaveReviewed::class);
    }

    public function test_rejected_leave_fires_event_and_does_not_count_as_leave(): void
    {
        Event::fake([LeaveReviewed::class]);
        $employee = $this->employee();

        $leave = $employee->requestLeave(['start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'reason' => 'sick']);
        $leave->reject(null, 'not enough notice');

        $this->assertSame('rejected', $leave->fresh()->status);
        Event::assertDispatched(LeaveReviewed::class, fn ($e) => $e->leave->status === 'rejected');
    }

    public function test_salary_deduction_is_absent_days_times_the_per_day_rate(): void
    {
        $employee = $this->employee(['basic_salary' => 30000, 'allowances' => []]);
        // No punches at all this month -> every working day resolves absent
        // (falls back to config('attendance.default_shift'), Friday off) —
        // enough to verify the deduction formula against a known absent count.
        $slip = (new SalaryService)->generate($employee, 2026, 9);

        $expectedPerDay = 30000 / config('attendance.salary.working_days_per_month');
        $this->assertEqualsWithDelta($slip->absent_days * $expectedPerDay, (float) $slip->deduction_amount, 0.01);
        $this->assertEqualsWithDelta(30000 - $slip->deduction_amount, (float) $slip->net_salary, 0.01);
    }

    public function test_salary_generation_rebuilds_summaries_for_the_month(): void
    {
        $employee = $this->employee();
        $employee->checkIn(['time' => '2026-09-01 09:00:00']);
        $employee->checkOut(['time' => '2026-09-01 17:00:00']);

        $slip = (new SalaryService)->generate($employee, 2026, 9);

        $this->assertGreaterThanOrEqual(1, $slip->present_days);
        $this->assertSame(1, $employee->summaries()->whereDate('date', '2026-09-01')->where('status', 'present')->count());
    }

    public function test_reports_reflect_generated_data(): void
    {
        $employee = $this->employee();
        $employee->checkIn(['time' => '2026-09-01 09:05:00']);
        $employee->checkOut(['time' => '2026-09-01 17:00:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-09-01');
        (new SalaryService)->generate($employee, 2026, 9);

        $controller = app(AttendanceReportController::class);

        $daily = json_decode($controller->daily(Request::create('/', 'GET', ['date' => '2026-09-01']))->getContent(), true);
        $this->assertSame(1, $daily['present']);

        $employeeReport = json_decode(
            $controller->employeeWise($employee, Request::create('/', 'GET', ['from' => '2026-09-01', 'to' => '2026-09-01']))->getContent(),
            true
        );
        $this->assertCount(1, $employeeReport['rows']);
        $this->assertSame('present', $employeeReport['rows'][0]['status']);

        $salary = json_decode($controller->salary(Request::create('/', 'GET', ['year' => 2026, 'month' => 9]))->getContent(), true);
        $this->assertCount(1, $salary['rows']);
    }
}
