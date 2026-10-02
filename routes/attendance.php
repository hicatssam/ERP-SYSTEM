<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\EmployeeHrFileController;
use App\Http\Controllers\Admin\HrDashboardController;
use App\Http\Controllers\Admin\HrOrganizationController;
use App\Http\Controllers\Admin\AttendanceCorrectionController;
use App\Http\Controllers\Employee\MyHrController;
use App\Http\Controllers\Employee\EmployeeFaceAttendanceController;
use App\Http\Controllers\Employee\EmployeeSelfAttendanceController;
use App\Http\Controllers\Admin\EmployeeSelfAttendanceReviewController;
use App\Http\Controllers\Admin\AttendancePayrollController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\WorkHolidayController;
use App\Http\Controllers\Admin\WorkShiftController;
use App\Http\Controllers\Admin\AttendanceDeviceController;
use App\Http\Controllers\Admin\FaceAttendanceController;
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

    Route::get(
        '/attendance/face/connection-test',
        [FaceAttendanceController::class, 'connectionTest']
    )->middleware([
        'can:settings.manage',
        'throttle:20,1',
    ])->name('attendance.face.connection-test');

    Route::prefix('my-hr')->name('my-hr.')->group(function (): void {
        Route::get('/', [MyHrController::class, 'index'])->name('index');
        Route::get('/payslips/{item}', [MyHrController::class, 'payslip'])->name('payslips.show');
        Route::post('/leaves', [MyHrController::class, 'leave'])
            ->middleware(EnsureAttendanceEnabled::class)->name('leaves.store');
        Route::post('/corrections', [MyHrController::class, 'correction'])
            ->middleware(EnsureAttendanceEnabled::class)->name('corrections.store');
        Route::post('/punch', [EmployeeSelfAttendanceController::class, 'punch'])
            ->middleware([EnsureAttendanceEnabled::class, 'throttle:6,1'])->name('punch');
        Route::post('/face/challenge', [EmployeeFaceAttendanceController::class, 'challenge'])
            ->middleware([EnsureBiometricAttendanceEnabled::class, 'throttle:10,1'])
            ->name('face.challenge');
        Route::post('/face/punch', [EmployeeFaceAttendanceController::class, 'punch'])
            ->middleware([EnsureBiometricAttendanceEnabled::class, 'throttle:5,1'])
            ->name('face.punch');
    });

    Route::get('/hr', [HrDashboardController::class, 'index'])
        ->middleware('can:hr.dashboard.view')->name('hr.dashboard');
    Route::get('/hr/attendance-report.csv', [HrDashboardController::class, 'csv'])
        ->middleware('can:hr.dashboard.view')->name('hr.report.csv');

    Route::get('/hr/organization', [HrOrganizationController::class, 'index'])
        ->middleware('can:hr.organization.view')->name('hr.organization.index');
    Route::get('/hr/organization.csv', [HrOrganizationController::class, 'csv'])
        ->middleware('can:hr.organization.view')->name('hr.organization.csv');
    Route::post('/hr/departments', [HrOrganizationController::class, 'department'])
        ->middleware('can:hr.organization.manage')->name('hr.departments.store');
    Route::post('/hr/positions', [HrOrganizationController::class, 'position'])
        ->middleware('can:hr.organization.manage')->name('hr.positions.store');
    Route::post('/hr/cost-centers', [HrOrganizationController::class, 'center'])
        ->middleware('can:hr.organization.manage')->name('hr.cost-centers.store');
    Route::post('/hr/assignments', [HrOrganizationController::class, 'assign'])
        ->middleware('can:hr.organization.manage')->name('hr.assignments.store');

    Route::prefix('hr/employees/{employee}')->name('hr.employees.')->group(function (): void {
        Route::get('/file', [EmployeeHrFileController::class, 'show'])
            ->middleware('can:hr.documents.view')->name('file');
        Route::put('/file', [EmployeeHrFileController::class, 'profile'])
            ->middleware('can:hr.documents.manage')->name('file.update');
        Route::post('/documents', [EmployeeHrFileController::class, 'upload'])
            ->middleware('can:hr.documents.manage')->name('documents.store');
        Route::get('/documents/{document}', [EmployeeHrFileController::class, 'download'])
            ->middleware('can:hr.documents.view')->name('documents.download');
    });

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

            Route::get('/self-requests', [EmployeeSelfAttendanceReviewController::class, 'index'])
                ->middleware('can:attendance.approve')->name('self-requests.index');
            Route::post('/self-requests/{submission}/approve', [EmployeeSelfAttendanceReviewController::class, 'approve'])
                ->middleware('can:attendance.approve')->name('self-requests.approve');
            Route::post('/self-requests/{submission}/reject', [EmployeeSelfAttendanceReviewController::class, 'reject'])
                ->middleware('can:attendance.approve')->name('self-requests.reject');

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

            Route::post('/face/challenge', [FaceAttendanceController::class, 'challenge'])
                ->middleware([
                    EnsureBiometricAttendanceEnabled::class,
                    'can:attendance.manage',
                    'throttle:60,1',
                ])
                ->name('face.challenge');

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

            Route::get('/leave-types', [LeaveTypeController::class, 'index'])
                ->middleware('can:settings.manage')
                ->name('leave-types.index');

            Route::get('/holidays', [WorkHolidayController::class, 'index'])
                ->middleware('can:settings.manage')->name('holidays.index');

            Route::post('/holidays', [WorkHolidayController::class, 'store'])
                ->middleware('can:settings.manage')->name('holidays.store');

            Route::delete('/holidays/{holiday}', [WorkHolidayController::class, 'destroy'])
                ->middleware('can:settings.manage')->name('holidays.destroy');

            Route::post('/leave-types', [LeaveTypeController::class, 'store'])
                ->middleware('can:settings.manage')
                ->name('leave-types.store');

            Route::put('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])
                ->middleware('can:settings.manage')
                ->name('leave-types.update');

            Route::post('/leaves', [LeaveController::class, 'store'])
                ->middleware('can:attendance.leaves.manage')
                ->name('leaves.store');

            Route::post('/leaves/carryover', [LeaveController::class, 'carryover'])
                ->middleware('can:attendance.leaves.manage')
                ->name('leaves.carryover');

            Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])
                ->middleware('can:attendance.leaves.approve')
                ->name('leaves.approve');

            Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])
                ->middleware('can:attendance.leaves.approve')
                ->name('leaves.reject');

            Route::get('/corrections', [AttendanceCorrectionController::class, 'index'])
                ->middleware('can:attendance.approve')->name('corrections.index');

            Route::post('/corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])
                ->middleware('can:attendance.approve')->name('corrections.approve');

            Route::post('/corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])
                ->middleware('can:attendance.approve')->name('corrections.reject');

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
