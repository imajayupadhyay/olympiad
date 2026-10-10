<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds a whole exam paper from one workbook: each row names its section, row order is
 * question order, and every row either creates a new Question Bank entry or reuses one
 * by its Question Bank ID. Nothing is written until commit().
 */
class ExamQuestionImportService
{
    public const MODES = ['append', 'replace'];

    private const HEADERS = [
        'Section *', 'Subject *', 'Class Levels', 'Category', 'Difficulty *', 'Question Type *',
        'Question Text *', 'Option A *', 'Option B *', 'Option C *', 'Option D *',
        'Correct Options *', 'Marks *', 'Negative Marks', 'Explanation', 'Question Bank ID',
    ];

    public function __construct(
        private QuestionBulkImportService $bank,
        private ExamSectionService $sections,
    ) {
    }

    public function template(Exam $exam): StreamedResponse
    {
        $exam->loadMissing('classLevel', 'sections.subject', 'subject');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Questions');
        $sheet->setCellValue('A1', "Exam Question Import - {$exam->name} ({$exam->exam_code})");
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A2', implode(' ', [
            'One row per question, in the order students should see them. Section groups rows into sections (in order of first appearance); every row of a section must use the same Subject.',
            "Class Levels may be left blank - {$exam->classLevel?->label} is always added.",
            'To reuse an existing bank question, fill only Section and Question Bank ID.',
            'Correct Options uses A-D; separate multiple answers with |. Row 5 is a demo and is ignored unless you replace its question text.',
        ]));
        $sheet->mergeCells('A2:P2');
        $sheet->getStyle('A2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(48);
        $sheet->fromArray(self::HEADERS, null, 'A4');
        $sheet->fromArray([$this->demoRow($exam)], null, 'A5');

        $sheet->getStyle('A1:P1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0A1024');
        $sheet->getStyle('A4:P4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:P4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEE6A2C');
        $sheet->getStyle('A5:P5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF4D6');
        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:P4');
        foreach ([22, 22, 22, 30, 14, 16, 46, 26, 26, 26, 26, 18, 10, 16, 40, 18] as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $this->bank->writeListsSheet($spreadsheet);
        foreach (['B' => 'Subjects', 'C' => 'ClassLevels', 'D' => 'Categories', 'E' => 'Difficulties', 'F' => 'QuestionTypes', 'L' => 'CorrectOptions'] as $column => $list) {
            $this->bank->addListValidation($sheet, $column, $list);
        }

        return $this->bank->download($spreadsheet, Str::slug($exam->exam_code).'-question-import.xlsx');
    }

    public function parse(UploadedFile $file, Exam $exam): array
    {
        ['rows' => $rows, 'error' => $error] = $this->bank->readRows($file, self::HEADERS);

        if ($error) {
            return ['questions' => [], 'fileErrors' => [$error]];
        }

        return [
            'questions' => $rows->map(fn (array $row, int $index) => $this->parseRow($row, $index + 1, $exam))->values()->all(),
            'fileErrors' => [],
        ];
    }

    /**
     * Attach errors, the reused bank question and any likely duplicate to every row.
     */
    public function validateRows(array $rows, Exam $exam, string $mode = 'append'): array
    {
        $rows = array_values($rows);
        $bankIds = collect($rows)->pluck('question_bank_id')->filter(fn ($id) => is_int($id))->unique();
        $bankQuestions = Question::with(['subject:id,name', 'classLevels:id,label', 'questionCategory:id,name'])
            ->whereIn('id', $bankIds)->get()->keyBy('id');
        $examQuestionIds = $exam->questions()->pluck('questions.id')->flip();
        $duplicates = $this->duplicateIndex(collect($rows)->whereNull('question_bank_id')->pluck('subject_id')->filter()->unique(), (int) $exam->class_level_id);
        $categoryScope = $exam->question_category_id
            ? collect(QuestionCategory::find($exam->question_category_id)?->idsWithDescendants() ?? [])
            : null;
        $classLabel = $exam->classLevel?->label ?? 'this class';

        $seenBankIds = [];
        $sectionSubjects = [];

        $rows = collect($rows)->map(function (array $row) use ($exam, $mode, $bankQuestions, $examQuestionIds, $duplicates, $categoryScope, $classLabel, &$seenBankIds, &$sectionSubjects) {
            $errors = [];
            $row['bank_question'] = null;
            $row['duplicate_of'] = null;
            $row['in_exam'] = false;
            $row['section'] = trim((string) ($row['section'] ?? ''));

            if ($row['section'] === '') {
                $errors['section'] = 'Enter the section this question belongs to.';
            } elseif (Str::length($row['section']) > 120) {
                $errors['section'] = 'Section names can be at most 120 characters.';
            }

            if (($row['question_bank_id'] ?? null) !== null) {
                $question = is_int($row['question_bank_id']) ? $bankQuestions->get($row['question_bank_id']) : null;
                $subjectId = $question?->subject_id;

                if (! $question) {
                    $errors['question_bank_id'] = 'No question with this ID exists in the Question Bank.';
                } elseif (! $question->is_active) {
                    $errors['question_bank_id'] = 'This bank question is inactive.';
                } elseif (! $question->classLevels->contains('id', $exam->class_level_id)) {
                    $errors['question_bank_id'] = "This bank question is not offered to {$classLabel}.";
                } elseif (isset($seenBankIds[$question->id])) {
                    $errors['question_bank_id'] = 'This bank question is already used in another row.';
                } elseif ($examQuestionIds->has($question->id) && $mode === 'append') {
                    $errors['question_bank_id'] = 'This question is already in the exam.';
                }

                if ($question) {
                    $row['in_exam'] = $examQuestionIds->has($question->id);
                    $seenBankIds[$question->id] = true;
                    $row['bank_question'] = $this->sections->questionPayload($question);
                }
            } else {
                $subjectId = filled($row['subject_id'] ?? null) ? (int) $row['subject_id'] : null;
                $errors = [...$errors, ...$this->bank->questionErrors([...$row, 'is_active' => true])];

                if ($categoryScope && $subjectId === (int) $exam->subject_id && ! isset($errors['question_category_id'])
                    && ! $categoryScope->contains((int) ($row['question_category_id'] ?: 0))) {
                    $errors['question_category_id'] = 'Choose a category inside this exam\'s category scope.';
                }
                if (! in_array((int) $exam->class_level_id, array_map('intval', $row['class_level_ids'] ?? []), true)) {
                    $errors['class_level_ids'] = "Include {$classLabel}, the class this exam is for.";
                }

                $row['duplicate_of'] = $duplicates[$subjectId][$this->normalize($row['question_text'] ?? '')] ?? null;
            }

            // Every row of a section must draw from the same bank subject.
            $sectionKey = Str::lower($row['section']);
            if ($row['section'] !== '' && $subjectId) {
                $sectionSubjects[$sectionKey] ??= $subjectId;
                if ($sectionSubjects[$sectionKey] !== $subjectId) {
                    $errors[$row['bank_question'] ? 'section' : 'subject_id'] = "Section \"{$row['section']}\" already uses another subject. Every question in a section must come from the same subject.";
                }
            }

            $row['errors'] = $errors;

            return $row;
        });

        // Full payloads for likely duplicates so the review page can offer "use existing instead".
        $duplicates = Question::with(['subject:id,name', 'classLevels:id,label', 'questionCategory:id,name'])
            ->whereIn('id', $rows->pluck('duplicate_of')->filter()->unique())->get()->keyBy('id');

        return $rows->map(fn (array $row) => [
            ...$row,
            'duplicate_question' => $row['duplicate_of'] ? $this->sections->questionPayload($duplicates->get($row['duplicate_of'])) : null,
        ])->all();
    }

    /**
     * @return array{created: int, reused: int, sections: int}
     */
    public function commit(Exam $exam, array $rows, string $mode, int $createdBy): array
    {
        $rows = $this->validateRows($rows, $exam, $mode);
        $messages = [];
        foreach ($rows as $index => $row) {
            foreach ($row['errors'] as $field => $message) {
                $messages["questions.{$index}.{$field}"] = $message;
            }
        }
        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return DB::transaction(function () use ($exam, $rows, $mode, $createdBy) {
            $groups = [];
            $created = 0;

            foreach ($rows as $row) {
                if ($row['bank_question']) {
                    $questionId = $row['bank_question']['id'];
                    $subjectId = $row['bank_question']['subject_id'];
                } else {
                    $data = $this->bank->questionAttributes([...$row, 'is_active' => true]);
                    $classLevelIds = $data['class_level_ids'];
                    unset($data['class_level_ids']);
                    $question = Question::create([...$data, 'created_by' => $createdBy]);
                    $question->classLevels()->sync($classLevelIds);
                    $questionId = $question->id;
                    $subjectId = $question->subject_id;
                    $created++;
                }

                $key = Str::lower($row['section']);
                $groups[$key] ??= ['name' => $row['section'], 'subject_id' => (int) $subjectId, 'question_ids' => []];
                $groups[$key]['question_ids'][] = $questionId;
            }

            $structure = $mode === 'replace' ? [] : $this->sections->currentSections($exam);
            foreach ($groups as $key => $group) {
                $index = collect($structure)->search(fn (array $section) => Str::lower($section['name']) === $key
                    && (int) $section['subject_id'] === $group['subject_id']);

                if ($index === false) {
                    $structure[] = [
                        'id' => null,
                        'name' => $group['name'],
                        'subject_id' => $group['subject_id'],
                        'instructions' => null,
                        'marks_per_question' => null,
                        'negative_marks_per_question' => null,
                        'question_ids' => $group['question_ids'],
                    ];
                } else {
                    $structure[$index]['question_ids'] = [...$structure[$index]['question_ids'], ...$group['question_ids']];
                }
            }

            $this->sections->assertFits($exam, $structure);
            $this->sections->sync($exam, $structure);
            $exam->update(['updated_by' => $createdBy]);

            return ['created' => $created, 'reused' => count($rows) - $created, 'sections' => count($groups)];
        });
    }

    private function parseRow(array $row, int $sourceRow, Exam $exam): array
    {
        [$section, $subject, $classes, $category, $difficulty, $type, $text, $a, $b, $c, $d, $correct, $marks, $negative, $explanation, $bankId] = array_pad($row, 16, '');
        $subjectId = Subject::where('is_active', true)->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $subject))])->value('id');
        $classLevelIds = collect([(int) $exam->class_level_id, ...$this->bank->classLevelIds((string) $classes)])->unique()->values()->all();
        $bankId = trim((string) $bankId);

        return [
            'source_row' => $sourceRow,
            'section' => trim((string) $section),
            'question_bank_id' => $bankId === '' ? null : (ctype_digit($bankId) ? (int) $bankId : $bankId),
            'subject_id' => $subjectId ?: '',
            'class_level_ids' => $classLevelIds,
            'question_category_id' => $this->bank->categoryId((string) $category, $subjectId) ?: '',
            'difficulty' => Str::lower(trim((string) $difficulty)),
            'question_type' => Str::lower(trim((string) $type)),
            'question_text' => trim((string) $text),
            'option_a' => trim((string) $a),
            'option_b' => trim((string) $b),
            'option_c' => trim((string) $c),
            'option_d' => trim((string) $d),
            'correct_options' => $this->bank->correctOptions((string) $correct),
            'marks' => is_numeric($marks) ? (int) $marks : $marks,
            'negative_marks' => is_numeric($negative) ? (float) $negative : (trim((string) $negative) === '' ? null : $negative),
            'explanation' => trim((string) $explanation),
            'errors' => [],
        ];
    }

    private function demoRow(Exam $exam): array
    {
        $section = $exam->sections->first();
        $subject = $section?->subject ?? $exam->subject;

        return [
            $section?->name ?? 'Section 1',
            $subject?->name ?? '',
            $exam->classLevel?->label ?? '',
            '',
            'medium',
            'single',
            QuestionBulkImportService::DEMO_TEXT,
            'Option A',
            'Option B',
            'Option C',
            'Option D',
            'A',
            1,
            0,
            'Replace this optional explanation.',
            '',
        ];
    }

    /**
     * subject_id => [normalized question text => bank question id] for duplicate warnings. Only questions
     * this exam could actually reuse (active, offered to its class) are offered as the original.
     */
    private function duplicateIndex(Collection $subjectIds, int $classLevelId): array
    {
        $index = [];
        Question::whereIn('subject_id', $subjectIds)
            ->where('is_active', true)
            ->whereHas('classLevels', fn ($query) => $query->where('class_levels.id', $classLevelId))
            ->select(['id', 'subject_id', 'question_text'])->orderBy('id')
            ->each(function (Question $question) use (&$index) {
                $index[$question->subject_id][$this->normalize($question->question_text)] ??= $question->id;
            });

        return $index;
    }

    private function normalize(string $text): string
    {
        return (string) Str::of(html_entity_decode(strip_tags($text)))->squish()->lower();
    }
}
