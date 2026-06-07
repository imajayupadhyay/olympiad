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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->unsignedTinyInteger('class_level');
            $table->string('difficulty')->default('medium');
            $table->string('topic')->nullable();
            $table->text('question_text');
            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');
            $table->char('correct_option', 1);
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('marks')->default(1);
            $table->decimal('negative_marks', 4, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['subject', 'class_level']);
            $table->index('difficulty');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
