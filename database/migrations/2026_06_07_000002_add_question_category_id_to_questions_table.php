<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('question_category_id')
                ->nullable()
                ->after('class_level_id')
                ->constrained('question_categories')
                ->nullOnDelete();

            $table->index(['question_category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['question_category_id', 'is_active']);
            $table->dropConstrainedForeignId('question_category_id');
        });
    }
};
