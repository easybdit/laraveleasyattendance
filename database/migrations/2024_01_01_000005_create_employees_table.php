<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.employees', false)) {
            return;
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('designation')->nullable();

            // Device PIN — same column device_sync matches punches against
            // by default (config('attendance.device_sync.pin_column')).
            $table->string('device_user_id')->nullable()->unique();

            $table->decimal('basic_salary', 12, 2)->default(0);
            // Named allowances (house_rent, medical, transport, ...) as a
            // flexible map instead of one fixed column per allowance type —
            // every org's pay structure names these differently.
            $table->json('allowances')->nullable();

            $table->date('joined_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
