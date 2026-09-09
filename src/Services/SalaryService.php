<?php

namespace Easybdit\LaravelEasyAttendance\Services;

use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Easybdit\LaravelEasyAttendance\Models\SalarySlip;
use Easybdit\LaravelEasyAttendance\Models\SpecialWorkingDay;

/**
 * Generates a month's SalarySlip from that month's AttendanceSummary rows.
 * Deliberately simple, override-able default rule (see
 * config('attendance.salary')) — this is a starting point, not a
 * full payroll engine: absent days each dock one per-day rate; every
 * Nth late day also docks one (a common "late 3x = 1 absent" policy);
 * approved overtime and payable special working days add to the total.
 */
class SalaryService
{
    public function __construct(private AttendanceSummaryService $summaries = new AttendanceSummaryService) {}

    /**
     * Generate (or regenerate) one employee's slip for a month. Rebuilds
     * that month's summaries first so the slip always reflects the latest
     * synced attendance, not whatever happened to be cached.
     */
    public function generate(Employee $employee, int $year, int $month): SalarySlip
    {
        $this->summaries->buildForMonth($employee, $year, $month);

        $rows = AttendanceSummary::forEmployee($employee->id)->forMonth($year, $month)->get();

        $presentDays = $rows->whereIn('status', ['present', 'late'])->count();
        $absentDays = $rows->where('status', 'absent')->count();
        $lateDays = $rows->where('status', 'late')->count();
        $leaveDays = $rows->where('status', 'leave')->count();

        $config = config('attendance.salary');
        $workingDays = max(1, (int) $config['working_days_per_month']);
        $perDayRate = (float) $employee->basic_salary / $workingDays;

        $deduction = 0.0;

        if ($config['deduct_for_absent']) {
            $deduction += $absentDays * $perDayRate;
        }

        if ($config['deduct_for_late'] && $config['late_deduction_ratio'] > 0) {
            $extraAbsentDays = intdiv($lateDays, (int) $config['late_deduction_ratio']);
            $deduction += $extraAbsentDays * $perDayRate;
        }

        $deduction = round($deduction, 2);

        // Approved OT only — a pending/rejected record never reaches pay,
        // see OvertimeRecord::detectFromSummary()'s approval gate.
        $otHours = 0.0;
        $otAmount = 0.0;
        if (config('attendance.features.overtime', false)) {
            $otRows = OvertimeRecord::where('employee_id', $employee->id)->approved()->forMonth($year, $month)->get();
            $otHours = (float) $otRows->sum('ot_hours');
            $otAmount = (float) $otRows->sum('ot_amount');
        }

        // Special working day pay — only for a date the employee actually
        // worked (their summary landed present/late that day), not merely
        // because the record exists.
        $specialPay = 0.0;
        if (config('attendance.features.special_working_days', false)) {
            $workedDates = $rows->whereIn('status', ['present', 'late'])->pluck('date')->map(fn ($d) => $d->toDateString());
            $specialDays = SpecialWorkingDay::where('employee_id', $employee->id)
                ->payable()
                ->whereYear('date', $year)->whereMonth('date', $month)
                ->get();

            foreach ($specialDays as $special) {
                if ($workedDates->contains($special->date->toDateString())) {
                    $specialPay += $special->getPaymentAmount($perDayRate);
                }
            }
        }
        $specialPay = round($specialPay, 2);

        $gross = round((float) $employee->basic_salary + $employee->totalAllowances(), 2);
        $net = round($gross - $deduction + $otAmount + $specialPay, 2);

        return SalarySlip::updateOrCreate(
            ['employee_id' => $employee->id, 'year' => $year, 'month' => $month],
            [
                'basic_salary' => $employee->basic_salary,
                'allowances' => $employee->allowances,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'late_days' => $lateDays,
                'leave_days' => $leaveDays,
                'overtime_hours' => $otHours,
                'overtime_amount' => $otAmount,
                'special_pay_amount' => $specialPay,
                'deduction_amount' => $deduction,
                'net_salary' => $net,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Generate every active employee's slip for a month in one call —
     * one bad employee (e.g. a data issue) never blocks the rest.
     */
    public function generateForMonth(int $year, int $month): array
    {
        $slips = [];

        foreach (Employee::where('status', 'active')->get() as $employee) {
            try {
                $slips[] = $this->generate($employee, $year, $month);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $slips;
    }
}
