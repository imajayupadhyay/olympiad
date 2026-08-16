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
