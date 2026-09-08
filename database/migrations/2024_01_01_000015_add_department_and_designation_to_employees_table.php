<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.departments', false) || ! config('attendance.features.employees', false)) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            // Alongside, not replacing, the existing plain `designation`
            // string column — that stays for anyone who just wants free
            // text without setting up the Department/Designation models.
            $table->foreignId('department_id')->nullable()->after('designation')->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('designation_id');
        });
    }
};
