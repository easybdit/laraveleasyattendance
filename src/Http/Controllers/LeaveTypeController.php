<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns\Paginatable;
use Easybdit\LaravelEasyAttendance\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LeaveTypeController extends Controller
{
    use Paginatable;

    public function index(Request $request): JsonResponse
    {
        return response()->json(LeaveType::orderBy('name')->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(LeaveType::create($this->validated($request)), 201);
    }

    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        $leaveType->update($this->validated($request));

        return response()->json($leaveType->fresh());
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $leaveType->delete();

        return response()->json(['success' => true]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'days_allowed_per_year' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
