<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('attendance.features.corrections', true)) {
            return;
        }

        Schema::create(config('attendance.table_names.attendance_corrections', 'easyattendance_corrections'), function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');

            // Plain nullable id, no FK constraint — the auth "actor" table
            // name/PK varies per app, so this stays app-agnostic.
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();

            $table->date('date');
            $table->time('requested_in')->nullable();
            $table->time('requested_out')->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            // Named explicitly — see the same note in the attendances migration.
            $table->index(['subject_type', 'subject_id', 'status'], 'ea_corrections_subject_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('attendance.table_names.attendance_corrections', 'easyattendance_corrections'));
    }
};
