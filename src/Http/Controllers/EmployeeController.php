<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Employee::latest()->get());
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json($employee);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Employee::create($this->validated($request)), 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $employee->update($this->validated($request, $employee->id));

        return response()->json($employee->fresh());
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Assign a shift for a date range — a new roster row, not an update
     * to an existing one, so schedule history is never lost when someone's
     * hours change (see EmployeeShift / ShiftResolver).
     */
    public function assignSchedule(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $assignment = $employee->shiftAssignments()->create($data);

        return response()->json($assignment->load('shift'), 201);
    }

    public function schedule(Employee $employee): JsonResponse
    {
        return response()->json($employee->shiftAssignments()->with('shift')->latest('start_date')->get());
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:employees,employee_code'.($ignoreId ? ",{$ignoreId}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'device_user_id' => ['nullable', 'string', 'max:50', 'unique:employees,device_user_id'.($ignoreId ? ",{$ignoreId}" : '')],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'joined_at' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
