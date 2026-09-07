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

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->after('subject_id');
            $table->string('device_user_id')->nullable()->after('device_id'); // raw PIN from the device

            // Prevents importing the same punch twice — a device can be
            // pulled AND push the same backlog, or a pull scheduled command
            // can overlap a manual "Sync now" click.
            $table->unique(['device_id', 'device_user_id', 'time'], 'attendances_device_punch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_device_punch_unique');
            $table->dropColumn(['device_id', 'device_user_id']);
        });
    }
};
