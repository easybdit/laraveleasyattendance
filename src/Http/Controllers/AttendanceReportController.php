<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\SalarySlip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Read-only report endpoints over pre-computed AttendanceSummary/SalarySlip
 * rows — build these first (attendance:build-summaries, or SalaryService)
 * before expecting data here. Deliberately JSON, not PDF/Blade: the
 * presentation layer belongs to whatever real app/GUI consumes this
 * package, not the package itself.
 */
class AttendanceReportController extends Controller
{
    /**
     * GET /attendance/reports/daily?date=YYYY-MM-DD
     * Every employee's status for one day.
     */
    public function daily(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        $rows = AttendanceSummary::with('employee:id,name,employee_code,designation')
            ->whereDate('date', $date)
            ->get();

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
     * GET /attendance/reports/monthly?year=&month=
     * A day-by-day grid: every employee x every day of the month.
     */
    public function monthly(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $rows = AttendanceSummary::with('employee:id,name,employee_code')
            ->forMonth($year, $month)
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id')
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
     * GET /attendance/reports/employee/{employee}?from=&to=
     * One employee's day-by-day status across a date range.
     */
    public function employeeWise(Employee $employee, Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $rows = $employee->summaries()
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get(['date', 'status', 'first_in', 'last_out', 'late_minutes', 'ot_minutes']);

        return response()->json([
            'employee' => $employee->only(['id', 'name', 'employee_code']),
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]);
    }

    /**
     * GET /attendance/reports/salary?year=&month=
     * Every employee's generated slip for a month.
     */
    public function salary(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $rows = SalarySlip::with('employee:id,name,employee_code')->forMonth($year, $month)->get();

        return response()->json([
            'year' => $year,
            'month' => $month,
            'total_net' => $rows->sum('net_salary'),
            'rows' => $rows,
        ]);
    }
}
