<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'submitted', 'timed_out', 'auto_submitted'])->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('time_taken_seconds')->nullable();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->unsignedSmallInteger('total_attempted')->default(0);
            $table->unsignedSmallInteger('total_correct')->default(0);
            $table->unsignedSmallInteger('total_wrong')->default(0);
            $table->unsignedSmallInteger('total_skipped')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_id']);
            $table->index(['exam_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
