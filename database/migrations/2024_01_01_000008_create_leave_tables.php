<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.leave', false)) {
            return;
        }

        Schema::create(config('attendance.table_names.leave_types', 'easyattendance_leave_types'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('days_allowed_per_year')->nullable();
            $table->timestamps();
        });

        Schema::create(config('attendance.table_names.leaves', 'easyattendance_leaves'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained(config('attendance.table_names.employees', 'easyattendance_employees'))->cascadeOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained(config('attendance.table_names.leave_types', 'easyattendance_leave_types'))->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            // Named explicitly — see the note in the shifts migration.
            $table->index(['employee_id', 'status'], 'ea_leaves_employee_status_idx');
            $table->index(['start_date', 'end_date'], 'ea_leaves_date_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.leaves', 'easyattendance_leaves'));
        Schema::dropIfExists(config('attendance.table_names.leave_types', 'easyattendance_leave_types'));
    }
};
