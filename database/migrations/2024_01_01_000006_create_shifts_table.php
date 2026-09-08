<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.shifts', false)) {
            return;
        }

        Schema::create(config('attendance.table_names.shifts', 'easyattendance_shifts'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('late_grace_minutes')->default(15);
            // Weekday names this shift is OFF, e.g. ["Friday"] or ["Friday","Saturday"].
            $table->json('off_days')->nullable();
            $table->timestamps();
        });

        Schema::create(config('attendance.table_names.employee_shifts', 'easyattendance_employee_shifts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained(config('attendance.table_names.employees', 'easyattendance_employees'))->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained(config('attendance.table_names.shifts', 'easyattendance_shifts'))->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = open-ended, still current
            $table->timestamps();

            // Named explicitly — the auto-generated name overflows MySQL's
            // 64-char identifier limit once the easyattendance_ prefix is
            // applied (see config('attendance.table_names')).
            $table->index(['employee_id', 'start_date', 'end_date'], 'ea_employee_shifts_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.employee_shifts', 'easyattendance_employee_shifts'));
        Schema::dropIfExists(config('attendance.table_names.shifts', 'easyattendance_shifts'));
    }
};
