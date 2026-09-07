<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.overtime', false)) {
            return;
        }

        Schema::create('overtime_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');

            $table->time('shift_end_time')->nullable();
            $table->time('actual_out_time')->nullable();

            $table->decimal('ot_hours', 5, 2)->default(0);
            $table->decimal('ot_rate', 10, 4)->default(0); // per-hour rate at detection time
            $table->decimal('ot_amount', 10, 2)->default(0); // ot_hours × ot_rate

            $table->enum('source', ['auto', 'manual'])->default('auto');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('note')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_records');
    }
};
