<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.summaries', false)) {
            return;
        }

        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('shift_id')->nullable();

            $table->enum('status', ['present', 'late', 'absent', 'leave', 'holiday', 'day_off'])->default('absent');

            $table->timestamp('first_in')->nullable();
            $table->timestamp('last_out')->nullable();
            $table->unsignedTinyInteger('punch_count')->default(0);

            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('ot_minutes')->default(0);

            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_day_off')->default(false);
            $table->boolean('is_on_leave')->default(false);
            $table->string('holiday_name')->nullable();

            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->string('shift_source', 20)->nullable(); // roster | default

            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
