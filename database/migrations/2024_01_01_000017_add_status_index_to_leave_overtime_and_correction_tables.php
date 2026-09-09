<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plain `status = 'pending'` count against the whole table — the
 * Filament admin panel's navigation badges (Leave/OvertimeRecord/
 * AttendanceCorrection resources) run exactly this query on every page
 * load. None of the three tables' existing composite indexes lead with
 * `status` (leaves: employee_id+status; corrections: subject_type+
 * subject_id+status; overtime_records: no status index at all), so none
 * of them can serve a bare status lookup — free on a small dataset, a
 * full table scan on a large one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('attendance.features.leave', false)) {
            Schema::table(config('attendance.table_names.leaves', 'easyattendance_leaves'), function (Blueprint $table) {
                $table->index('status', 'ea_leaves_status_idx');
            });
        }

        if (config('attendance.features.overtime', false)) {
            Schema::table(config('attendance.table_names.overtime_records', 'easyattendance_overtime_records'), function (Blueprint $table) {
                $table->index('status', 'ea_overtime_records_status_idx');
            });
        }

        if (config('attendance.features.corrections', true)) {
            Schema::table(config('attendance.table_names.attendance_corrections', 'easyattendance_corrections'), function (Blueprint $table) {
                $table->index('status', 'ea_corrections_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (config('attendance.features.leave', false)) {
            Schema::table(config('attendance.table_names.leaves', 'easyattendance_leaves'), function (Blueprint $table) {
                $table->dropIndex('ea_leaves_status_idx');
            });
        }

        if (config('attendance.features.overtime', false)) {
            Schema::table(config('attendance.table_names.overtime_records', 'easyattendance_overtime_records'), function (Blueprint $table) {
                $table->dropIndex('ea_overtime_records_status_idx');
            });
        }

        if (config('attendance.features.corrections', true)) {
            Schema::table(config('attendance.table_names.attendance_corrections', 'easyattendance_corrections'), function (Blueprint $table) {
                $table->dropIndex('ea_corrections_status_idx');
            });
        }
    }
};
