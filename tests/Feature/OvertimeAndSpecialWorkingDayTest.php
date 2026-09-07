<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Feature;

use Easybdit\LaravelEasyAttendance\Events\OvertimeReviewed;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\EmployeeShift;
use Easybdit\LaravelEasyAttendance\Models\Holiday;
use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Easybdit\LaravelEasyAttendance\Models\Shift;
use Easybdit\LaravelEasyAttendance\Models\SpecialWorkingDay;
use Easybdit\LaravelEasyAttendance\Services\AttendanceSummaryService;
use Easybdit\LaravelEasyAttendance\Services\SalaryService;
use Easybdit\LaravelEasyAttendance\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class OvertimeAndSpecialWorkingDayTest extends TestCase
{
    private function employeeWithShift(array $offDays = ['Friday']): Employee
    {
        $employee = Employee::create(['employee_code' => 'E-'.uniqid(), 'name' => 'Test', 'basic_salary' => 26000, 'allowances' => [], 'status' => 'active']);
        $shift = Shift::create(['name' => 'S-'.uniqid(), 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'late_grace_minutes' => 10, 'off_days' => $offDays]);
        EmployeeShift::create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'start_date' => '2026-10-01']);

        return $employee;
    }

    public function test_three_late_days_deduct_exactly_one_extra_day(): void
    {
        $employee = $this->employeeWithShift();

        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $d) {
            $employee->checkIn(['time' => $d.' 09:45:00']);
        }

        $slip = (new SalaryService)->generate($employee, 2026, 10);
        $perDay = 26000 / config('attendance.salary.working_days_per_month');

        $this->assertSame(3, $slip->late_days);
        // 22 other days absent (Oct has ~5 Fridays as day_off, not absent) + 1 late-derived day.
        $this->assertEqualsWithDelta(($slip->absent_days + 1) * $perDay, (float) $slip->deduction_amount, 0.01);
    }

    public function test_two_late_days_do_not_trigger_the_ratio_deduction(): void
    {
        $employee = $this->employeeWithShift();

        foreach (['2026-10-05', '2026-10-06'] as $d) {
            $employee->checkIn(['time' => $d.' 09:45:00']);
        }

        $slip = (new SalaryService)->generate($employee, 2026, 10);
        $perDay = 26000 / config('attendance.salary.working_days_per_month');

        $this->assertSame(2, $slip->late_days);
        $this->assertEqualsWithDelta($slip->absent_days * $perDay, (float) $slip->deduction_amount, 0.01);
    }

    public function test_overtime_is_auto_detected_as_pending_and_capped_at_max_hours(): void
    {
        $employee = $this->employeeWithShift();

        $employee->checkIn(['time' => '2026-10-08 09:00:00']);
        $employee->checkOut(['time' => '2026-10-08 19:30:00']); // 2.5h over a 17:00 end

        $summary = (new AttendanceSummaryService)->buildOne($employee, '2026-10-08');
        $ot = OvertimeRecord::where('employee_id', $employee->id)->where('date', '2026-10-08')->first();

        $this->assertSame(150, $summary->ot_minutes); // raw, uncapped
        $this->assertNotNull($ot);
        $this->assertSame('pending', $ot->status);
        $this->assertSame('auto', $ot->source);
        $this->assertEqualsWithDelta(2.0, (float) $ot->ot_hours, 0.01); // capped at config max_hours_per_day
    }

    public function test_overtime_rate_follows_the_basic_over_divisor_times_eight_formula(): void
    {
        $employee = $this->employeeWithShift();
        // basic 26000, divisor 26 (default), multiplier 2 -> (26000/(26*8))*2 = 250
        $rate = OvertimeRecord::hourlyRate($employee);

        $this->assertEqualsWithDelta(250.0, $rate, 0.01);
    }

    public function test_only_approved_overtime_reaches_the_salary_slip(): void
    {
        $employee = $this->employeeWithShift();
        $employee->checkIn(['time' => '2026-10-08 09:00:00']);
        $employee->checkOut(['time' => '2026-10-08 19:30:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-10-08');

        $slipBeforeApproval = (new SalaryService)->generate($employee, 2026, 10);
        $this->assertSame(0.0, (float) $slipBeforeApproval->overtime_amount);

        $ot = OvertimeRecord::where('employee_id', $employee->id)->first();
        Event::fake([OvertimeReviewed::class]);
        $ot->approve(1, 'confirmed');
        Event::assertDispatched(OvertimeReviewed::class, fn ($e) => $e->record->status === 'approved');

        $slipAfterApproval = (new SalaryService)->generate($employee, 2026, 10);
        $this->assertEqualsWithDelta(500.0, (float) $slipAfterApproval->overtime_amount, 0.01); // 2h × 250
    }

    public function test_rebuilding_a_summary_does_not_reopen_an_already_reviewed_overtime_record(): void
    {
        $employee = $this->employeeWithShift();
        $employee->checkIn(['time' => '2026-10-08 09:00:00']);
        $employee->checkOut(['time' => '2026-10-08 19:30:00']);
        (new AttendanceSummaryService)->buildOne($employee, '2026-10-08');

        $ot = OvertimeRecord::where('employee_id', $employee->id)->first();
        $ot->reject(1, 'not authorized');

        // Rebuilding the same day's summary (e.g. a re-sync) must not
        // silently flip a rejected record back to pending.
        (new AttendanceSummaryService)->buildOne($employee, '2026-10-08');

        $this->assertSame('rejected', $ot->fresh()->status);
    }

    public function test_special_working_day_type_is_detected_from_the_employees_own_shift_off_day(): void
    {
        $employee = $this->employeeWithShift(['Saturday']); // deliberately not Friday

        $special = SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-10', 'is_payable' => true]); // a Saturday

        $this->assertSame('day_off', $special->type);
    }

    public function test_special_working_day_type_is_holiday_when_a_holiday_covers_the_date(): void
    {
        $employee = $this->employeeWithShift();
        Holiday::create(['name' => 'Test Holiday', 'date' => '2026-10-11']);

        $special = SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-11', 'is_payable' => true]);

        $this->assertSame('holiday', $special->type);
    }

    public function test_special_working_day_pays_only_if_the_employee_actually_worked_it(): void
    {
        $employee = $this->employeeWithShift(); // Friday off

        // Asked to work Friday 2026-10-09, and did.
        $employee->checkIn(['time' => '2026-10-09 09:00:00']);
        $employee->checkOut(['time' => '2026-10-09 17:00:00']);
        SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-09', 'is_payable' => true]);

        $slip = (new SalaryService)->generate($employee, 2026, 10);
        $perDay = 26000 / config('attendance.salary.working_days_per_month');

        $this->assertEqualsWithDelta($perDay, (float) $slip->special_pay_amount, 0.01);
    }

    public function test_special_working_day_does_not_pay_if_the_employee_never_showed_up(): void
    {
        $employee = $this->employeeWithShift();

        // Marked as a special working day, but no punch — they didn't
        // actually come in, so no extra pay for it.
        SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-09', 'is_payable' => true]);

        $slip = (new SalaryService)->generate($employee, 2026, 10);

        $this->assertSame(0.0, (float) $slip->special_pay_amount);
    }

    public function test_a_custom_payment_amount_overrides_the_config_default(): void
    {
        $employee = $this->employeeWithShift();
        $employee->checkIn(['time' => '2026-10-09 09:00:00']);
        $employee->checkOut(['time' => '2026-10-09 17:00:00']);

        SpecialWorkingDay::create(['employee_id' => $employee->id, 'date' => '2026-10-09', 'is_payable' => true, 'payment_amount' => 1500]);

        $slip = (new SalaryService)->generate($employee, 2026, 10);

        $this->assertEqualsWithDelta(1500.0, (float) $slip->special_pay_amount, 0.01);
    }
}
