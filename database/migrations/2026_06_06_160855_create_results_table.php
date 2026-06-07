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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('percentile', 5, 2)->nullable();
            $table->unsignedInteger('rank_national')->nullable();
            $table->unsignedInteger('rank_state')->nullable();
            $table->unsignedInteger('rank_city')->nullable();
            $table->unsignedInteger('rank_school')->nullable();
            $table->string('grade')->nullable();
            $table->boolean('is_released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->decimal('score_override', 8, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'exam_id']);
            $table->index(['exam_id', 'is_released']);
            $table->index(['exam_id', 'rank_national']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
