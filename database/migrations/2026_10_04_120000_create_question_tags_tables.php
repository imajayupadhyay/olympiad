<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained('class_levels')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subject_id', 'class_level_id', 'slug']);
            $table->index(['class_level_id', 'subject_id', 'is_active']);
        });

        Schema::create('question_question_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('question_tag_id')->constrained('question_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'question_tag_id']);
            $table->index('question_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_question_tag');
        Schema::dropIfExists('question_tags');
    }
};
