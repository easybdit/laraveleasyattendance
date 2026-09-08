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

        Schema::table(config('attendance.table_names.salary_slips', 'easyattendance_salary_slips'), function (Blueprint $table) {
            $table->decimal('overtime_hours', 6, 2)->default(0)->after('leave_days');
            $table->decimal('overtime_amount', 10, 2)->default(0)->after('overtime_hours');
            $table->decimal('special_pay_amount', 10, 2)->default(0)->after('overtime_amount');
        });
    }

    public function down(): void
    {
        Schema::table(config('attendance.table_names.salary_slips', 'easyattendance_salary_slips'), function (Blueprint $table) {
            $table->dropColumn(['overtime_hours', 'overtime_amount', 'special_pay_amount']);
        });
    }
};
