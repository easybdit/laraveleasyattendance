<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // Polymorphic so this works against ANY subject model
            // (User, Employee, Staff, ...) without needing a matching
            // foreign key / table name at install time.
            $table->morphs('subject');

            $table->timestamp('time');
            $table->enum('type', ['check_in', 'check_out']);
            $table->string('source', 20)->default('manual'); // manual | device | api
            $table->boolean('is_manual')->default(true);
            $table->json('meta')->nullable(); // free-form: device id, ip, note, ...
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
