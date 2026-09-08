<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HolidayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Holiday::orderBy('date')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Holiday::create($this->validated($request)), 201);
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $holiday->update($this->validated($request));

        return response()->json($holiday->fresh());
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json(['success' => true]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'is_recurring_yearly' => ['nullable', 'boolean'],
        ]);
    }
}
