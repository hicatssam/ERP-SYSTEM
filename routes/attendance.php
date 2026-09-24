<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendancePayrollController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\WorkShiftController;
use App\Http\Controllers\Admin\AttendanceDeviceController;
use App\Http\Controllers\Admin\FaceAttendanceController;
use App\Http\Controllers\FaceioWebhookController;
use App\Http\Controllers\AttendanceDevicePushController;
use App\Http\Controllers\Admin\AttendancePayrollSettingsController;
use App\Http\Middleware\EnsureAttendanceEnabled;
use App\Http\Middleware\EnsureBiometricAttendanceEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'location.scope',
    'password.changed',
])->group(function (): void {
    Route::get(
        '/settings/attendance-payroll',
        [AttendancePayrollSettingsController::class, 'edit']
    )->middleware('can:settings.manage')
        ->name('settings.attendance-payroll.edit');

    Route::put(
        '/settings/attendance-payroll',
        [AttendancePayrollSettingsController::class, 'update']
    )->middleware('can:settings.manage')
        ->name('settings.attendance-payroll.update');

    Route::prefix('attendance')
        ->name('attendance.')
        ->middleware(EnsureAttendanceEnabled::class)
        ->group(function (): void {
            Route::get('/', [AttendanceController::class, 'index'])
                ->middleware('can:attendance.view')
                ->name('index');

            Route::post('/employees/{employee}', [AttendanceController::class, 'store'])
                ->middleware('can:attendance.manage')
                ->name('store');

            Route::post('/records/{record}/approve', [AttendanceController::class, 'approve'])
                ->middleware('can:attendance.approve')
                ->name('approve');

            Route::get('/face/kiosk', [FaceAttendanceController::class, 'kiosk'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                ])
                ->name('face.kiosk');

            Route::get('/face/employees/{employee}', [FaceAttendanceController::class, 'enrollPage'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                ])
                ->name('face.enroll-page');

            Route::post('/face/employees/{employee}/enroll', [FaceAttendanceController::class, 'enroll'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                ])
                ->name('face.enroll');

            Route::get('/face/employees/{employee}/status', [FaceAttendanceController::class, 'status'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                ])
                ->name('face.status');

            Route::post('/face/employees/{employee}/revoke', [FaceAttendanceController::class, 'revoke'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                ])
                ->name('face.revoke');

            Route::post('/face/punch', [FaceAttendanceController::class, 'punch'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                    'throttle:30,1',
                ])
                ->name('face.punch');

            Route::get('/shifts', [WorkShiftController::class, 'index'])
                ->middleware('can:attendance.view')
                ->name('shifts.index');

            Route::post('/shifts', [WorkShiftController::class, 'store'])
                ->middleware('can:attendance.shifts.manage')
                ->name('shifts.store');

            Route::post('/shifts/assign', [WorkShiftController::class, 'assign'])
                ->middleware('can:attendance.shifts.manage')
                ->name('shifts.assign');

            Route::get('/leaves', [LeaveController::class, 'index'])
                ->middleware('can:attendance.leaves.view')
                ->name('leaves.index');

            Route::post('/leaves', [LeaveController::class, 'store'])
                ->middleware('can:attendance.leaves.manage')
                ->name('leaves.store');

            Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])
                ->middleware('can:attendance.leaves.approve')
                ->name('leaves.approve');

            Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])
                ->middleware('can:attendance.leaves.approve')
                ->name('leaves.reject');

            Route::get('/devices', [AttendanceDeviceController::class, 'index'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.devices.view',
                ])
                ->name('devices.index');

            Route::post('/devices', [AttendanceDeviceController::class, 'store'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.devices.manage',
                ])
                ->name('devices.store');

            Route::post('/devices/{device}/regenerate-token', [AttendanceDeviceController::class, 'regenerateToken'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.devices.manage',
                ])
                ->name('devices.regenerate-token');

            Route::post('/devices/{device}/mappings', [AttendanceDeviceController::class, 'storeMapping'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.devices.manage',
                ])
                ->name('devices.mappings.store');

            Route::delete('/devices/{device}/mappings/{mapping}', [AttendanceDeviceController::class, 'destroyMapping'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.devices.manage',
                ])
                ->name('devices.mappings.destroy');

            Route::post('/devices/{device}/reprocess', [AttendanceDeviceController::class, 'reprocess'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.devices.manage',
                ])
                ->name('devices.reprocess');
        });

    Route::post(
        '/payroll/periods/{period}/attendance-sync',
        [AttendancePayrollController::class, 'sync']
    )->middleware([
        EnsureAttendanceEnabled::class,
        'can:payroll.attendance.sync',
    ])->name('payroll.attendance.sync');
});


Route::post(
    '/attendance/integrations/faceio/webhook',
    FaceioWebhookController::class
)->middleware([
    EnsureAttendanceEnabled::class,
    EnsureBiometricAttendanceEnabled::class,
    'throttle:180,1',
])->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->name('attendance.integrations.faceio.webhook');

Route::prefix('attendance/integrations')
    ->middleware([
        EnsureAttendanceEnabled::class,
        EnsureBiometricAttendanceEnabled::class,
        'throttle:120,1',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ])
    ->group(function (): void {
        Route::post(
            '/devices/{device}/heartbeat',
            [AttendanceDevicePushController::class, 'heartbeat']
        )->name('attendance.integrations.devices.heartbeat');

        Route::post(
            '/devices/{device}/punches',
            [AttendanceDevicePushController::class, 'punches']
        )->name('attendance.integrations.devices.punches');
    });
