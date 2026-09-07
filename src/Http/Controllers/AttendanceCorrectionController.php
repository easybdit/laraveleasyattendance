<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\AttendanceCorrection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service submit + reviewer approve/reject for correction requests.
 * approve/reject are behind config('attendance.routes.review_middleware')
 * — set that to your own admin/HR gate in config/attendance.php.
 */
class AttendanceCorrectionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Auth::user()->attendanceCorrections()->latest('date')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'requested_in' => ['nullable', 'date_format:H:i'],
            'requested_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $correction = Auth::user()->requestAttendanceCorrection(
            array_merge($data, ['submitted_by' => Auth::id()])
        );

        return response()->json($correction, 201);
    }

    public function approve(AttendanceCorrection $correction, Request $request): JsonResponse
    {
        $correction->approve(Auth::id(), $request->input('note'));

        return response()->json($correction->fresh());
    }

    public function reject(AttendanceCorrection $correction, Request $request): JsonResponse
    {
        $correction->reject(Auth::id(), $request->input('note'));

        return response()->json($correction->fresh());
    }
}
