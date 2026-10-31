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

    public const DEMO_TEXT = '[DEMO] Replace this with your question before importing.';

    private const HEADERS = [
        'Subject *', 'Class Levels *', 'Category', 'Difficulty *', 'Question Type *',
        'Question Text *', 'Option A *', 'Option B *', 'Option C *', 'Option D *',
        'Correct Options *', 'Marks *', 'Negative Marks', 'Explanation', 'Active *',
    ];

    /**
     * " New categories created: A, B." suffix for the import success flash (empty when none).
     */
    public static function createdCategoriesNote(array $names): string
    {
        $count = count($names);

        return $count === 0 ? '' : sprintf(' %d new categor%s created: %s.', $count, $count === 1 ? 'y' : 'ies', implode(', ', $names));
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Questions');
        $sheet->setCellValue('A1', 'National Olympiad Hunt - Bulk Question Import');
        $sheet->mergeCells('A1:O1');
        $sheet->setCellValue('A2', 'Fields marked * are required. Row 5 is a live demo based on your current setup and is ignored unless you replace its demo question text. Class Levels accepts one dropdown value or multiple values separated by | (example: Class 5|Class 6). Correct Options uses A, B, C or D; use | for multiple-correct questions. Category: pick one from the list or type a new name - it is created for that row\'s subject when you confirm the import.');
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

        $this->writeListsSheet($spreadsheet);

        foreach (['A' => 'Subjects', 'B' => 'ClassLevels', 'C' => 'Categories', 'D' => 'Difficulties', 'E' => 'QuestionTypes', 'K' => 'CorrectOptions', 'O' => 'ActiveValues'] as $column => $list) {
            $this->addListValidation($sheet, $column, $list);
        }

        return $this->download($spreadsheet, 'question-import-template.xlsx');
    }

    /**
     * Hidden "Lists" sheet whose named ranges back every template dropdown.
     */
    public function writeListsSheet(Spreadsheet $spreadsheet): void
    {
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
    }

    /**
     * Dropdown for one template column across every importable row.
     */
    public function addListValidation($sheet, string $column, string $list): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setFormula1($list);
        $sheet->setDataValidation("{$column}5:{$column}".(self::MAX_ROWS + 5), $validation);
    }

    public function download(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Non-empty data rows (keyed by 0-based sheet row index) of a template whose row 4 holds $headers,
     * or a file-level error message.
     *
     * @return array{rows: \Illuminate\Support\Collection, error: ?string}
     */
    public function readRows(UploadedFile $file, array $headers): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        // The template's data sheet is "Questions"; fall back to the active sheet for workbooks
        // that were renamed. (Saving the file while another sheet was open must not break it.)
        $book = $reader->load($file->getRealPath());
        $rows = ($book->getSheetByName('Questions') ?? $book->getActiveSheet())->toArray('', true, true, false);
        $found = array_map(fn ($value) => trim((string) $value), array_slice($rows[3] ?? [], 0, count($headers)));

        if ($found !== $headers) {
            return ['rows' => collect(), 'error' => 'Use the current import template. Its required columns were not found.'];
        }

        $filled = collect(array_slice($rows, 4, null, true))
            ->map(fn (array $row) => array_slice($row, 0, count($headers)))
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''))
            ->reject(fn (array $row) => collect($row)->contains(fn ($value) => trim((string) $value) === self::DEMO_TEXT));

        if ($filled->count() > self::MAX_ROWS) {
            return ['rows' => collect(), 'error' => 'A workbook can contain at most '.self::MAX_ROWS.' question rows. Split the remaining questions into another upload.'];
        }

        if ($filled->isEmpty()) {
            return ['rows' => collect(), 'error' => 'The workbook does not contain any question rows.'];
        }

        return ['rows' => $filled, 'error' => null];
    }

    public function parse(UploadedFile $file): array
    {
        ['rows' => $rows, 'error' => $error] = $this->readRows($file, self::HEADERS);

        if ($error) {
            return ['questions' => [], 'fileErrors' => [$error]];
        }

        return [
            'questions' => $rows->map(fn (array $row, int $index) => $this->parseRow($row, $index + 1))->values()->all(),
            'fileErrors' => [],
        ];
    }

    public function validateRows(array $questions): array
    {
        return collect($questions)->map(function (array $question, int $index): array {
            $question['errors'] = $this->questionErrors($question);
            $question['source_row'] = $question['source_row'] ?? $index + 1;

            return $question;
        })->all();
    }

    /**
     * @return array{questions: int, categories: list<string>}
     */
    public function commit(array $questions, int $createdBy): array
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

        return DB::transaction(function () use ($questions, $createdBy): array {
            ['questions' => $questions, 'created' => $createdCategories] = $this->createMissingCategories($questions);

            foreach ($questions as $question) {
                $data = $this->questionAttributes($question);
                $classLevelIds = $data['class_level_ids'];
                unset($data['class_level_ids']);
                $data['created_by'] = $createdBy;

                $record = Question::create($data);
                $record->classLevels()->sync($classLevelIds);
            }

            return ['questions' => count($questions), 'categories' => $createdCategories];
        });
    }

    /**
     * Gives every row that names a category not yet in the bank a real category id, creating each
     * missing (subject, name) pair once as a top-level active category. Call inside the commit
     * transaction, after validation. Rows that already point at a category are left untouched.
     *
     * @return array{questions: array, created: list<string>}
     */
    public function createMissingCategories(array $questions): array
    {
        $resolved = [];
        $created = [];

        foreach ($questions as $index => $question) {
            $name = $this->normalizeCategoryName((string) ($question['new_category'] ?? ''));
            $subjectId = (int) ($question['subject_id'] ?? 0);

            if (filled($question['question_category_id'] ?? null) || $name === '' || ! $subjectId) {
                continue;
            }

            $key = $subjectId.'|'.Str::lower($name);
            if (! isset($resolved[$key])) {
                $existing = $this->categoryNamed($name, $subjectId);

                if ($existing?->is_active) {
                    $resolved[$key] = $existing->id;
                } else {
                    $category = QuestionCategory::create([
                        'subject_id' => $subjectId,
                        'parent_id' => null,
                        'name' => $name,
                        'slug' => QuestionCategory::uniqueSlug($name, $subjectId),
                        'is_active' => true,
                        'sort_order' => (int) QuestionCategory::where('subject_id', $subjectId)->whereNull('parent_id')->max('sort_order') + 1,
                    ]);
                    $resolved[$key] = $category->id;
                    $created[] = $name.' ('.Subject::whereKey($subjectId)->value('name').')';
                }
            }

            $questions[$index]['question_category_id'] = $resolved[$key];
            $questions[$index]['new_category'] = '';
        }

        return ['questions' => $questions, 'created' => $created];
    }

    private function parseRow(array $row, int $sourceRow): array
    {
        [$subject, $classes, $category, $difficulty, $type, $text, $a, $b, $c, $d, $correct, $marks, $negative, $explanation, $active] = array_pad($row, 15, '');
        $subjectId = Subject::where('is_active', true)->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $subject))])->value('id');
        $classLevelIds = $this->classLevelIds((string) $classes);

        return [
            'source_row' => $sourceRow,
            'subject_id' => $subjectId ?: '',
            'class_level_ids' => $classLevelIds,
            ...$this->categoryFields((string) $category, $subjectId),
            'difficulty' => Str::lower(trim((string) $difficulty)),
            'question_type' => Str::lower(trim((string) $type)),
            'question_text' => trim((string) $text),
            'option_a' => trim((string) $a),
            'option_b' => trim((string) $b),
            'option_c' => trim((string) $c),
            'option_d' => trim((string) $d),
            'correct_options' => $this->correctOptions((string) $correct),
            'marks' => $this->parseMarks($marks),
            'negative_marks' => is_numeric($negative) ? (float) $negative : (trim((string) $negative) === '' ? null : $negative),
            'explanation' => trim((string) $explanation),
            'is_active' => in_array(Str::lower(trim((string) $active)), ['yes', '1', 'true'], true),
            'errors' => [],
        ];
    }

    public function questionErrors(array $question): array
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
        } elseif (! filled($question['question_category_id'] ?? null) && ($message = $this->newCategoryError($question))) {
            $errors['question_category_id'][] = $message;
        }
        if (($question['question_type'] ?? null) === 'single' && count($question['correct_options'] ?? []) !== 1) {
            $errors['correct_options'][] = 'Single-correct questions need exactly one correct option.';
        }

        return collect($errors)->map(fn (array $messages) => $messages[0])->all();
    }

    public function questionAttributes(array $question): array
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

    public function classLevelIds(string $classes): array
    {
        $labels = collect(preg_split('/[|,]/', $classes))->map(fn ($value) => Str::lower(trim($value)))->filter();

        return ClassLevel::where('is_active', true)->orderBy('sort_order')->get()->filter(fn (ClassLevel $level) => $labels->contains(Str::lower($level->label)))
            ->pluck('id')->values()->all();
    }

    /**
     * Maps the workbook's Category cell to row fields. Accepts a plain name ("Verbs") or the
     * template's "Subject: Name" form. An existing active category of the row's subject is linked
     * by id; any other name is kept in new_category so the review page can show it and commit()
     * can create it. Nothing typed in the cell is ever dropped silently.
     *
     * @return array{category_name: string, question_category_id: int|string, new_category: string}
     */
    public function categoryFields(string $category, ?int $subjectId): array
    {
        $raw = $this->normalizeCategoryName($category);
        $fields = ['category_name' => $raw, 'question_category_id' => '', 'new_category' => ''];

        if ($raw === '') {
            return $fields;
        }

        $name = $raw;
        if ($subjectId && ! $this->categoryNamed($raw, $subjectId) && str_contains($raw, ':')) {
            $prefix = trim(Str::before($raw, ':'));
            if (Subject::whereKey($subjectId)->whereRaw('LOWER(name) = ?', [Str::lower($prefix)])->exists()) {
                $name = $this->normalizeCategoryName(Str::after($raw, ':'));
            }
        }

        $existing = $subjectId ? $this->categoryNamed($name, $subjectId) : null;
        if ($existing?->is_active) {
            $fields['question_category_id'] = $existing->id;
        } else {
            $fields['new_category'] = $name;
        }

        return $fields;
    }

    /**
     * Why a row's not-yet-existing category cannot be created, or null when it can.
     */
    private function newCategoryError(array $question): ?string
    {
        $name = $this->normalizeCategoryName((string) ($question['new_category'] ?? ''));
        $subjectId = (int) ($question['subject_id'] ?? 0);

        if ($name === '' || ! $subjectId) {
            return null;
        }
        if (Str::length($name) > 120) {
            return 'Category names can be at most 120 characters.';
        }

        $existing = $this->categoryNamed($name, $subjectId);
        if ($existing && ! $existing->is_active) {
            return "Category \"{$name}\" exists for this subject but is inactive. Re-activate it in Settings → Categories or choose another category.";
        }

        if (! $existing && str_contains($name, ':')) {
            $owner = Subject::whereKeyNot($subjectId)
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim(Str::before($name, ':')))])
                ->value('name');
            if ($owner) {
                return "\"{$name}\" is a {$owner} category. Choose a category of this row's subject.";
            }
        }

        return null;
    }

    private function categoryNamed(string $name, int $subjectId): ?QuestionCategory
    {
        return QuestionCategory::where('subject_id', $subjectId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderByDesc('is_active')->orderBy('id')
            ->first();
    }

    private function normalizeCategoryName(string $name): string
    {
        return (string) Str::of($name)->squish();
    }

    /**
     * Whole numbers become ints; anything else (e.g. "1.5", "two") is kept as typed so validation
     * reports it instead of the value being silently truncated.
     */
    public function parseMarks(mixed $marks): mixed
    {
        if (is_numeric($marks) && (float) $marks === (float) (int) $marks) {
            return (int) $marks;
        }

        return is_string($marks) ? trim($marks) : $marks;
    }

    public function correctOptions(string $correct): array
    {
        return collect(preg_split('/[|,]/', Str::lower($correct)))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function html(string $value): string
    {
        return '<p>'.nl2br(e($value)).'</p>';
    }
}
