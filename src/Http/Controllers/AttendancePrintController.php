<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\AttendanceSummary;
use Easybdit\LaravelEasyAttendance\Models\SalarySlip;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Plain print-friendly HTML — no dompdf/wkhtmltopdf, no external package.
 * The "Print / Save as PDF" button just calls the browser's own print
 * dialog, which every modern browser can save as a PDF from directly.
 * Views are published (attendance-views tag) so you can restyle/rebrand
 * them without touching the package.
 */
class AttendancePrintController extends Controller
{
    private const STATUS_LABELS = [
        'present' => 'P',
        'late' => 'L',
        'absent' => 'A',
        'leave' => 'LV',
        'holiday' => 'H',
        'day_off' => 'O',
    ];

    /**
     * GET /attendance/salary/{slip}/print
     */
    public function payslip(SalarySlip $slip): View
    {
        $slip->loadMissing('employee');

        return view('attendance::print.payslip', ['slip' => $slip]);
    }

    /**
     * GET /attendance/reports/monthly/print?year=&month=
     */
    public function monthlyReport(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $daysInMonth = \Carbon\Carbon::create($year, $month, 1)->daysInMonth;
        $days = range(1, $daysInMonth);

        $employees = AttendanceSummary::with('employee:id,name,employee_code')
            ->forMonth($year, $month)
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($rows) {
                $byDay = [];
                foreach ($rows as $row) {
                    $byDay[$row->date->day] = [
                        'status' => $row->status,
                        'label' => self::STATUS_LABELS[$row->status] ?? '?',
                    ];
                }

                return [
                    'employee' => $rows->first()->employee,
                    'byDay' => $byDay,
                    'present' => $rows->whereIn('status', ['present', 'late'])->count(),
                    'absent' => $rows->where('status', 'absent')->count(),
                    'late' => $rows->where('status', 'late')->count(),
                    'leave' => $rows->where('status', 'leave')->count(),
                ];
            })
            ->values();

        return view('attendance::print.monthly-report', [
            'monthLabel' => \Carbon\Carbon::create($year, $month, 1)->format('F Y'),
            'days' => $days,
            'employees' => $employees,
        ]);
    }
}
