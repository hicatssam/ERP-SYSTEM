<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\EmployeeBiometricMapping;
use App\Models\Location;
use App\Services\AttendancePunchProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceDeviceController extends Controller
{
    public function index(Request $request): View
    {
        $devices = AttendanceDevice::query()
            ->with('location')
            ->withCount(['mappings', 'punches'])
            ->latest('id')
            ->get();

        $selectedDevice = null;

        if ($request->filled('device')) {
            $selectedDevice = AttendanceDevice::query()
                ->with([
                    'location',
                    'mappings.employee',
                    'syncLogs' => fn ($q) => $q
                        ->latest('started_at')
                        ->limit(20),
                ])
                ->find($request->integer('device'));
        }

        $recentPunches = $selectedDevice
            ? AttendancePunch::query()
                ->with('employee')
                ->where('attendance_device_id', $selectedDevice->id)
                ->latest('punch_at')
                ->limit(50)
                ->get()
            : collect();

        return view('admin.attendance.devices', [
            'devices' => $devices,
            'selectedDevice' => $selectedDevice,
            'recentPunches' => $recentPunches,
            'employees' => Employee::query()
                ->orderBy('full_name')
                ->get(),
            'locations' => Location::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'unique:attendance_devices,code'],
            'name' => ['required', 'string', 'max:190'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'connection_mode' => [
                'required',
                Rule::in([
                    'local_bridge',
                    'push',
                    'rest_api',
                    'sdk',
                    'manual',
                ]),
            ],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'location_id' => ['nullable', Rule::exists('locations', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $plainToken = Str::random(64);

        $device = AttendanceDevice::create([
            ...$data,
            'api_token_hash' => hash('sha256', $plainToken),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('attendance.devices.index', [
                'device' => $device->id,
            ])
            ->with('success', 'تم إنشاء جهاز الحضور.')
            ->with('attendance_device_token', $plainToken)
            ->with('attendance_device_token_id', $device->id);
    }

    public function regenerateToken(
        Request $request,
        AttendanceDevice $device
    ): RedirectResponse {
        $plainToken = Str::random(64);

        $device->update([
            'api_token_hash' => hash('sha256', $plainToken),
        ]);

        return redirect()
            ->route('attendance.devices.index', [
                'device' => $device->id,
            ])
            ->with('success', 'تم إنشاء Token جديد. التوكن القديم لم يعد صالحًا.')
            ->with('attendance_device_token', $plainToken)
            ->with('attendance_device_token_id', $device->id);
    }

    public function storeMapping(
        Request $request,
        AttendanceDevice $device
    ): RedirectResponse {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
            'device_user_id' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($device, $data): void {
            EmployeeBiometricMapping::query()
                ->where('attendance_device_id', $device->id)
                ->where('device_user_id', $data['device_user_id'])
                ->where('employee_id', '!=', $data['employee_id'])
                ->delete();

            EmployeeBiometricMapping::query()->updateOrCreate(
                [
                    'attendance_device_id' => $device->id,
                    'employee_id' => $data['employee_id'],
                ],
                [
                    'device_user_id' => $data['device_user_id'],
                    'is_active' => true,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });

        return redirect()
            ->route('attendance.devices.index', [
                'device' => $device->id,
            ])
            ->with('success', 'تم ربط مستخدم الجهاز بالموظف.');
    }

    public function destroyMapping(
        AttendanceDevice $device,
        EmployeeBiometricMapping $mapping
    ): RedirectResponse {
        abort_unless(
            $mapping->attendance_device_id === $device->id,
            404
        );

        $mapping->delete();

        return redirect()
            ->route('attendance.devices.index', [
                'device' => $device->id,
            ])
            ->with('success', 'تم حذف الربط.');
    }

    public function reprocess(
        AttendanceDevice $device,
        AttendancePunchProcessor $processor
    ): RedirectResponse {
        $punches = AttendancePunch::query()
            ->where('attendance_device_id', $device->id)
            ->whereIn('status', ['pending', 'unmapped'])
            ->orderBy('punch_at')
            ->limit(2000)
            ->get();

        $processed = 0;
        $unmapped = 0;

        foreach ($punches as $punch) {
            $result = $processor->process($punch);

            if ($result === 'processed') {
                $processed++;
            } elseif ($result === 'unmapped') {
                $unmapped++;
            }
        }

        return redirect()
            ->route('attendance.devices.index', [
                'device' => $device->id,
            ])
            ->with(
                'success',
                "إعادة المعالجة: {$processed} تمت، {$unmapped} غير مربوطة."
            );
    }
}
