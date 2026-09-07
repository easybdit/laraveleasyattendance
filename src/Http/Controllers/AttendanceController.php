<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Minimal self-service check-in/out for the logged-in subject.
 *
 * Only works out of the box when the configured subject_model IS your
 * auth user model (the default) and uses the HasAttendance trait. For
 * a separate subject model (e.g. Employee), build your own controller
 * against Easybdit\LaravelEasyAttendance\Models\Attendance instead —
 * this one is a convenience starting point, not the only way in.
 */
class AttendanceController extends Controller
{
    public function checkIn(Request $request): JsonResponse
    {
        $attendance = Auth::user()->checkIn([
            'meta' => $request->input('meta'),
        ]);

        return response()->json($attendance, 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $attendance = Auth::user()->checkOut([
            'meta' => $request->input('meta'),
        ]);

        return response()->json($attendance, 201);
    }

    public function today(): JsonResponse
    {
        $window = Auth::user()->attendanceOn(now()->toDateString());

        return response()->json($window);
    }
}
