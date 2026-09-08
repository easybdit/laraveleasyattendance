<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns\Paginatable;
use Easybdit\LaravelEasyAttendance\Models\Department;
use Easybdit\LaravelEasyAttendance\Models\Designation;
use Easybdit\LaravelEasyAttendance\Models\Employee;
use Easybdit\LaravelEasyAttendance\Models\Shift;
use Easybdit\LaravelEasyAttendance\Services\EmployeeCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use Paginatable;

    /**
     * GET /attendance/employees?search=&status=&department_id=&per_page=
     * search matches name, employee_code, or email.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        return response()->json($query->paginate($this->perPage($request)));
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
            'shift_id' => ['required', Rule::exists(Shift::class, 'id')],
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

    /**
     * POST /attendance/employees/import — bulk-add from a CSV file (field
     * name "file"). See EmployeeCsvImporter for the expected columns.
     */
    public function import(Request $request, EmployeeCsvImporter $importer): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        return response()->json($importer->import($request->file('file')));
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'employee_code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'employee_code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', Rule::exists(Department::class, 'id')],
            'designation_id' => ['nullable', Rule::exists(Designation::class, 'id')],
            'device_user_id' => ['nullable', 'string', 'max:50', Rule::unique(Employee::class, 'device_user_id')->ignore($ignoreId)],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'joined_at' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
