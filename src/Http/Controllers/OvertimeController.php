<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\OvertimeRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Overtime records are auto-detected (see AttendanceSummaryService /
 * OvertimeRecord::detectFromSummary()) — there's no store() here, only
 * reviewing what was detected. Nested under {employee} for the same
 * reason as LeaveController: no generic "current employee" to resolve.
 */
class OvertimeController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        return response()->json($employee->overtimeRecords()->orderByDesc('date')->get());
    }

    public function approve(OvertimeRecord $overtime, Request $request): JsonResponse
    {
        $overtime->approve(Auth::id(), $request->input('note'));

        return response()->json($overtime->fresh());
    }

    public function reject(OvertimeRecord $overtime, Request $request): JsonResponse
    {
        $overtime->reject(Auth::id(), $request->input('note'));

        return response()->json($overtime->fresh());
    }
}
