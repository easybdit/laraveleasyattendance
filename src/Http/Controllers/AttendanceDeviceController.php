<?php

namespace Easybdit\LaravelEasyAttendance\Http\Controllers;

use Easybdit\LaravelEasyAttendance\Http\Controllers\Concerns\Paginatable;
use Easybdit\LaravelEasyAttendance\Models\AttendanceDevice;
use Easybdit\LaravelEasyAttendance\Services\AttendanceDeviceSyncService;
use Easybdit\LaravelEasyAttendance\Services\ZKService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceDeviceController extends Controller
{
    use Paginatable;

    public function index(Request $request): JsonResponse
    {
        return response()->json(AttendanceDevice::latest()->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json(AttendanceDevice::create($data), 201);
    }

    public function update(Request $request, AttendanceDevice $device): JsonResponse
    {
        $device->update($this->validated($request, $device->id));

        return response()->json($device->fresh());
    }

    public function destroy(AttendanceDevice $device): JsonResponse
    {
        $device->delete();

        return response()->json(['success' => true]);
    }

    public function test(AttendanceDevice $device): JsonResponse
    {
        if (! $device->ip) {
            return response()->json(['success' => false, 'message' => 'No IP configured on this device.']);
        }

        try {
            $svc = new ZKService($device->ip, (int) ($device->port ?: 4370), $device->comm_key, 8);
            $connected = $svc->connect();
            $svc->disconnect();

            return response()->json([
                'success' => $connected,
                'message' => $connected ? 'Connected successfully.' : "Cannot connect to {$device->ip}:{$device->port}",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * A large backlog over the ZK protocol's many-small-packet exchange can
     * take a while — lift PHP's execution ceiling for this one action. If
     * this still times out at the webserver/proxy layer for you, prefer the
     * scheduled `attendance:sync-devices` command, which keeps backlogs
     * small so this rarely runs long in the first place.
     */
    public function pull(AttendanceDevice $device, AttendanceDeviceSyncService $sync): JsonResponse
    {
        set_time_limit(300);

        return response()->json($sync->pull($device));
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ip' => ['nullable', 'ip', 'required_without:serial_number'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'comm_key' => ['nullable', 'string', 'max:20'],
            'serial_number' => ['nullable', 'string', 'max:50', 'required_without:ip', 'unique:attendance_devices,serial_number'.($ignoreId ? ",{$ignoreId}" : '')],
            'model' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
