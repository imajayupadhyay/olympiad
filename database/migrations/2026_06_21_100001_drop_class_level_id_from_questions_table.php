<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['class_level_id']);
            $table->dropColumn('class_level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('class_level_id')->nullable()->after('subject_id')
                ->constrained('class_levels')->cascadeOnDelete();
        });

        // Lossy by necessity (many classes -> one column): take the lowest class_level_id per question.
        DB::table('question_class_levels')
            ->select('question_id', DB::raw('MIN(class_level_id) as class_level_id'))
            ->groupBy('question_id')
            ->get()
            ->each(fn ($row) => DB::table('questions')->where('id', $row->question_id)->update(['class_level_id' => $row->class_level_id]));
    }
};
