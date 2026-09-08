<?php

use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceCorrectionController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceDeviceController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendancePrintController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceReportController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\EmployeeController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\HolidayController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\LeaveController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\LeaveTypeController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\OvertimeController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\ShiftController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\SpecialWorkingDayController;
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
        Route::post('/import', [EmployeeController::class, 'import'])->name('attendance.employees.import');
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

        if (config('attendance.features.overtime', false)) {
            Route::get('/{employee}/overtime', [OvertimeController::class, 'index'])->name('attendance.employees.overtime.index');
        }

        if (config('attendance.features.special_working_days', false)) {
            Route::get('/{employee}/special-working-days', [SpecialWorkingDayController::class, 'index'])->name('attendance.employees.special-working-days.index');
            Route::post('/{employee}/special-working-days', [SpecialWorkingDayController::class, 'store'])->name('attendance.employees.special-working-days.store');
        }
    });

    if (config('attendance.features.leave', false)) {
        Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->group(function () {
            Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('attendance.leaves.approve');
            Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('attendance.leaves.reject');
        });
    }

    if (config('attendance.features.overtime', false)) {
        Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->group(function () {
            Route::post('/overtime/{overtime}/approve', [OvertimeController::class, 'approve'])->name('attendance.overtime.approve');
            Route::post('/overtime/{overtime}/reject', [OvertimeController::class, 'reject'])->name('attendance.overtime.reject');
        });
    }

    if (config('attendance.features.special_working_days', false)) {
        Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->group(function () {
            Route::put('/special-working-days/{specialWorkingDay}', [SpecialWorkingDayController::class, 'update'])->name('attendance.special-working-days.update');
            Route::delete('/special-working-days/{specialWorkingDay}', [SpecialWorkingDayController::class, 'destroy'])->name('attendance.special-working-days.destroy');
        });
    }
}

if (config('attendance.features.holidays', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->prefix('holidays')->group(function () {
        Route::get('/', [HolidayController::class, 'index'])->name('attendance.holidays.index');
        Route::post('/', [HolidayController::class, 'store'])->name('attendance.holidays.store');
        Route::put('/{holiday}', [HolidayController::class, 'update'])->name('attendance.holidays.update');
        Route::delete('/{holiday}', [HolidayController::class, 'destroy'])->name('attendance.holidays.destroy');
    });
}

if (config('attendance.features.leave', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))->prefix('leave-types')->group(function () {
        Route::get('/', [LeaveTypeController::class, 'index'])->name('attendance.leave-types.index');
        Route::post('/', [LeaveTypeController::class, 'store'])->name('attendance.leave-types.store');
        Route::put('/{leaveType}', [LeaveTypeController::class, 'update'])->name('attendance.leave-types.update');
        Route::delete('/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('attendance.leave-types.destroy');
    });
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
        Route::get('/monthly/print', [AttendancePrintController::class, 'monthlyReport'])->name('attendance.reports.monthly.print');

        if (config('attendance.features.salary', false)) {
            Route::get('/salary', [AttendanceReportController::class, 'salary'])->name('attendance.reports.salary');
        }
    });
}

if (config('attendance.features.salary', false)) {
    Route::middleware(config('attendance.routes.review_middleware', ['web', 'auth']))
        ->get('/salary/{slip}/print', [AttendancePrintController::class, 'payslip'])
        ->name('attendance.salary.print');
}
