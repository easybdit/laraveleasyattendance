<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EmployeeController::index()'s ?status= filter and
 * AttendanceSummaryService::buildForDate()'s Employee::where('status',
 * 'active') both do a plain equality lookup on this column — free on a
 * small roster, a real full scan on a large one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.employees', false)) {
            return;
        }

        Schema::table(config('attendance.table_names.employees', 'easyattendance_employees'), function (Blueprint $table) {
            $table->index('status', 'ea_employees_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('attendance.table_names.employees', 'easyattendance_employees'), function (Blueprint $table) {
            $table->dropIndex('ea_employees_status_idx');
        });
    }
};
