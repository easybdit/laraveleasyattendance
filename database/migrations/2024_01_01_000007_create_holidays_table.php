<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.holidays', false)) {
            return;
        }

        Schema::create(config('attendance.table_names.holidays', 'easyattendance_holidays'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            // Repeats every year on the same month/day (e.g. a fixed
            // national holiday) without re-entering it annually.
            $table->boolean('is_recurring_yearly')->default(false);
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.holidays', 'easyattendance_holidays'));
    }
};
