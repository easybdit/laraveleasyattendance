<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.special_working_days', false)) {
            return;
        }

        Schema::create(config('attendance.table_names.special_working_days', 'easyattendance_special_working_days'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained(config('attendance.table_names.employees', 'easyattendance_employees'))->cascadeOnDelete();
            $table->date('date');
            // Auto-set on save from the date itself — see SpecialWorkingDay::booted().
            $table->enum('type', ['day_off', 'holiday', 'other'])->default('other');
            $table->boolean('is_payable')->default(true);
            $table->decimal('payment_amount', 10, 2)->nullable(); // null = use config default for the type
            $table->string('note')->nullable();
            $table->timestamps();

            // Named explicitly — see the note in the shifts migration.
            $table->unique(['employee_id', 'date'], 'ea_special_working_days_employee_date_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.special_working_days', 'easyattendance_special_working_days'));
    }
};
