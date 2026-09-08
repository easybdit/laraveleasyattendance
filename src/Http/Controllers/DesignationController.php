<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DesignationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Designation::with('department:id,name')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Designation::create($this->validated($request)), 201);
    }

    public function update(Request $request, Designation $designation): JsonResponse
    {
        $designation->update($this->validated($request));

        return response()->json($designation->fresh());
    }

    public function destroy(Designation $designation): JsonResponse
    {
        $designation->delete();

        return response()->json(['success' => true]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);
    }
}
