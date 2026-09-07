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

        Schema::create('special_working_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            // Auto-set on save from the date itself — see SpecialWorkingDay::booted().
            $table->enum('type', ['day_off', 'holiday', 'other'])->default('other');
            $table->boolean('is_payable')->default(true);
            $table->decimal('payment_amount', 10, 2)->nullable(); // null = use config default for the type
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_working_days');
    }
};
