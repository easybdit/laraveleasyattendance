<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\SpecialWorkingDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SpecialWorkingDayController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        return response()->json($employee->specialWorkingDays()->orderByDesc('date')->get());
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'is_payable' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // type is derived automatically from the date — see SpecialWorkingDay::booted().
        return response()->json($employee->specialWorkingDays()->create($data), 201);
    }

    public function update(Request $request, SpecialWorkingDay $specialWorkingDay): JsonResponse
    {
        $data = $request->validate([
            'is_payable' => ['nullable', 'boolean'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $specialWorkingDay->update($data);

        return response()->json($specialWorkingDay->fresh());
    }

    public function destroy(SpecialWorkingDay $specialWorkingDay): JsonResponse
    {
        $specialWorkingDay->delete();

        return response()->json(['success' => true]);
    }
}
