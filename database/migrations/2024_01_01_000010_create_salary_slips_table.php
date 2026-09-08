<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.salary', false)) {
            return;
        }

        Schema::create(config('attendance.table_names.salary_slips', 'easyattendance_salary_slips'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained(config('attendance.table_names.employees', 'easyattendance_employees'))->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Snapshot at generation time — a later raise must never
            // silently reshape an already-generated slip.
            $table->decimal('basic_salary', 12, 2);
            $table->json('allowances')->nullable();

            $table->unsignedTinyInteger('present_days')->default(0);
            $table->unsignedTinyInteger('absent_days')->default(0);
            $table->unsignedTinyInteger('late_days')->default(0);
            $table->unsignedTinyInteger('leave_days')->default(0);

            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // Named explicitly — see the note in the shifts migration.
            $table->unique(['employee_id', 'year', 'month'], 'ea_salary_slips_employee_period_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.salary_slips', 'easyattendance_salary_slips'));
    }
};
