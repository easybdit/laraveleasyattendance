<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Shift::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Shift::create($this->validated($request)), 201);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $shift->update($this->validated($request));

        return response()->json($shift->fresh());
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $shift->delete();

        return response()->json(['success' => true]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0'],
            'off_days' => ['nullable', 'array'],
            'off_days.*' => ['string', 'in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'],
        ]);
    }
}
