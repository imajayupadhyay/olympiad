<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            // The question-bank subject this section draws from (Reasoning, Mathematics, ...).
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Optional per-section scoring; null falls back to the exam-wide scoring policy.
            $table->decimal('marks_per_question', 6, 2)->nullable();
            $table->decimal('negative_marks_per_question', 6, 2)->nullable();
            $table->timestamps();
            $table->index(['exam_id', 'sort_order']);
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->foreignId('exam_section_id')
                ->nullable()
                ->after('question_id')
                ->constrained('exam_sections')
                ->cascadeOnDelete();
        });

        // Existing exams become a single "General" section so the builder and exam room
        // treat every exam the same way.
        $now = now();
        DB::table('exams')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('exam_questions')
                ->whereColumn('exam_questions.exam_id', 'exams.id'))
            ->orderBy('id')
            ->get(['id', 'subject_id'])
            ->each(function ($exam) use ($now) {
                $sectionId = DB::table('exam_sections')->insertGetId([
                    'exam_id' => $exam->id,
                    'subject_id' => $exam->subject_id,
                    'name' => 'General',
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('exam_questions')
                    ->where('exam_id', $exam->id)
                    ->update(['exam_section_id' => $sectionId]);
            });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_section_id');
        });

        Schema::dropIfExists('exam_sections');
    }
};
