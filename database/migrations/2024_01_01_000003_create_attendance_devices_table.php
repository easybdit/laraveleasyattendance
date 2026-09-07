<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.device_sync', false)) {
            return;
        }

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Pull mode: connect out to the device over the local network.
            $table->string('ip')->nullable();
            $table->unsignedInteger('port')->nullable()->default(4370);
            $table->string('comm_key')->nullable();

            // Push mode (ZKTeco "Cloud Server / ADMS"): the device dials
            // home to us, identified purely by serial number.
            $table->string('serial_number')->nullable()->unique();

            $table->string('model')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_seen_at')->nullable(); // push heartbeat
            $table->unsignedInteger('sync_fail_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
