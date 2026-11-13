<?php

namespace App\Services\Notifications;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * The single audience resolver for every notification channel (workflow.md §13.2).
 * The browser sends rules and ids only; this class turns them into one query that
 * the live count, the matched-students table and the send snapshot all share, so
 * the number an admin sees is the number that gets sent.
 *
 * Combination rules: groups are ANDed, values inside a multi-select group are ORed.
 * "Olympiad subject" and "Exam" define the exam scope; Enrollment and Attempt are
 * evaluated inside that scope (scope + "any" enrollment means enrolled in it).
 */
class NotificationAudienceService
{
    public const MAX_IDS = 5000;

    public const DEFAULTS = [
        'mode' => 'filters',
        'student_status' => 'active',
        'class_level_ids' => [],
        'subject_ids' => [],
        'exam_ids' => [],
        'enrollment_status' => 'all',
        'attempt_status' => 'all',
        'payment_status' => 'all',
        'hall_ticket' => 'all',
        'registration_source' => 'all',
        'state' => '',
        'city' => '',
        'school' => '',
        'search' => '',
        'excluded_ids' => [],
        'selected_ids' => [],
    ];

    /** Validation rules for an `audience` payload. */
    public function rules(string $prefix = 'audience'): array
    {
        $p = $prefix.'.';

        return [
            $prefix => ['required', 'array'],
            $p.'mode' => ['required', Rule::in(['filters', 'selected'])],
            $p.'student_status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            $p.'class_level_ids' => ['nullable', 'array', 'max:50'],
            $p.'class_level_ids.*' => ['integer', 'exists:class_levels,id'],
            $p.'subject_ids' => ['nullable', 'array', 'max:50'],
            $p.'subject_ids.*' => ['integer', 'exists:subjects,id'],
            $p.'exam_ids' => ['nullable', 'array', 'max:100'],
            $p.'exam_ids.*' => ['integer', 'exists:exams,id'],
            $p.'enrollment_status' => ['nullable', Rule::in(['all', 'enrolled', 'not_enrolled', 'cancelled'])],
            $p.'attempt_status' => ['nullable', Rule::in(['all', 'attempted', 'not_attempted'])],
            $p.'payment_status' => ['nullable', Rule::in(['all', 'paid', 'unpaid', 'pending', 'failed', 'refunded'])],
            $p.'hall_ticket' => ['nullable', Rule::in(['all', 'approved', 'pending', 'rejected', 'none'])],
            $p.'registration_source' => ['nullable', Rule::in(['all', 'website', 'marketing', 'admin'])],
            $p.'state' => ['nullable', 'string', 'max:80'],
            $p.'city' => ['nullable', 'string', 'max:80'],
            $p.'school' => ['nullable', 'string', 'max:200'],
            $p.'search' => ['nullable', 'string', 'max:100'],
            $p.'excluded_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            $p.'excluded_ids.*' => ['integer'],
            $p.'selected_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            $p.'selected_ids.*' => ['integer'],
        ];
    }

    /** Fills defaults and normalises ids, so stored filters are comparable. */
    public function normalise(array $audience): array
    {
        $a = array_merge(self::DEFAULTS, array_intersect_key($audience, self::DEFAULTS));

        foreach (['class_level_ids', 'subject_ids', 'exam_ids', 'excluded_ids', 'selected_ids'] as $key) {
            $a[$key] = collect($a[$key] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values()->all();
        }

        foreach (['state', 'city', 'school', 'search'] as $key) {
            $a[$key] = trim((string) ($a[$key] ?? ''));
        }

        foreach (['student_status' => 'active', 'enrollment_status' => 'all', 'attempt_status' => 'all', 'payment_status' => 'all', 'hall_ticket' => 'all', 'registration_source' => 'all'] as $key => $default) {
            $a[$key] = $a[$key] ?: $default;
        }

        if ($a['mode'] === 'selected') {
            $a['excluded_ids'] = [];
        }

        return $a;
    }

    /** Everyone the rules match, before channel reachability and exclusions. */
    public function matched(array $audience): Builder
    {
        $a = $this->normalise($audience);
        $query = User::query()->where('role', 'student');

        if ($a['mode'] === 'selected') {
            return $query->whereIn('users.id', $a['selected_ids'] ?: [0]);
        }

        match ($a['student_status']) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        if ($a['class_level_ids']) {
            $query->whereIn('class_level_id', $a['class_level_ids']);
        }

        $this->applyExamScope($query, $a);

        match ($a['payment_status']) {
            'paid' => $query->whereHas('payments', fn (Builder $p) => $p->where('status', 'paid')),
            'unpaid' => $query->whereDoesntHave('payments', fn (Builder $p) => $p->where('status', 'paid')),
            'pending' => $query->whereHas('payments', fn (Builder $p) => $p->where('status', 'created')),
            'failed' => $query->whereHas('payments', fn (Builder $p) => $p->where('status', 'failed')),
            'refunded' => $query->whereHas('payments', fn (Builder $p) => $p->where('status', 'refunded')),
            default => null,
        };

        match ($a['hall_ticket']) {
            'approved', 'pending', 'rejected' => $query->whereHas('hallTicket', fn (Builder $t) => $t->where('status', $a['hall_ticket'])),
            'none' => $query->whereDoesntHave('hallTicket'),
            default => null,
        };

        match ($a['registration_source']) {
            'website' => $query->where(fn (Builder $q) => $q->where('registration_source', 'website')->orWhereNull('registration_source')),
            'marketing', 'admin' => $query->where('registration_source', $a['registration_source']),
            default => null,
        };

        if ($a['state'] !== '') {
            $query->where('state', $a['state']);
        }
        if ($a['city'] !== '') {
            $query->where('city', 'like', $this->like($a['city']));
        }
        if ($a['school'] !== '') {
            $query->where('school', 'like', $this->like($a['school']));
        }
        if ($a['search'] !== '') {
            $term = $this->like($a['search']);
            $query->where(fn (Builder $q) => $q->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('phone_e164', 'like', $term)
                ->orWhere('school', 'like', $term));
        }

        return $query;
    }

    /** The final recipient set for a channel: matched, minus exclusions, minus unreachable. */
    public function recipients(array $audience, string $channel): Builder
    {
        $a = $this->normalise($audience);
        $query = $this->matched($a);

        if ($a['excluded_ids']) {
            $query->whereNotIn('users.id', $a['excluded_ids']);
        }

        return $this->reachable($query, $channel);
    }

    public function reachable(Builder $query, string $channel): Builder
    {
        return match ($channel) {
            'email' => $query->whereNotNull('email')->where('email', '!=', ''),
            'whatsapp' => $query->whereNotNull('phone_e164')->where('phone_e164', '!=', ''),
            default => $query,
        };
    }

    /** Numbers for the live counter. */
    public function summary(array $audience, string $channel): array
    {
        $a = $this->normalise($audience);
        $matched = $this->matched($a);
        $matchedCount = (clone $matched)->count();
        $excluded = $a['excluded_ids'] ? (clone $matched)->whereIn('users.id', $a['excluded_ids'])->count() : 0;
        $recipients = $this->recipients($a, $channel)->count();

        return [
            'matched' => $matchedCount,
            'excluded' => $excluded,
            'unreachable' => max(0, $matchedCount - $excluded - $recipients),
            'recipients' => $recipients,
            'missing_selected' => $a['mode'] === 'selected' ? max(0, count($a['selected_ids']) - $matchedCount) : 0,
        ];
    }

    /** One page of matched students with per-row reachability and exclusion flags. */
    public function page(array $audience, string $channel, int $page = 1, int $perPage = 25): array
    {
        $a = $this->normalise($audience);
        $excluded = array_flip($a['excluded_ids']);

        $paginator = $this->matched($a)
            ->with(['classLevel:id,label', 'hallTicket:id,user_id,status'])
            ->orderBy('name')->orderBy('users.id')
            ->paginate($perPage, ['users.id', 'name', 'email', 'phone', 'phone_e164', 'school', 'city', 'state', 'class_level_id', 'is_active'], 'page', max(1, $page));

        return [
            'data' => collect($paginator->items())->map(fn (User $u) => $this->row($u, $channel) + [
                'excluded' => isset($excluded[$u->id]),
            ])->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /** Search for the "Pick students" mode. */
    public function search(?string $term, array $ids = [], int $limit = 20): array
    {
        $query = User::query()->where('role', 'student')->with('classLevel:id,label');

        if ($ids) {
            $query->whereIn('id', array_slice($ids, 0, 200));
        } elseif (filled($term)) {
            $like = $this->like($term);
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('phone_e164', 'like', $like)
                ->orWhere('school', 'like', $like)
                ->orWhere('city', 'like', $like));
        } else {
            $query->latest('id');
        }

        return $query->orderBy('name')->limit($ids ? 200 : $limit)
            ->get(['id', 'name', 'email', 'phone', 'phone_e164', 'school', 'city', 'state', 'class_level_id', 'is_active'])
            ->map(fn (User $u) => $this->row($u, null))
            ->all();
    }

    /** Human-readable chips for history ("Class 5, Class 6", "Exam: …"). */
    public function describe(array $audience): array
    {
        $a = $this->normalise($audience);

        if ($a['mode'] === 'selected') {
            return [count($a['selected_ids']).' hand-picked student'.(count($a['selected_ids']) === 1 ? '' : 's')];
        }

        $chips = [];
        if ($a['class_level_ids']) {
            $chips[] = ClassLevel::whereIn('id', $a['class_level_ids'])->orderBy('sort_order')->pluck('label')->join(', ');
        }
        if ($a['subject_ids']) {
            $chips[] = 'Olympiad: '.Subject::whereIn('id', $a['subject_ids'])->pluck('name')->join(', ');
        }
        if ($a['exam_ids']) {
            $names = Exam::whereIn('id', $a['exam_ids'])->pluck('name');
            $chips[] = 'Exam: '.($names->count() > 2 ? $names->take(2)->join(', ').' +'.($names->count() - 2) : $names->join(', '));
        }

        $labels = [
            'student_status' => ['inactive' => 'Inactive accounts', 'all' => 'All accounts'],
            'enrollment_status' => ['enrolled' => 'Enrolled', 'not_enrolled' => 'Not enrolled', 'cancelled' => 'Cancelled access'],
            'attempt_status' => ['attempted' => 'Attempted', 'not_attempted' => 'Not attempted'],
            'payment_status' => ['paid' => 'Paid', 'unpaid' => 'Unpaid', 'pending' => 'Payment pending', 'failed' => 'Payment failed', 'refunded' => 'Refunded'],
            'hall_ticket' => ['approved' => 'Hall ticket approved', 'pending' => 'Hall ticket pending', 'rejected' => 'Hall ticket rejected', 'none' => 'No hall ticket'],
            'registration_source' => ['website' => 'Via website', 'marketing' => 'Via marketing', 'admin' => 'Added by admin'],
        ];
        foreach ($labels as $key => $map) {
            if (isset($map[$a[$key]])) {
                $chips[] = $map[$a[$key]];
            }
        }

        foreach (['state' => 'State', 'city' => 'City', 'school' => 'School', 'search' => 'Search'] as $key => $label) {
            if ($a[$key] !== '') {
                $chips[] = $label.': '.$a[$key];
            }
        }
        if ($a['excluded_ids']) {
            $chips[] = count($a['excluded_ids']).' excluded';
        }

        return $chips ?: ['All active students'];
    }

    /** Exam ids the subject/exam filters scope to (null = no scope). */
    public function scopeExamIds(array $a): ?array
    {
        if ($a['exam_ids']) {
            return $a['exam_ids'];
        }
        if ($a['subject_ids']) {
            return Exam::whereIn('subject_id', $a['subject_ids'])->pluck('id')->all() ?: [0];
        }

        return null;
    }

    private function applyExamScope(Builder $query, array $a): void
    {
        $scope = $this->scopeExamIds($a);
        $inScope = fn (Builder $q) => $scope === null ? $q : $q->whereIn('exam_id', $scope);
        $status = $a['enrollment_status'] === 'all' && $scope !== null ? 'enrolled' : $a['enrollment_status'];

        match ($status) {
            'enrolled' => $query->whereHas('enrollments', fn (Builder $e) => $inScope($e)->where('status', 'enrolled')),
            'not_enrolled' => $query->whereDoesntHave('enrollments', fn (Builder $e) => $inScope($e)->where('status', 'enrolled')),
            'cancelled' => $query->whereHas('enrollments', fn (Builder $e) => $inScope($e)->where('status', 'cancelled')),
            default => null,
        };

        $finished = ['submitted', 'timed_out', 'auto_submitted', 'in_progress'];
        match ($a['attempt_status']) {
            'attempted' => $query->whereHas('examAttempts', fn (Builder $t) => $inScope($t)->whereIn('status', $finished)),
            'not_attempted' => $query->whereDoesntHave('examAttempts', fn (Builder $t) => $inScope($t)),
            default => null,
        };
    }

    private function row(User $u, ?string $channel): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone_e164 ?: $u->phone,
            'has_whatsapp_number' => filled($u->phone_e164),
            'school' => $u->school,
            'city' => $u->city,
            'state' => $u->state,
            'class' => $u->classLevel?->label,
            'is_active' => (bool) $u->is_active,
            'reachable' => match ($channel) {
                'email' => filled($u->email),
                'whatsapp' => filled($u->phone_e164),
                default => true,
            },
        ];
    }

    private function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }
}
