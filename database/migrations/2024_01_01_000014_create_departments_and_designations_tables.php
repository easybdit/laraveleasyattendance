<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.departments', false)) {
            return;
        }

        Schema::create(config('attendance.table_names.departments', 'easyattendance_departments'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create(config('attendance.table_names.designations', 'easyattendance_designations'), function (Blueprint $table) {
            $table->id();
            // Nullable — a designation can be org-wide (e.g. "Manager")
            // rather than tied to one department.
            $table->foreignId('department_id')->nullable()->constrained(config('attendance.table_names.departments', 'easyattendance_departments'))->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.designations', 'easyattendance_designations'));
        Schema::dropIfExists(config('attendance.table_names.departments', 'easyattendance_departments'));
    }
};
