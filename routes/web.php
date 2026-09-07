<?php

use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceController;
use Easybdit\LaravelEasyAttendance\Http\Controllers\AttendanceCorrectionController;
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
