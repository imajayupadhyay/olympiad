<?php

namespace App\Services;

use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionBulkImportService
{
    public const MAX_ROWS = 500;

    private const DEMO_TEXT = '[DEMO] Replace this with your question before importing.';

    private const HEADERS = [
        'Subject *', 'Class Levels *', 'Category', 'Difficulty *', 'Question Type *',
        'Question Text *', 'Option A *', 'Option B *', 'Option C *', 'Option D *',
        'Correct Options *', 'Marks *', 'Negative Marks', 'Explanation', 'Active *',
    ];

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Questions');
        $sheet->setCellValue('A1', 'National Olympiad Hunt - Bulk Question Import');
        $sheet->mergeCells('A1:O1');
        $sheet->setCellValue('A2', 'Fields marked * are required. Row 5 is a live demo based on your current setup and is ignored unless you replace its demo question text. Class Levels accepts one dropdown value or multiple values separated by | (example: Class 5|Class 6). Correct Options uses A, B, C or D; use | for multiple-correct questions.');
        $sheet->mergeCells('A2:O2');
        $sheet->getStyle('A2')->getAlignment()->setWrapText(true);
        $sheet->fromArray(self::HEADERS, null, 'A4');
        $sheet->fromArray([$this->demoRow()], null, 'A5');

        $sheet->getStyle('A1:O1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0A1024');
        $sheet->getStyle('A4:O4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:O4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEE6A2C');
        $sheet->getStyle('A5:O5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF4D6');
        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:O4');
        $sheet->getRowDimension(2)->setRowHeight(36);

        foreach ([22, 24, 34, 14, 18, 46, 28, 28, 28, 28, 20, 12, 18, 42, 12] as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $lists = $spreadsheet->createSheet();
        $lists->setTitle('Lists');
        $lists->fromArray([
            ['Subjects', 'Class Levels', 'Categories', 'Difficulties', 'Question Types', 'Correct Options', 'Active'],
        ], null, 'A1');
        $listRows = $this->listRows();
        $lists->fromArray($listRows, null, 'A2');
        $lastRow = count($listRows) + 1;
        foreach (['Subjects', 'ClassLevels', 'Categories', 'Difficulties', 'QuestionTypes', 'CorrectOptions', 'ActiveValues'] as $index => $name) {
            $column = chr(65 + $index);
            $spreadsheet->addNamedRange(new NamedRange($name, $lists, '$'.$column.'$2:$'.$column.'$'.$lastRow));
        }
        $lists->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $this->addValidation($sheet, 'A5:A'.(self::MAX_ROWS + 5), 'Subjects');
        $this->addValidation($sheet, 'B5:B'.(self::MAX_ROWS + 5), 'ClassLevels');
        $this->addValidation($sheet, 'C5:C'.(self::MAX_ROWS + 5), 'Categories');
        $this->addValidation($sheet, 'D5:D'.(self::MAX_ROWS + 5), 'Difficulties');
        $this->addValidation($sheet, 'E5:E'.(self::MAX_ROWS + 5), 'QuestionTypes');
        $this->addValidation($sheet, 'K5:K'.(self::MAX_ROWS + 5), 'CorrectOptions');
        $this->addValidation($sheet, 'O5:O'.(self::MAX_ROWS + 5), 'ActiveValues');
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'question-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function parse(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray('', true, true, false);
        $headers = array_map(fn ($value) => trim((string) $value), array_slice($rows[3] ?? [], 0, count(self::HEADERS)));

        if ($headers !== self::HEADERS) {
            return ['questions' => [], 'fileErrors' => ['Use the current Question Import Template. Its required columns were not found.']];
        }

        $filledRows = collect(array_slice($rows, 4, null, true))
            ->map(fn (array $row) => array_slice($row, 0, count(self::HEADERS)))
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''))
            ->reject(fn (array $row) => trim((string) ($row[5] ?? '')) === self::DEMO_TEXT);

        if ($filledRows->count() > self::MAX_ROWS) {
            return ['questions' => [], 'fileErrors' => ['A workbook can contain at most '.self::MAX_ROWS.' question rows. Split the remaining questions into another upload.']];
        }

        $questions = [];
        foreach ($filledRows as $index => $row) {
            $questions[] = $this->parseRow($row, $index + 1);
        }

        if (count($questions) === 0) {
            return ['questions' => [], 'fileErrors' => ['The workbook does not contain any question rows.']];
        }

        return ['questions' => $questions, 'fileErrors' => []];
    }

    public function validateRows(array $questions): array
    {
        return collect($questions)->map(function (array $question, int $index): array {
            $question['errors'] = $this->rowErrors($question);
            $question['source_row'] = $question['source_row'] ?? $index + 1;

            return $question;
        })->all();
    }

    public function commit(array $questions, int $createdBy): int
    {
        $questions = $this->validateRows($questions);
        $hasErrors = collect($questions)->contains(fn (array $question) => $question['errors'] !== []);

        if ($hasErrors) {
            $messages = [];
            foreach ($questions as $index => $question) {
                foreach ($question['errors'] as $field => $message) {
                    $messages["questions.{$index}.{$field}"] = $message;
                }
            }

            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }

        return DB::transaction(function () use ($questions, $createdBy): int {
            foreach ($questions as $question) {
                $data = $this->attributes($question);
                $classLevelIds = $data['class_level_ids'];
                unset($data['class_level_ids']);
                $data['created_by'] = $createdBy;

                $record = Question::create($data);
                $record->classLevels()->sync($classLevelIds);
            }

            return count($questions);
        });
    }

    private function parseRow(array $row, int $sourceRow): array
    {
        [$subject, $classes, $category, $difficulty, $type, $text, $a, $b, $c, $d, $correct, $marks, $negative, $explanation, $active] = array_pad($row, 15, '');
        $subjectId = Subject::where('is_active', true)->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $subject))])->value('id');
        $classLevelIds = $this->classLevelIds((string) $classes);
        $categoryId = $this->categoryId((string) $category, $subjectId);

        return [
            'source_row' => $sourceRow,
            'subject_id' => $subjectId ?: '',
            'class_level_ids' => $classLevelIds,
            'question_category_id' => $categoryId ?: '',
            'difficulty' => Str::lower(trim((string) $difficulty)),
            'question_type' => Str::lower(trim((string) $type)),
            'question_text' => trim((string) $text),
            'option_a' => trim((string) $a),
            'option_b' => trim((string) $b),
            'option_c' => trim((string) $c),
            'option_d' => trim((string) $d),
            'correct_options' => $this->correctOptions((string) $correct),
            'marks' => is_numeric($marks) ? (int) $marks : $marks,
            'negative_marks' => is_numeric($negative) ? (float) $negative : (trim((string) $negative) === '' ? null : $negative),
            'explanation' => trim((string) $explanation),
            'is_active' => in_array(Str::lower(trim((string) $active)), ['yes', '1', 'true'], true),
            'errors' => [],
        ];
    }

    private function rowErrors(array $question): array
    {
        $validator = Validator::make($question, [
            'subject_id' => ['required', 'exists:subjects,id'],
            'class_level_ids' => ['required', 'array', 'min:1'],
            'class_level_ids.*' => ['integer', 'distinct', 'exists:class_levels,id'],
            'question_category_id' => ['nullable', 'exists:question_categories,id'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_type' => ['required', 'in:single,multiple'],
            'question_text' => ['required', 'string'],
            'option_a' => ['required', 'string'],
            'option_b' => ['required', 'string'],
            'option_c' => ['required', 'string'],
            'option_d' => ['required', 'string'],
            'correct_options' => ['required', 'array', 'min:1'],
            'correct_options.*' => ['in:a,b,c,d'],
            'marks' => ['required', 'integer', 'min:1', 'max:10'],
            'negative_marks' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'is_active' => ['boolean'],
        ]);

        $errors = $validator->errors()->toArray();
        if (filled($question['question_category_id'] ?? null) && ! QuestionCategory::whereKey($question['question_category_id'])
            ->where('subject_id', $question['subject_id'] ?? null)->exists()) {
            $errors['question_category_id'][] = 'Category must belong to the selected subject.';
        }
        if (($question['question_type'] ?? null) === 'single' && count($question['correct_options'] ?? []) !== 1) {
            $errors['correct_options'][] = 'Single-correct questions need exactly one correct option.';
        }

        return collect($errors)->map(fn (array $messages) => $messages[0])->all();
    }

    private function attributes(array $question): array
    {
        return [
            'subject_id' => $question['subject_id'],
            'question_category_id' => $question['question_category_id'] ?: null,
            'class_level_ids' => $question['class_level_ids'],
            'difficulty' => $question['difficulty'],
            'question_type' => $question['question_type'],
            'question_text' => $this->html($question['question_text']),
            'option_a' => $this->html($question['option_a']),
            'option_b' => $this->html($question['option_b']),
            'option_c' => $this->html($question['option_c']),
            'option_d' => $this->html($question['option_d']),
            'correct_options' => $question['correct_options'],
            'explanation' => filled($question['explanation']) ? $this->html($question['explanation']) : null,
            'marks' => $question['marks'],
            'negative_marks' => filled($question['negative_marks']) ? $question['negative_marks'] : 0,
            'is_active' => $question['is_active'],
        ];
    }

    private function listRows(): array
    {
        $subjects = Subject::active()->pluck('name')->all();
        $classes = ClassLevel::active()->pluck('label')->all();
        $categories = QuestionCategory::active()->with('subject:id,name')->get()
            ->map(fn (QuestionCategory $category) => "{$category->subject->name}: {$category->name}")->all();
        $lists = [$subjects, $classes, $categories, ['easy', 'medium', 'hard'], ['single', 'multiple'], ['A', 'B', 'C', 'D'], ['Yes', 'No']];
        $size = max(1, ...array_map('count', $lists));

        return array_map(fn (int $index) => array_map(fn (array $list) => $list[$index] ?? '', $lists), range(0, $size - 1));
    }

    private function demoRow(): array
    {
        $subject = Subject::where('is_active', true)->orderBy('sort_order')->first();
        $classLevel = ClassLevel::where('is_active', true)->orderBy('sort_order')->first();
        $category = $subject
            ? QuestionCategory::active()->where('subject_id', $subject->id)->orderBy('sort_order')->orderBy('name')->first()
            : null;

        return [
            $subject?->name ?? '',
            $classLevel?->label ?? '',
            $category ? "{$subject->name}: {$category->name}" : '',
            'medium',
            'single',
            self::DEMO_TEXT,
            'Option A',
            'Option B',
            'Option C',
            'Option D',
            'A',
            1,
            0,
            'Replace this optional explanation.',
            'Yes',
        ];
    }

    private function addValidation($sheet, string $range, string $formula): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setFormula1($formula);
        $sheet->setDataValidation($range, $validation);
    }

    private function classLevelIds(string $classes): array
    {
        $labels = collect(preg_split('/[|,]/', $classes))->map(fn ($value) => Str::lower(trim($value)))->filter();

        return ClassLevel::where('is_active', true)->orderBy('sort_order')->get()->filter(fn (ClassLevel $level) => $labels->contains(Str::lower($level->label)))
            ->pluck('id')->values()->all();
    }

    private function categoryId(string $category, ?int $subjectId): ?int
    {
        if (! filled(trim($category)) || ! $subjectId) {
            return null;
        }

        $name = trim(Str::after($category, ':'));

        return QuestionCategory::active()->where('subject_id', $subjectId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])->value('id');
    }

    private function correctOptions(string $correct): array
    {
        return collect(preg_split('/[|,]/', Str::lower($correct)))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function html(string $value): string
    {
        return '<p>'.nl2br(e($value)).'</p>';
    }
}
