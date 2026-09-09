<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncLog;
use App\Services\AttendancePunchIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceDevicePushController extends Controller
{
    public function punches(
        Request $request,
        AttendanceDevice $device,
        AttendancePunchIngestService $ingest
    ): JsonResponse {
        $this->authorizeDevice($request, $device);

        $data = $request->validate([
            'punches' => ['required', 'array', 'min:1', 'max:1000'],
            'punches.*' => ['required', 'array'],
        ]);

        $result = $ingest->ingestBatch(
            $device,
            $data['punches']
        );

        return response()->json([
            'ok' => true,
            'device' => $device->code,
            'result' => $result,
        ]);
    }

    public function heartbeat(
        Request $request,
        AttendanceDevice $device
    ): JsonResponse {
        $this->authorizeDevice($request, $device);

        $device->update([
            'last_seen_at' => now(),
        ]);

        AttendanceDeviceSyncLog::create([
            'attendance_device_id' => $device->id,
            'direction' => 'heartbeat',
            'status' => 'success',
            'received_count' => 0,
            'processed_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'finished_at' => now(),
            'message' => 'Heartbeat received.',
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'device' => $device->code,
        ]);
    }

    private function authorizeDevice(
        Request $request,
        AttendanceDevice $device
    ): void {
        abort_unless($device->is_active, 403, 'Device disabled.');

        $token = $request->bearerToken()
            ?: $request->header('X-Attendance-Token');

        abort_unless(
            $device->verifyToken($token),
            401,
            'Invalid device token.'
        );
    }
}
