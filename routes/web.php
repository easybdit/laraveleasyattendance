<?php

use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceCorrectionController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceDeviceController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceReportController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\EmployeeController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\LeaveController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::post('/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
Route::post('/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
Route::get('/today', [AttendanceController::class, 'today'])->name('attendance.today');

if (config('attendance.features.corrections', true)) {
    Route::get('/corrections', [AttendanceCorrectionController::class, 'index'])->name('attendance.corrections.index');
    Route::post('/corrections', [AttendanceCorrectionController::class, 'store'])->name('attendance.corrections.store');

    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->group(function () {
        Route::post('/corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('attendance.corrections.approve');
        Route::post('/corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('attendance.corrections.reject');
    });
}

if (config('attendance.features.device_sync', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->prefix('devices')->group(function () {
        Route::get('/', [AttendanceDeviceController::class, 'index'])->name('attendance.devices.index');
        Route::post('/', [AttendanceDeviceController::class, 'store'])->name('attendance.devices.store');
        Route::put('/{device}', [AttendanceDeviceController::class, 'update'])->name('attendance.devices.update');
        Route::delete('/{device}', [AttendanceDeviceController::class, 'destroy'])->name('attendance.devices.destroy');
        Route::post('/{device}/test', [AttendanceDeviceController::class, 'test'])->name('attendance.devices.test');
        Route::post('/{device}/pull', [AttendanceDeviceController::class, 'pull'])->name('attendance.devices.pull');
    });
}

if (config('attendance.features.employees', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('attendance.employees.index');
        Route::post('/', [EmployeeController::class, 'store'])->name('attendance.employees.store');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->name('attendance.employees.show');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('attendance.employees.update');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('attendance.employees.destroy');

        if (config('attendance.features.shifts', false)) {
            Route::get('/{employee}/schedule', [EmployeeController::class, 'schedule'])->name('attendance.employees.schedule.index');
            Route::post('/{employee}/schedule', [EmployeeController::class, 'assignSchedule'])->name('attendance.employees.schedule.assign');
        }

        if (config('attendance.features.leave', false)) {
            Route::get('/{employee}/leaves', [LeaveController::class, 'index'])->name('attendance.employees.leaves.index');
            Route::post('/{employee}/leaves', [LeaveController::class, 'store'])->name('attendance.employees.leaves.store');
        }
    });

    if (config('attendance.features.leave', false)) {
        Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->group(function () {
            Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('attendance.leaves.approve');
            Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('attendance.leaves.reject');
        });
    }
}

if (config('attendance.features.shifts', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->prefix('shifts')->group(function () {
        Route::get('/', [ShiftController::class, 'index'])->name('attendance.shifts.index');
        Route::post('/', [ShiftController::class, 'store'])->name('attendance.shifts.store');
        Route::put('/{shift}', [ShiftController::class, 'update'])->name('attendance.shifts.update');
        Route::delete('/{shift}', [ShiftController::class, 'destroy'])->name('attendance.shifts.destroy');
    });
}

if (config('attendance.features.summaries', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->prefix('reports')->group(function () {
        Route::get('/daily', [AttendanceReportController::class, 'daily'])->name('attendance.reports.daily');
        Route::get('/monthly', [AttendanceReportController::class, 'monthly'])->name('attendance.reports.monthly');
        Route::get('/employee/{employee}', [AttendanceReportController::class, 'employeeWise'])->name('attendance.reports.employee');

        if (config('attendance.features.salary', false)) {
            Route::get('/salary', [AttendanceReportController::class, 'salary'])->name('attendance.reports.salary');
        }
    });
}
