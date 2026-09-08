<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns\Paginatable;
use Easybdit\LaravelEasyAttendance\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DepartmentController extends Controller
{
    use Paginatable;

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Department::withCount('employees')->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Department::create($this->validated($request)), 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $department->update($this->validated($request));

        return response()->json($department->fresh());
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();

        return response()->json(['success' => true]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
