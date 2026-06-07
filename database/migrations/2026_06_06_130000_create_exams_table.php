<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('exam_code')->unique();
            $table->text('description')->nullable();
            $table->text('syllabus')->nullable();
            $table->text('eligibility')->nullable();
            $table->text('instructions')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->string('fee_currency', 3)->default('INR');
            $table->string('scoring_mode')->default('question_bank');
            $table->decimal('marks_per_question', 6, 2)->default(1);
            $table->boolean('negative_marking_enabled')->default(false);
            $table->decimal('negative_marks_per_question', 6, 2)->default(0);
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_options')->default(false);
            $table->boolean('show_result_immediately')->default(true);
            $table->dateTime('result_release_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['subject_id', 'class_level_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
