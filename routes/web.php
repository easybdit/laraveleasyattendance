<?php

use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceCorrectionController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceDeviceController;
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
