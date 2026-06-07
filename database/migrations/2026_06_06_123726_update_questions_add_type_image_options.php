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
        Schema::table('questions', function (Blueprint $table) {
            // Drop old single-char correct_option
            $table->dropColumn('correct_option');

            // Question type: single correct or multiple correct
            $table->string('question_type')->default('single')->after('option_d');
            // Correct options stored as JSON array: ['a'] or ['a','c']
            $table->json('correct_options')->after('question_type');
            // Optional image attached to the question
            $table->string('question_image')->nullable()->after('question_text');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->char('correct_option', 1)->after('option_d');
            $table->dropColumn(['question_type', 'correct_options', 'question_image']);
        });
    }
};
