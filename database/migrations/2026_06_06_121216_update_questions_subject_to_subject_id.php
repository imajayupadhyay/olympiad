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
            // Drop old string column and replace with FK to subjects
            $table->dropIndex(['subject', 'class_level']);
            $table->dropColumn('subject');
            $table->foreignId('subject_id')->after('id')->constrained('subjects')->cascadeOnDelete();

            // Also drop class_level int and replace with FK to class_levels
            $table->dropColumn('class_level');
            $table->foreignId('class_level_id')->after('subject_id')->constrained('class_levels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
            $table->string('subject')->after('id');

            $table->dropForeign(['class_level_id']);
            $table->dropColumn('class_level_id');
            $table->unsignedTinyInteger('class_level')->after('subject');

            $table->index(['subject', 'class_level']);
        });
    }
};
