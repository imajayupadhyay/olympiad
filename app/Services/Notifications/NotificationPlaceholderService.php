<?php

namespace App\Services\Notifications;

use App\Models\Exam;
use App\Models\User;
use App\Services\ExamSessionService;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The one placeholder catalogue for admin broadcasts (email, in-app, WhatsApp).
 *
 * `{{ key }}` tokens are filled per student at send time. Exam placeholders need an
 * "exam for placeholders" (the broadcast's exam context); a personal window from an
 * admin reschedule wins over the exam window. Dates are on the display clock (§5.7).
 * Unknown tokens and exam tokens without an exam are rejected before anything is sent,
 * so a raw `{{ … }}` never reaches a student.
 */
class NotificationPlaceholderService
{
    public const TOKEN = '/{{\s*([a-zA-Z0-9_]+)\s*}}/';

    /** key => [label, group, example, requires exam?, column that can be blank] */
    public const CATALOGUE = [
        'student_name' => ['Student name', 'student', 'Aarav Sharma', false, null],
        'first_name' => ['First name', 'student', 'Aarav', false, null],
        'student_email' => ['Email', 'student', 'aarav@example.com', false, null],
        'student_phone' => ['Mobile number', 'student', '+91 98765 43210', false, 'phone'],
        'student_class' => ['Class', 'student', 'Class 5', false, 'class_level_id'],
        'school_name' => ['School', 'student', 'Delhi Public School', false, 'school'],
        'city' => ['City', 'student', 'New Delhi', false, 'city'],
        'state' => ['State', 'student', 'Delhi', false, 'state'],
        'registration_no' => ['Registration no.', 'student', 'NEO-ST-000127', false, null],
        'roll_number' => ['Roll no. (approved hall ticket)', 'student', 'NEO260500001', false, 'roll_number'],
        'exam_name' => ['Exam name', 'exam', 'National Mathematics Olympiad – Class 5', true, null],
        'course_name' => ['Olympiad subject (course)', 'exam', 'Mathematics', true, null],
        'exam_code' => ['Exam code', 'exam', 'NMO-5', true, null],
        'exam_date' => ['Exam date', 'exam', '15 Nov 2026', true, null],
        'exam_start_time' => ['Opens at', 'exam', '9:00 AM', true, null],
        'exam_end_time' => ['Closes at', 'exam', '9:00 PM', true, null],
        'exam_window' => ['Exam window', 'exam', 'Sun, 15 Nov · 9:00 AM – 9:00 PM', true, null],
        'exam_duration' => ['Duration', 'exam', '60 minutes', true, null],
        'app_name' => ['Platform name', 'portal', null, false, null],
        'portal_url' => ['Portal link', 'portal', null, false, null],
        'support_email' => ['Support email', 'portal', null, false, null],
        'support_phone' => ['Support phone', 'portal', null, false, null],
    ];

    public function __construct(private readonly ExamSessionService $sessions) {}

    /** Catalogue for the composer UI. */
    public function catalogue(): array
    {
        $portal = $this->portalValues();

        return collect(self::CATALOGUE)->map(fn ($p, $key) => [
            'key' => $key,
            'token' => '{{'.$key.'}}',
            'label' => $p[0],
            'group' => $p[1],
            'example' => $p[2] ?? ($portal[$key] ?? null),
            'requires_exam' => $p[3],
        ])->values()->all();
    }

    /** @return array<int, string> unique keys used across the given texts */
    public function usedKeys(?string ...$texts): array
    {
        $keys = [];
        foreach ($texts as $text) {
            if ($text !== null && preg_match_all(self::TOKEN, $text, $m)) {
                $keys = array_merge($keys, $m[1]);
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Problems that must block a send.
     *
     * @return array<int, string>
     */
    public function problems(array $keys, ?Exam $exam): array
    {
        $errors = [];
        $unknown = array_diff($keys, array_keys(self::CATALOGUE));
        if ($unknown) {
            $errors[] = 'Unknown placeholder'.(count($unknown) > 1 ? 's' : '').': '.implode(', ', array_map(fn ($k) => '{{'.$k.'}}', $unknown)).'.';
        }

        $examKeys = array_filter($keys, fn ($k) => (self::CATALOGUE[$k][3] ?? false));
        if ($examKeys && ! $exam) {
            $errors[] = 'Choose an "Exam for placeholders" to use '.implode(', ', array_map(fn ($k) => '{{'.$k.'}}', $examKeys)).'.';
        }

        return $errors;
    }

    /**
     * How many audience members would get an empty value, per placeholder.
     *
     * @return array<string, int>
     */
    public function blankCounts(Builder $audience, array $keys): array
    {
        $counts = [];

        foreach ($keys as $key) {
            $column = self::CATALOGUE[$key][4] ?? null;
            if (! $column) {
                continue;
            }

            $query = clone $audience;
            $count = $column === 'roll_number'
                ? $query->whereDoesntHave('hallTicket', fn (Builder $t) => $t->where('status', 'approved')->whereNotNull('roll_number'))->count()
                : $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''))->count();

            if ($count > 0) {
                $counts[$key] = $count;
            }
        }

        return $counts;
    }

    /** Relations a recipient chunk must eager-load before valuesFor(). */
    public function eagerLoads(?Exam $exam): array
    {
        $with = ['classLevel:id,label', 'hallTicket:id,user_id,status,roll_number'];

        if ($exam) {
            $with['enrollments'] = fn ($q) => $q->where('exam_id', $exam->id)->where('status', 'enrolled');
        }

        return $with;
    }

    /** @return array<string, string> */
    public function valuesFor(User $user, ?Exam $exam = null): array
    {
        $ticket = $user->relationLoaded('hallTicket') ? $user->hallTicket : $user->hallTicket()->first();

        $values = [
            'student_name' => (string) $user->name,
            'first_name' => (string) (preg_split('/\s+/', trim((string) $user->name))[0] ?? ''),
            'student_email' => (string) $user->email,
            'student_phone' => (string) ($user->phone_e164 ?: $user->phone),
            'student_class' => (string) ($user->classLevel?->label ?? ''),
            'school_name' => (string) ($user->school ?? ''),
            'city' => (string) ($user->city ?? ''),
            'state' => (string) ($user->state ?? ''),
            'registration_no' => 'NEO-ST-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'roll_number' => $ticket && $ticket->status === 'approved' ? (string) $ticket->roll_number : '',
        ] + $this->portalValues();

        if ($exam) {
            $values += $this->examValues($exam, $user);
        }

        return $values;
    }

    /** Example values for previews before any real student is available. */
    public function sampleValues(?Exam $exam = null): array
    {
        $values = collect(self::CATALOGUE)->map(fn ($p) => (string) ($p[2] ?? ''))->all();
        $values = array_merge($values, $this->portalValues());

        if ($exam) {
            $values = array_merge($values, $this->examValues($exam, null));
        }

        return $values;
    }

    /** Plain-text render (in-app, WhatsApp, subjects). */
    public function renderText(string $text, array $values): string
    {
        return (string) preg_replace_callback(self::TOKEN, fn ($m) => (string) Arr::get($values, $m[1], ''), $text);
    }

    /** HTML render: admin-authored HTML is kept, values are escaped. */
    public function renderHtml(string $html, array $values): string
    {
        return (string) preg_replace_callback(self::TOKEN, fn ($m) => e((string) Arr::get($values, $m[1], '')), $html);
    }

    private function examValues(Exam $exam, ?User $user): array
    {
        $exam->loadMissing('subject:id,name');
        $enrollment = $user && $user->relationLoaded('enrollments')
            ? $user->enrollments->firstWhere('exam_id', $exam->id)
            : null;

        [$starts, $ends] = $this->sessions->window($exam, $enrollment);

        return [
            'exam_name' => (string) $exam->name,
            'course_name' => (string) ($exam->subject?->name ?? ''),
            'exam_code' => (string) ($exam->exam_code ?? ''),
            'exam_date' => (string) LocalTime::format($starts, 'd M Y'),
            'exam_start_time' => (string) LocalTime::format($starts, 'g:i A'),
            'exam_end_time' => (string) LocalTime::format($ends, 'g:i A'),
            'exam_window' => (string) $this->sessions->windowLabel($starts, $ends),
            'exam_duration' => $exam->duration_minutes ? $exam->duration_minutes.' minutes' : '',
        ];
    }

    private function portalValues(): array
    {
        return [
            'app_name' => (string) config('app.name'),
            'portal_url' => (string) config('app.url'),
            'support_email' => (string) (config('services.brevo.support_email') ?: config('mail.from.address')),
            'support_phone' => (string) config('services.brevo.support_phone'),
        ];
    }

    /** Values for a chunk keyed by user id (used by the in-app snapshot). */
    public function valuesForMany(Collection $users, ?Exam $exam): array
    {
        return $users->mapWithKeys(fn (User $u) => [$u->id => $this->valuesFor($u, $exam)])->all();
    }
}
