<?php

namespace App\Services;

use App\Models\HallTicketSetting;
use App\Models\StudentCustomField;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Owns the admin-configurable hall ticket (§12.15): the catalogue of fields and schedule
 * columns, defaults, normalisation of whatever the admin saves, and rendering a raw
 * ticket context (HallTicketService::context()) into the payload HallTicketCard draws.
 *
 * Adding a new built-in field = one FIELDS entry + one value in context(). Admins add
 * student data without code via StudentCustomField (keys `custom:{key}`) and static
 * text rows (`text:{id}`).
 */
class HallTicketLayoutService
{
    /** Built-in candidate details: label, description, default flags. */
    public const FIELDS = [
        'name' => ['label' => 'Candidate name', 'hint' => 'Student\'s full name', 'wide' => true, 'strong' => true],
        'roll_number' => ['label' => 'Roll number', 'hint' => 'Issued on approval (also shown in the header box)', 'mono' => true],
        'registration_no' => ['label' => 'Registration no.', 'hint' => 'NEO-ST-… portal registration number', 'mono' => true],
        'dob' => ['label' => 'Date of birth', 'hint' => 'From the profile'],
        'class' => ['label' => 'Class', 'hint' => 'From the profile'],
        'phone' => ['label' => 'Mobile', 'hint' => 'Registered mobile number', 'mono' => true],
        'email' => ['label' => 'Email', 'hint' => 'Registered email address'],
        'school' => ['label' => 'School', 'hint' => 'School name', 'wide' => true],
        'school_address' => ['label' => 'School address', 'hint' => 'Address, city, state and PIN combined', 'wide' => true],
        'city' => ['label' => 'City', 'hint' => 'City only'],
        'state' => ['label' => 'State', 'hint' => 'State only'],
        'pincode' => ['label' => 'PIN code', 'hint' => 'PIN only', 'mono' => true],
        'coordinator_name' => ['label' => 'School coordinator', 'hint' => 'Coordinator\'s name'],
        'coordinator_phone' => ['label' => 'Coordinator phone', 'hint' => 'Coordinator\'s mobile', 'mono' => true],
        'olympiads' => ['label' => 'Olympiads', 'hint' => 'All enrolled olympiads, comma-separated', 'wide' => true],
        'exam_centre' => ['label' => 'Examination centre', 'hint' => 'Text from Layout → "Examination centre"'],
    ];

    /** Schedule table columns. */
    public const COLUMNS = [
        'index' => ['label' => '#', 'hint' => 'Row number'],
        'enrollment_id' => ['label' => 'Enrollment ID', 'hint' => 'NEO-EN-… per olympiad', 'mono' => true],
        'exam' => ['label' => 'Olympiad', 'hint' => 'Examination name (+ subject · code line, see Layout)'],
        'subject' => ['label' => 'Subject', 'hint' => 'Subject only'],
        'exam_code' => ['label' => 'Exam code', 'hint' => 'Exam code only', 'mono' => true],
        'date' => ['label' => 'Exam date', 'hint' => 'Window start date'],
        'login_by' => ['label' => 'Log in by', 'hint' => 'Start time minus the log-in buffer', 'mono' => true],
        'start_time' => ['label' => 'Starts', 'hint' => 'Exam timing: start', 'mono' => true],
        'window_closes' => ['label' => 'Window closes', 'hint' => 'Exam timing: end of window', 'mono' => true],
        'timing' => ['label' => 'Timing', 'hint' => 'Start – end in one column', 'mono' => true],
        'duration' => ['label' => 'Duration', 'hint' => 'Exam length in minutes', 'mono' => true],
        'mode' => ['label' => 'Mode', 'hint' => 'Text from Layout → "Mode"'],
        'centre' => ['label' => 'Centre', 'hint' => 'Text from Layout → "Examination centre"'],
    ];

    private const DEFAULT_FIELDS_ON = ['name', 'registration_no', 'dob', 'class', 'phone', 'school', 'school_address', 'coordinator_name', 'coordinator_phone'];

    private const DEFAULT_COLUMNS_ON = ['index', 'enrollment_id', 'exam', 'date', 'login_by', 'start_time', 'window_closes', 'duration', 'mode'];

    public const DEFAULT_INSTRUCTIONS = [
        'Keep this hall ticket (printed or on your phone) and your school ID card with you on the exam day.',
        'Sign in to the student portal at least 15 minutes before the exam opens, using your registered email or mobile number.',
        'You can start any time while the exam window is open. Once you start, the timer runs for the full duration and cannot be paused by you.',
        'Use a laptop, desktop or tablet with a stable internet connection and an up-to-date Chrome browser. Keep the device charged.',
        'The exam runs in full-screen mode. Leaving the exam screen or switching apps is recorded; the exam is submitted automatically after 3 warnings.',
        'Only one attempt is allowed per olympiad. Your answers are saved automatically as you go.',
        'Books, notes, calculators and other devices are not allowed during the exam.',
        'Results are declared by the examination team on the student portal.',
        'This hall ticket is valid only after it is approved by the examination team.',
    ];

    public const LIMITS = ['instructions' => 25, 'instruction_length' => 400, 'text_rows' => 20, 'label' => 60, 'text_value' => 200];

    /** Every layout option with its default. normalizeLayout() enforces types and ranges. */
    public const LAYOUT_DEFAULTS = [
        'theme' => 'brand',                 // brand | classic (pure black & white)
        'title' => 'Hall Ticket',
        'subtitle' => 'Admit card · Online olympiad examination',
        'show_logo' => true,
        'show_session' => true,
        'show_roll_box' => true,
        'photo' => 'right',                 // right | left | hidden
        'show_signature_box' => true,       // candidate signature box under the photo
        'detail_columns' => 2,              // 1 | 2 | 3
        'hide_empty' => false,              // hide fields with no value instead of "—"
        'show_schedule' => true,
        'schedule_title' => 'Examination schedule',
        'exam_detail_line' => true,         // subject · code under the olympiad name
        'login_offset_minutes' => 15,
        'mode_text' => 'Online',
        'exam_centre' => 'Online (student portal)',
        'show_venue' => true,
        'venue_text' => 'Online, from your own device at {portal} (student portal). No physical exam centre.',
        'show_instructions' => true,
        'instructions_title' => 'Instructions to the candidate',
        'instruction_columns' => 2,         // 1 | 2
        'show_signatures' => true,
        'signatures' => ["Candidate's signature", "Parent / guardian's signature", 'Controller of Examinations'],
        'show_stamp' => true,
        'stamp_text' => 'Verified & approved',
        'show_watermark' => true,
        'footer_note' => 'This is a computer-generated hall ticket. Keep it safe until results are declared.',
    ];

    private const ENUMS = ['theme' => ['brand', 'classic'], 'photo' => ['right', 'left', 'hidden'], 'detail_columns' => [1, 2, 3], 'instruction_columns' => [1, 2]];

    private const STRING_MAX = [
        'title' => 40, 'subtitle' => 90, 'schedule_title' => 50, 'mode_text' => 30, 'exam_centre' => 120,
        'venue_text' => 200, 'instructions_title' => 60, 'stamp_text' => 30, 'footer_note' => 200,
    ];

    /* ───────────────────────── configuration ───────────────────────── */

    public function defaults(): array
    {
        return [
            'fields' => collect(self::FIELDS)->keys()->map(fn ($key) => [
                'key' => $key, 'enabled' => in_array($key, self::DEFAULT_FIELDS_ON, true), 'label' => null, 'wide' => (bool) (self::FIELDS[$key]['wide'] ?? false),
            ])->sortBy(fn ($f) => $f['enabled'] ? array_search($f['key'], self::DEFAULT_FIELDS_ON, true) : 100)->values()->all(),
            'exam_columns' => collect(self::COLUMNS)->keys()->map(fn ($key) => [
                'key' => $key, 'enabled' => in_array($key, self::DEFAULT_COLUMNS_ON, true), 'label' => null,
            ])->sortBy(fn ($c) => $c['enabled'] ? array_search($c['key'], self::DEFAULT_COLUMNS_ON, true) : 100)->values()->all(),
            'text_rows' => [],
            'instructions' => self::DEFAULT_INSTRUCTIONS,
            'layout' => self::LAYOUT_DEFAULTS,
        ];
    }

    /** The saved configuration, normalised against the current catalogue (custom fields come and go). */
    public function config(?HallTicketSetting $setting = null): array
    {
        $setting ??= HallTicketSetting::current();

        return $this->normalize([
            'fields' => $setting->fields,
            'exam_columns' => $setting->exam_columns,
            'text_rows' => $setting->text_rows,
            'instructions' => $setting->instructions,
            'layout' => $setting->layout,
        ]);
    }

    public function save(array $input, int $adminId): HallTicketSetting
    {
        $setting = HallTicketSetting::current();
        $setting->update([...$this->normalize($input), 'updated_by' => $adminId]);

        return $setting;
    }

    public function reset(int $adminId): HallTicketSetting
    {
        $setting = HallTicketSetting::current();
        $setting->update([...$this->defaults(), 'updated_by' => $adminId]);

        return $setting;
    }

    /** Request validation for the settings form (shape only; normalize() does the rest). */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'max:200'],
            'fields.*.key' => ['required', 'string', 'max:80'],
            'fields.*.enabled' => ['boolean'],
            'fields.*.label' => ['nullable', 'string', 'max:'.self::LIMITS['label']],
            'fields.*.wide' => ['boolean'],
            'exam_columns' => ['required', 'array', 'max:50'],
            'exam_columns.*.key' => ['required', 'string', 'max:40'],
            'exam_columns.*.enabled' => ['boolean'],
            'exam_columns.*.label' => ['nullable', 'string', 'max:'.self::LIMITS['label']],
            'text_rows' => ['nullable', 'array', 'max:'.self::LIMITS['text_rows']],
            'text_rows.*.id' => ['nullable', 'string', 'max:20'],
            'text_rows.*.label' => ['required', 'string', 'max:'.self::LIMITS['label']],
            'text_rows.*.value' => ['nullable', 'string', 'max:'.self::LIMITS['text_value']],
            'instructions' => ['nullable', 'array', 'max:'.self::LIMITS['instructions']],
            'instructions.*' => ['nullable', 'string', 'max:'.self::LIMITS['instruction_length']],
            'layout' => ['required', 'array'],
            'layout.signatures' => ['nullable', 'array', 'max:3'],
            'layout.signatures.*' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Clean any (possibly stale or hostile) config into the canonical shape: unknown keys
     * dropped, every available field/column present exactly once (new ones appended,
     * disabled), labels trimmed, layout values clamped to their allowed ranges.
     */
    public function normalize(array $input): array
    {
        $customs = StudentCustomField::active()->ordered()->get();
        $textRows = $this->normalizeTextRows($input['text_rows'] ?? []);

        $catalogue = collect(self::FIELDS)->map(fn ($meta) => (bool) ($meta['wide'] ?? false));
        foreach ($customs as $field) {
            $catalogue[$field->ticketKey()] = false;
        }
        foreach ($textRows as $row) {
            $catalogue['text:'.$row['id']] = false;
        }

        return [
            'fields' => $this->normalizeList($input['fields'] ?? null, $catalogue->all(), withWide: true),
            'exam_columns' => $this->normalizeList($input['exam_columns'] ?? null, array_fill_keys(array_keys(self::COLUMNS), false)),
            'text_rows' => $textRows,
            'instructions' => collect(is_array($input['instructions'] ?? null) ? $input['instructions'] : self::DEFAULT_INSTRUCTIONS)
                ->map(fn ($line) => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $line)), self::LIMITS['instruction_length'], ''))
                ->filter()
                ->take(self::LIMITS['instructions'])
                ->values()
                ->all(),
            'layout' => $this->normalizeLayout(is_array($input['layout'] ?? null) ? $input['layout'] : []),
        ];
    }

    /** Everything the settings page needs to label the toggles. */
    public function catalogue(): array
    {
        $customs = StudentCustomField::active()->ordered()->get();

        return [
            'fields' => [
                ...collect(self::FIELDS)->map(fn ($m, $k) => ['key' => $k, 'label' => $m['label'], 'hint' => $m['hint'], 'group' => 'Student & exam'])->values()->all(),
                ...$customs->map(fn (StudentCustomField $f) => [
                    'key' => $f->ticketKey(), 'label' => $f->label, 'group' => 'Custom student field',
                    'hint' => ($f->is_required ? 'Required' : 'Optional').' · students fill it on their profile',
                ])->all(),
            ],
            'columns' => collect(self::COLUMNS)->map(fn ($m, $k) => ['key' => $k, 'label' => $m['label'], 'hint' => $m['hint']])->values()->all(),
            'limits' => self::LIMITS,
            'layout_defaults' => self::LAYOUT_DEFAULTS,
        ];
    }

    /* ───────────────────────── rendering ───────────────────────── */

    /**
     * Shape a raw context into the card payload. Pure: no queries except reading the
     * config (pass $config to render a draft, e.g. the live preview).
     */
    public function render(array $context, ?array $config = null): array
    {
        $config ??= $this->config();
        $layout = $config['layout'];
        $approved = $context['status'] === 'approved';
        $values = $context['values'];
        $values['exam_centre'] = $layout['exam_centre'];
        foreach ($config['text_rows'] as $row) {
            $values['text:'.$row['id']] = $row['value'];
        }
        $labels = $this->defaultLabels($config);

        $details = collect($config['fields'])
            ->filter(fn ($f) => $f['enabled'])
            ->map(fn ($f) => [
                'key' => $f['key'],
                'label' => $f['label'] ?: ($labels[$f['key']] ?? $f['key']),
                'value' => filled($values[$f['key']] ?? null) ? (string) $values[$f['key']] : null,
                'wide' => (bool) $f['wide'],
                'strong' => (bool) (self::FIELDS[$f['key']]['strong'] ?? false),
                'mono' => (bool) (self::FIELDS[$f['key']]['mono'] ?? false),
            ])
            ->when($layout['hide_empty'], fn ($c) => $c->filter(fn ($d) => $d['value'] !== null))
            ->values()
            ->all();

        $columns = collect($config['exam_columns'])
            ->filter(fn ($c) => $c['enabled'])
            ->map(fn ($c) => [
                'key' => $c['key'],
                'label' => $c['label'] ?: self::COLUMNS[$c['key']]['label'],
                'mono' => (bool) (self::COLUMNS[$c['key']]['mono'] ?? false),
            ])
            ->values()
            ->all();

        $portal = preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/'));

        return [
            'status' => $context['status'],
            'theme' => $layout['theme'],
            'header' => [
                'logo' => $layout['show_logo'],
                'title' => $layout['title'],
                'subtitle' => $layout['subtitle'],
                'session' => $layout['show_session'] ? 'Academic Session '.$this->academicSession($context['session_at']) : null,
                'roll_box' => $layout['show_roll_box'],
                'roll_number' => $context['roll_number'],
            ],
            'photo' => $layout['photo'] === 'hidden' ? null : [
                'position' => $layout['photo'],
                'url' => $context['photo_url'],
                'signature_box' => $layout['show_signature_box'],
            ],
            'detail_columns' => $layout['detail_columns'],
            'details' => $details,
            'schedule' => $layout['show_schedule'] && $columns ? [
                'title' => $layout['schedule_title'],
                'columns' => $columns,
                'rows' => collect($context['exams'])->values()->map(fn ($exam, $i) => $this->examCells($exam, $i, $columns, $layout))->all(),
            ] : null,
            'venue' => $layout['show_venue'] && filled($layout['venue_text']) ? str_replace('{portal}', $portal, $layout['venue_text']) : null,
            'instructions' => $layout['show_instructions'] && $config['instructions'] ? [
                'title' => $layout['instructions_title'],
                'columns' => $layout['instruction_columns'],
                'items' => $config['instructions'],
            ] : null,
            'signatures' => $layout['show_signatures'] ? array_values(array_filter($layout['signatures'], 'filled')) : [],
            'stamp' => $approved && $layout['show_stamp'] ? ['text' => $layout['stamp_text'], 'date' => $context['approved_on']] : null,
            'watermark' => ! $approved && $layout['show_watermark']
                ? (['rejected' => 'NOT APPROVED', 'pending' => 'AWAITING VERIFICATION'][$context['status']] ?? 'PREVIEW')
                : null,
            'footer_note' => $layout['footer_note'] ?: null,
        ];
    }

    /** A realistic made-up student for the settings preview when no real ticket is chosen. */
    public function sampleContext(string $status = 'approved'): array
    {
        $values = [
            'name' => 'Aarav Sharma', 'roll_number' => 'NEO260500001', 'registration_no' => 'NEO-ST-000127',
            'dob' => '14 March 2015', 'class' => 'Class 5', 'phone' => '+919000000001', 'email' => 'aarav@example.test',
            'school' => 'Delhi Public School', 'school_address' => 'Sector 12, R.K. Puram, New Delhi, Delhi, 110022',
            'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110022',
            'coordinator_name' => 'Mrs. Sunita Rao', 'coordinator_phone' => '+919876543210',
            'olympiads' => 'National Mathematics Olympiad – Class 5, National Science Olympiad – Class 5',
        ];
        $samples = ['father_name' => 'Rajesh Sharma', 'mother_name' => 'Priya Sharma', 'gender' => 'Male', 'school_admission_no' => 'DPS/2019/0457'];
        foreach (StudentCustomField::active()->get() as $field) {
            $values[$field->ticketKey()] = $samples[$field->key] ?? match ($field->type) {
                'date' => '1 January 2015',
                'select' => $field->options[0] ?? 'Sample',
                default => 'Sample '.Str::lower($field->label),
            };
        }

        $day = now()->addDays(10)->setTime(10, 0);

        return [
            'status' => $status,
            'roll_number' => $status === 'approved' ? 'NEO260500001' : null,
            'session_at' => now(),
            'approved_on' => $status === 'approved' ? now()->format('j F Y') : null,
            'values' => $status === 'approved' ? $values : [...$values, 'roll_number' => null],
            'photo_url' => null,
            'exams' => [
                ['enrollment_id' => 'NEO-EN-000101', 'exam' => 'National Mathematics Olympiad – Class 5', 'exam_code' => 'NMO-5', 'subject' => 'Mathematics', 'starts' => $day, 'ends' => $day->copy()->addHours(3), 'duration_minutes' => 60, 'personal_window' => false],
                ['enrollment_id' => 'NEO-EN-000102', 'exam' => 'National Science Olympiad – Class 5', 'exam_code' => 'NSO-5', 'subject' => 'Science', 'starts' => $day->copy()->addDays(2), 'ends' => $day->copy()->addDays(2)->addHours(3), 'duration_minutes' => 60, 'personal_window' => false],
            ],
        ];
    }

    /* ───────────────────────── internals ───────────────────────── */

    private function examCells(array $exam, int $i, array $columns, array $layout): array
    {
        /** @var Carbon|null $starts */
        $starts = $exam['starts'] ?? null;
        /** @var Carbon|null $ends */
        $ends = $exam['ends'] ?? null;
        $sameDay = $starts && $ends && $starts->isSameDay($ends);
        $closes = $ends ? ($sameDay || ! $starts ? $ends->format('g:i A') : $ends->format('j M, g:i A')) : null;
        $detail = collect([$exam['subject'] ?? null, $exam['exam_code'] ?? null])->filter()->implode(' · ')
            .(! empty($exam['personal_window']) ? ' · rescheduled' : '');

        $cell = fn (string $key) => match ($key) {
            'index' => (string) ($i + 1),
            'enrollment_id' => $exam['enrollment_id'],
            'exam' => ['text' => $exam['exam'], 'sub' => $layout['exam_detail_line'] && $detail !== '' ? ltrim($detail, ' ·') : null],
            'subject' => $exam['subject'] ?? null,
            'exam_code' => $exam['exam_code'] ?? null,
            'date' => $starts?->format('D, j M Y') ?? 'To be announced',
            'login_by' => $starts?->copy()->subMinutes($layout['login_offset_minutes'])->format('g:i A'),
            'start_time' => $starts?->format('g:i A'),
            'window_closes' => $closes,
            'timing' => $starts ? $starts->format('g:i A').($closes ? ' – '.$closes : '') : null,
            'duration' => $exam['duration_minutes'] ? $exam['duration_minutes'].' min' : null,
            'mode' => $layout['mode_text'],
            'centre' => $layout['exam_centre'],
            default => null,
        };

        return collect($columns)->mapWithKeys(function ($c) use ($cell) {
            $v = $cell($c['key']);

            return [$c['key'] => is_array($v) ? $v : ['text' => $v ?? '—', 'sub' => null]];
        })->all();
    }

    private function defaultLabels(array $config): array
    {
        $labels = collect(self::FIELDS)->map(fn ($m) => $m['label'])->all();
        foreach (StudentCustomField::active()->get() as $field) {
            $labels[$field->ticketKey()] = $field->label;
        }
        foreach ($config['text_rows'] as $row) {
            $labels['text:'.$row['id']] = $row['label'];
        }

        return $labels;
    }

    /**
     * @param  array<string, bool>  $catalogue  key => default "wide"
     */
    private function normalizeList(mixed $items, array $catalogue, bool $withWide = false): array
    {
        $seen = [];
        $out = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $key = is_array($item) ? (string) ($item['key'] ?? '') : '';
            if (! array_key_exists($key, $catalogue) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $label = trim((string) ($item['label'] ?? ''));
            $row = ['key' => $key, 'enabled' => (bool) ($item['enabled'] ?? false), 'label' => $label === '' ? null : Str::limit($label, self::LIMITS['label'], '')];
            if ($withWide) {
                $row['wide'] = (bool) ($item['wide'] ?? $catalogue[$key]);
            }
            $out[] = $row;
        }

        // Anything new in the catalogue (e.g. a just-created custom field) is appended, switched off.
        foreach ($catalogue as $key => $wide) {
            if (! isset($seen[$key])) {
                $row = ['key' => $key, 'enabled' => $items === null && $this->defaultOn($key), 'label' => null];
                if ($withWide) {
                    $row['wide'] = $wide;
                }
                $out[] = $row;
            }
        }

        return $out;
    }

    private function defaultOn(string $key): bool
    {
        return in_array($key, self::DEFAULT_FIELDS_ON, true) || in_array($key, self::DEFAULT_COLUMNS_ON, true);
    }

    private function normalizeTextRows(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($r) => is_array($r) && filled($r['label'] ?? null))
            ->map(fn ($r) => [
                'id' => preg_match('/^[a-z0-9]{4,20}$/', (string) ($r['id'] ?? '')) ? $r['id'] : Str::lower(Str::random(8)),
                'label' => Str::limit(trim((string) $r['label']), self::LIMITS['label'], ''),
                'value' => Str::limit(trim((string) ($r['value'] ?? '')), self::LIMITS['text_value'], ''),
            ])
            ->unique('id')
            ->take(self::LIMITS['text_rows'])
            ->values()
            ->all();
    }

    private function normalizeLayout(array $input): array
    {
        $out = [];

        foreach (self::LAYOUT_DEFAULTS as $key => $default) {
            $value = $input[$key] ?? $default;

            $out[$key] = match (true) {
                isset(self::ENUMS[$key]) => in_array(is_numeric($value) ? (int) $value : $value, self::ENUMS[$key], true) ? (is_numeric($value) ? (int) $value : $value) : $default,
                is_bool($default) => filter_var($value, FILTER_VALIDATE_BOOL),
                $key === 'login_offset_minutes' => max(0, min(120, (int) $value)),
                $key === 'signatures' => collect(array_pad(array_slice(is_array($value) ? $value : $default, 0, 3), 3, ''))
                    ->map(fn ($s) => Str::limit(trim((string) $s), 40, ''))->all(),
                is_string($default) => Str::limit(trim((string) $value), self::STRING_MAX[$key] ?? 120, ''),
                default => $default,
            };
        }

        // A ticket always needs a title.
        $out['title'] = $out['title'] ?: self::LAYOUT_DEFAULTS['title'];

        return $out;
    }

    /** Indian academic year (April–March), e.g. "2026–27". */
    private function academicSession(Carbon $at): string
    {
        $start = $at->month >= 4 ? $at->year : $at->year - 1;

        return $start.'–'.substr((string) ($start + 1), -2);
    }
}
