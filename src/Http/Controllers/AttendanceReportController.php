<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns\ExportsCsv;
use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\SalarySlip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only report endpoints over pre-computed AttendanceSummary/SalarySlip
 * rows — build these first (attendance:build-summaries, or SalaryService)
 * before expecting data here. JSON by default; add ?format=csv to any of
 * them for a downloadable file instead — no PDF here, that's the
 * dedicated print views (see AttendancePrintController).
 */
class AttendanceReportController extends Controller
{
    use ExportsCsv;

    /**
     * GET /attendance/reports/daily?date=YYYY-MM-DD[&format=csv]
     * Every employee's status for one day.
     */
    public function daily(Request $request): JsonResponse|StreamedResponse
    {
        $date = $request->input('date', now()->toDateString());

        $rows = AttendanceSummary::with('employee:id,name,employee_code,designation')
            ->whereDate('date', $date)
            ->get();

        if ($this->wantsCsv($request)) {
            return $this->csvResponse(
                "attendance-daily-{$date}.csv",
                ['Employee Code', 'Name', 'Status', 'First In', 'Last Out', 'Late Minutes'],
                $rows->map(fn ($r) => [
                    $r->employee->employee_code, $r->employee->name, $r->status,
                    $r->first_in?->format('H:i:s'), $r->last_out?->format('H:i:s'), $r->late_minutes,
                ])
            );
        }

        return response()->json([
            'date' => $date,
            'total' => $rows->count(),
            'present' => $rows->whereIn('status', ['present', 'late'])->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'on_leave' => $rows->where('status', 'leave')->count(),
            'rows' => $rows->values(),
        ]);
    }

    /**
     * GET /attendance/reports/monthly?year=&month=[&format=csv]
     * A day-by-day grid: every employee x every day of the month.
     */
    public function monthly(Request $request): JsonResponse|StreamedResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $summaries = AttendanceSummary::with('employee:id,name,employee_code')
            ->forMonth($year, $month)
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get();

        if ($this->wantsCsv($request)) {
            return $this->csvResponse(
                "attendance-monthly-{$year}-{$month}.csv",
                ['Employee Code', 'Name', 'Date', 'Status', 'First In', 'Last Out', 'Late Minutes'],
                $summaries->map(fn ($r) => [
                    $r->employee->employee_code, $r->employee->name, $r->date->toDateString(), $r->status,
                    $r->first_in?->format('H:i:s'), $r->last_out?->format('H:i:s'), $r->late_minutes,
                ])
            );
        }

        $rows = $summaries->groupBy('employee_id')
            ->map(function ($rows) {
                return [
                    'employee' => $rows->first()->employee,
                    'days' => $rows->map->only(['date', 'status', 'first_in', 'last_out', 'late_minutes']),
                    'present' => $rows->whereIn('status', ['present', 'late'])->count(),
                    'absent' => $rows->where('status', 'absent')->count(),
                    'late' => $rows->where('status', 'late')->count(),
                    'leave' => $rows->where('status', 'leave')->count(),
                ];
            })
            ->values();

        return response()->json(['year' => $year, 'month' => $month, 'employees' => $rows]);
    }

    /**
     * GET /attendance/reports/employee/{employee}?from=&to=[&format=csv]
     * One employee's day-by-day status across a date range.
     */
    public function employeeWise(Employee $employee, Request $request): JsonResponse|StreamedResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $rows = $employee->summaries()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get(['date', 'status', 'first_in', 'last_out', 'late_minutes', 'ot_minutes']);

        if ($this->wantsCsv($request)) {
            return $this->csvResponse(
                "attendance-{$employee->employee_code}-{$from}-to-{$to}.csv",
                ['Date', 'Status', 'First In', 'Last Out', 'Late Minutes', 'OT Minutes'],
                $rows->map(fn ($r) => [
                    $r->date->toDateString(), $r->status,
                    $r->first_in?->format('H:i:s'), $r->last_out?->format('H:i:s'), $r->late_minutes, $r->ot_minutes,
                ])
            );
        }

        return response()->json([
            'employee' => $employee->only(['id', 'name', 'employee_code']),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]);
    }

    /**
     * GET /attendance/reports/salary?year=&month=[&format=csv]
     * Every employee's generated slip for a month.
     */
    public function salary(Request $request): JsonResponse|StreamedResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $rows = SalarySlip::with('employee:id,name,employee_code')->forMonth($year, $month)->get();

        if ($this->wantsCsv($request)) {
            return $this->csvResponse(
                "salary-{$year}-{$month}.csv",
                ['Employee Code', 'Name', 'Basic Salary', 'Present', 'Absent', 'Late', 'Leave', 'OT Hours', 'OT Amount', 'Special Pay', 'Deduction', 'Net Salary'],
                $rows->map(fn ($r) => [
                    $r->employee->employee_code, $r->employee->name, $r->basic_salary,
                    $r->present_days, $r->absent_days, $r->late_days, $r->leave_days,
                    $r->overtime_hours, $r->overtime_amount, $r->special_pay_amount,
                    $r->deduction_amount, $r->net_salary,
                ])
            );
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'total_net' => $rows->sum('net_salary'),
            'rows' => $rows,
        ]);
    }
}
