<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Support\Collection;

class ExamCatalogueService
{
    /**
     * One card per SUBJECT for the homepage "Upcoming olympiads" carousel.
     *
     * Every subject has a separate exam row per class, so listing exams one-per-card
     * rendered "Science · Science · Science…". This rolls those rows up so a subject
     * appears once, summarising the class range and the cheapest fee behind it.
     *
     * Display-only: the per-class exam rows are untouched, and /exams, /marketing and
     * the enrolment pipeline still consume the flat list.
     *
     * Mirrors the client-side grouping in Pages/Public/Exams/Index.vue -> groups().
     * Keep the two in sync until /exams is migrated onto this service.
     *
     * @return list<array<string, mixed>>
     */
    public function homepageSubjectCards(int $limit = 12): array
    {
        $exams = Exam::query()
            ->where('status', 'published')
            ->whereHas('subject', fn ($q) => $q->where('is_active', true))
            ->with([
                'subject:id,name,sort_order',
                'classLevel:id,level',
            ])
            // Keep id/subject_id/class_level_id — the eager loads and grouping need them.
            ->get(['id', 'subject_id', 'class_level_id', 'starts_at', 'ends_at', 'fee_amount']);

        return $exams
            ->groupBy('subject_id')
            ->map(fn (Collection $group) => $this->card($group))
            ->filter()
            // The admin's subject order (Settings -> Subjects), name breaking ties.
            ->sortBy([
                fn (array $a, array $b) => $a['_order'] <=> $b['_order'],
                fn (array $a, array $b) => $a['name'] <=> $b['name'],
            ])
            ->take($limit)
            ->map(function (array $card) {
                unset($card['_order']);

                return $card;
            })
            // REQUIRED: groupBy keys by subject_id, so without values() Inertia emits a
            // JSON object instead of a list and the carousel's .length math breaks.
            ->values()
            ->all();
    }

    /**
     * The public /exam-dates calendar: one entry per SUBJECT SESSION.
     *
     * Exams are stored one row per class, so a session is every published exam of a
     * subject that shares the same start, end and duration — "Mathematics, Class 1–5,
     * 15 Nov 10:00 AM" is one card even though it is five exam rows. A subject that
     * runs different classes on different days yields one card per day.
     *
     * Times are formatted here, not in the browser. The admin form posts a
     * datetime-local wall time that is stored unconverted, so formatting the stored
     * value shows exactly what the admin typed; serialising it as an ISO instant would
     * let the viewer's timezone shift it.
     *
     * Only sessions that have not finished are listed: anything starting today or
     * later, plus earlier-starting windows that are still open.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingSchedule(): array
    {
        $now = now();

        $exams = Exam::query()
            ->where('status', 'published')
            ->whereNotNull('starts_at')
            ->whereHas('subject', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q
                ->where('starts_at', '>=', $now->copy()->startOfDay())
                ->orWhere('ends_at', '>', $now))
            ->with([
                'subject:id,name,icon,color,sort_order',
                'classLevel:id,level,label',
            ])
            ->orderBy('starts_at')
            ->get(['id', 'subject_id', 'class_level_id', 'starts_at', 'ends_at', 'duration_minutes']);

        return $exams
            // Defensive: a window that already closed (e.g. ends before it starts) never shows.
            ->reject(fn (Exam $e) => $e->availabilityState() === 'closed')
            ->groupBy(fn (Exam $e) => implode('|', [
                $e->subject_id,
                $e->starts_at->format('Y-m-d H:i'),
                $e->ends_at?->format('Y-m-d H:i'),
                $e->duration_minutes,
            ]))
            ->map(fn (Collection $session) => $this->session($session))
            ->sortBy([
                fn (array $a, array $b) => $a['_starts'] <=> $b['_starts'],
                fn (array $a, array $b) => $a['_order'] <=> $b['_order'],
                fn (array $a, array $b) => $a['subject']['name'] <=> $b['subject']['name'],
            ])
            ->map(function (array $session) {
                unset($session['_starts'], $session['_order']);

                return $session;
            })
            // groupBy keys by the session key — values() keeps the Inertia prop a list.
            ->values()
            ->all();
    }

    /**
     * One subject session for the exam calendar.
     *
     * @param  Collection<int, Exam>  $exams  same subject, start, end and duration
     * @return array<string, mixed>
     */
    private function session(Collection $exams): array
    {
        /** @var Exam $first */
        $first = $exams->first();
        $subject = $first->subject;
        $start = $first->starts_at;
        $end = $first->ends_at;
        $sameDayEnd = $end && $end->isSameDay($start);

        return [
            'key' => $subject->id.'-'.$start->format('YmdHi').'-'.$exams->pluck('id')->min(),
            'subject' => [
                'name' => $subject->name,
                'icon' => $subject->icon,
                'color' => $subject->color,
            ],
            'class_range' => $this->classRuns($exams),
            'date' => $start->format('Y-m-d'),
            'day' => $start->format('j'),
            'month' => $start->format('M'),
            'weekday' => $start->format('l'),
            'date_label' => $start->format('l, j F Y'),
            'month_key' => $start->format('Y-m'),
            'month_label' => $start->format('F Y'),
            'start_time' => $start->format('g:i A'),
            'end_time' => $sameDayEnd ? $end->format('g:i A') : null,
            'closes_label' => $end && ! $sameDayEnd ? $end->format('D, j M · g:i A') : null,
            'duration_minutes' => $first->duration_minutes,
            'status' => $exams->contains(fn (Exam $e) => $e->availabilityState() === 'live') ? 'live' : 'upcoming',
            '_starts' => $start->getTimestamp(),
            '_order' => (int) ($subject->sort_order ?? PHP_INT_MAX),
        ];
    }

    /**
     * "Class 7", "Class 1–5", or "Class 1–3, 6–8" when the covered classes have gaps.
     *
     * Unlike classRange() the calendar spells gaps out: a student checking whether
     * their own class sits this paper must not be told "Class 1–8" when Class 4 isn't in it.
     *
     * @param  Collection<int, Exam>  $exams
     */
    private function classRuns(Collection $exams): string
    {
        $levels = $exams
            ->map(fn (Exam $e) => $e->classLevel?->level)
            ->reject(fn ($level) => $level === null)
            ->map(fn ($level) => (int) $level)
            ->unique()
            ->sort()
            ->values();

        if ($levels->isEmpty()) {
            // No numeric level recorded: fall back to the class labels themselves.
            return $exams->map(fn (Exam $e) => $e->classLevel?->label)->filter()->unique()->implode(', ') ?: 'All classes';
        }

        $runs = [];
        $runStart = $previous = $levels->first();

        foreach ($levels->slice(1) as $level) {
            if ($level !== $previous + 1) {
                $runs[] = $runStart === $previous ? "{$runStart}" : "{$runStart}–{$previous}";
                $runStart = $level;
            }
            $previous = $level;
        }
        $runs[] = $runStart === $previous ? "{$runStart}" : "{$runStart}–{$previous}";

        return 'Class '.implode(', ', $runs);
    }

    /**
     * Roll one subject's published exams into a single card, or null when the
     * subject has nothing left to offer.
     *
     * @param  Collection<int, Exam>  $exams
     * @return array<string, mixed>|null
     */
    private function card(Collection $exams): ?array
    {
        $subject = $exams->first()->subject;

        if (! $subject instanceof Subject) {
            return null; // Defensive: subject_id is NOT NULL with restrictOnDelete.
        }

        // The section promises "upcoming olympiads", so a subject whose exam windows
        // have all closed drops out entirely, and everything below is derived from
        // the still-open exams only.
        $open = $exams->reject(fn (Exam $e) => $e->availabilityState() === 'closed');

        if ($open->isEmpty()) {
            return null;
        }

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'classRange' => $this->classRange($open),
            'fee' => $this->feeLabel($open),
            'ribbon' => $open->contains(fn (Exam $e) => $e->availabilityState() === 'live') ? 'LIVE' : '',
            '_order' => (int) ($subject->sort_order ?? PHP_INT_MAX),
        ];
    }

    /**
     * "Class 7" or "Class 1–10" from the class levels the subject actually covers.
     *
     * A subject offering only Classes 1, 2, 9 and 10 still reads "Class 1–10". That
     * matches how /exams already presents it and is not worth enumerating per class.
     *
     * @param  Collection<int, Exam>  $exams
     */
    private function classRange(Collection $exams): string
    {
        $levels = $exams
            ->map(fn (Exam $e) => $e->classLevel?->level)
            ->reject(fn ($level) => $level === null)
            ->map(fn ($level) => (int) $level);

        if ($levels->isEmpty()) {
            return $exams->count() === 1 ? '1 class' : $exams->count().' classes';
        }

        $min = $levels->min();
        $max = $levels->max();

        return $min === $max ? "Class {$min}" : "Class {$min}–{$max}";
    }

    /**
     * "Free", "₹299", or "From ₹299" when classes are priced differently.
     *
     * fee_amount is cast decimal:2, so it arrives as a string — cast to float before
     * comparing. When a subject mixes free and paid classes we quote the cheapest
     * PAID fee: saying "Free" would overpromise, and "From ₹0" is meaningless.
     *
     * @param  Collection<int, Exam>  $exams
     */
    private function feeLabel(Collection $exams): string
    {
        $fees = $exams->map(fn (Exam $e) => (float) $e->fee_amount);
        $paid = $fees->filter(fn (float $fee) => $fee > 0.0);

        if ($paid->isEmpty()) {
            return 'Free';
        }

        $amount = '₹'.number_format($paid->min(), 0);

        return $fees->unique()->count() > 1 ? 'From '.$amount : $amount;
    }
}
