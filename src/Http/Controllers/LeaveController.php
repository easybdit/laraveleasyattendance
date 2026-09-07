<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Nested under an explicit {employee} rather than "my leaves" — Leave
 * belongs to Employee, which is a separate concept from whatever
 * attendance.subject_model your app's Auth::user() actually is (see the
 * README's "Core concept: the subject model"), so there's no generic way
 * to resolve "the current employee" here. Gate these behind your own
 * policy (e.g. an Employee <-> User link) in your app if self-service
 * leave requests are what you need instead of HR/admin managing them.
 */
class LeaveController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        return response()->json($employee->leaves()->with('leaveType')->latest('start_date')->get());
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['nullable', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($employee->requestLeave($data), 201);
    }

    public function approve(Leave $leave, Request $request): JsonResponse
    {
        $leave->approve(Auth::id(), $request->input('note'));

        return response()->json($leave->fresh());
    }

    public function reject(Leave $leave, Request $request): JsonResponse
    {
        $leave->reject(Auth::id(), $request->input('note'));

        return response()->json($leave->fresh());
    }
}
