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
        Schema::create('question_class_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_id', 'class_level_id']);
        });

        DB::table('questions')->select('id', 'class_level_id')->whereNotNull('class_level_id')->orderBy('id')
            ->chunkById(500, function ($questions) {
                DB::table('question_class_levels')->insert($questions->map(fn ($q) => [
                    'question_id' => $q->id,
                    'class_level_id' => $q->class_level_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_class_levels');
    }
};
