<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The homepage "Upcoming olympiads" carousel shows one card per SUBJECT.
 *
 * Each subject has a separate exam per class, so listing exams one-per-card used to
 * render "Science · Science · Science…". These tests pin the roll-up.
 */
class PublicHomepageExamsTest extends TestCase
{
    use RefreshDatabase;

    private function subject(string $name, int $sortOrder = 1, bool $active = true): Subject
    {
        return Subject::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'is_active' => $active,
            'sort_order' => $sortOrder,
        ]);
    }

    private function classLevel(int $level): ClassLevel
    {
        return ClassLevel::firstOrCreate(
            ['level' => $level],
            ['label' => "Class {$level}", 'is_active' => true, 'sort_order' => $level],
        );
    }

    private function exam(Subject $subject, int $level, array $overrides = []): Exam
    {
        static $n = 0;
        $n++;

        return Exam::create(array_merge([
            'subject_id' => $subject->id,
            'class_level_id' => $this->classLevel($level)->id,
            'name' => "{$subject->name} Olympiad Class {$level}",
            'slug' => 'exam-'.$n,
            'exam_code' => 'EX'.(1000 + $n),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeeks(2),
            'duration_minutes' => 60,
            'fee_amount' => 299,
            'fee_currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> */
    private function cards(): array
    {
        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        return $props['upcomingExams'];
    }

    public function test_each_subject_appears_only_once(): void
    {
        $science = $this->subject('Science', 1);
        $maths = $this->subject('Mathematics', 2);

        foreach (range(1, 3) as $level) {
            $this->exam($science, $level);
            $this->exam($maths, $level);
        }

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('upcomingExams', 2)
            ->where('upcomingExams.0.name', 'Science')
            ->where('upcomingExams.1.name', 'Mathematics'));
    }

    /**
     * The regression that motivates the whole change: one subject's many classes must
     * not crowd another subject out. A client-side dedupe, or a take(8) over exams,
     * would drop Mathematics here.
     */
    public function test_one_subjects_classes_cannot_push_another_subject_off_the_carousel(): void
    {
        $science = $this->subject('Science', 1);
        $maths = $this->subject('Mathematics', 2);

        foreach (range(1, 10) as $level) {
            $this->exam($science, $level, ['starts_at' => now()->addDay()]);
        }

        $this->exam($maths, 5, ['starts_at' => now()->addMonth(), 'ends_at' => now()->addMonths(2)]);

        $names = array_column($this->cards(), 'name');

        $this->assertSame(['Science', 'Mathematics'], $names);
    }

    public function test_card_summarises_class_range_and_cheapest_fee(): void
    {
        $science = $this->subject('Science');
        $this->exam($science, 1, ['fee_amount' => 499]);
        $this->exam($science, 10, ['fee_amount' => 299]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('upcomingExams', 1)
            ->where('upcomingExams.0.classRange', 'Class 1–10')
            ->where('upcomingExams.0.fee', 'From ₹299'));
    }

    public function test_single_class_and_single_price_render_without_a_range_or_from(): void
    {
        $gk = $this->subject('General Knowledge');
        $this->exam($gk, 7, ['fee_amount' => 299]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('upcomingExams.0.classRange', 'Class 7')
            ->where('upcomingExams.0.fee', '₹299'));
    }

    public function test_subject_is_free_only_when_every_class_is_free(): void
    {
        $free = $this->subject('Reasoning', 1);
        $this->exam($free, 1, ['fee_amount' => 0]);
        $this->exam($free, 2, ['fee_amount' => 0]);

        // Mixing a free class with a paid one must quote the cheapest PAID fee,
        // never "Free" (which would overpromise) and never "From ₹0".
        $mixed = $this->subject('English', 2);
        $this->exam($mixed, 1, ['fee_amount' => 0]);
        $this->exam($mixed, 2, ['fee_amount' => 399]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('upcomingExams.0.fee', 'Free')
            ->where('upcomingExams.1.fee', 'From ₹399'));
    }

    public function test_ribbon_is_live_when_any_class_exam_is_open_now(): void
    {
        $science = $this->subject('Science');
        $this->exam($science, 1); // starts next week
        $this->exam($science, 2, ['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('upcomingExams.0.ribbon', 'LIVE'));
    }

    public function test_subjects_whose_exams_have_all_ended_are_hidden(): void
    {
        $over = $this->subject('History', 1);
        $this->exam($over, 1, ['starts_at' => now()->subMonth(), 'ends_at' => now()->subWeek()]);

        $live = $this->subject('Science', 2);
        $this->exam($live, 1);

        $this->assertSame(['Science'], array_column($this->cards(), 'name'));
    }

    public function test_closed_classes_do_not_affect_a_still_open_subjects_summary(): void
    {
        $science = $this->subject('Science');
        $this->exam($science, 1, ['starts_at' => now()->subMonth(), 'ends_at' => now()->subWeek(), 'fee_amount' => 99]);
        $this->exam($science, 8, ['fee_amount' => 299]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('upcomingExams', 1)
            ->where('upcomingExams.0.classRange', 'Class 8')
            ->where('upcomingExams.0.fee', '₹299'));
    }

    public function test_draft_and_archived_exams_are_excluded(): void
    {
        $draft = $this->subject('Draft Subject', 1);
        $this->exam($draft, 1, ['status' => 'draft']);

        $archived = $this->subject('Archived Subject', 2);
        $this->exam($archived, 1, ['status' => 'archived']);

        $this->assertSame([], $this->cards());
    }

    public function test_inactive_subjects_are_excluded(): void
    {
        $hidden = $this->subject('Retired', 1, active: false);
        $this->exam($hidden, 1);

        $shown = $this->subject('Science', 2);
        $this->exam($shown, 1);

        $this->assertSame(['Science'], array_column($this->cards(), 'name'));
    }

    public function test_carousel_is_capped_and_ordered_by_admin_subject_sort_order(): void
    {
        foreach (range(1, 13) as $i) {
            // Descending sort_order, so a correct sort reverses creation order.
            $subject = $this->subject('Subject '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 100 - $i);
            $this->exam($subject, 1);
        }

        $cards = $this->cards();

        $this->assertCount(12, $cards);
        $this->assertSame('Subject 13', $cards[0]['name']);
        $this->assertSame('Subject 02', $cards[11]['name']);
    }

    /**
     * groupBy() keys the collection by subject_id. Without a values() call Inertia
     * serialises an object, and the carousel's length arithmetic silently breaks.
     */
    public function test_payload_is_a_json_list_not_a_keyed_object(): void
    {
        $science = $this->subject('Science');
        $this->exam($science, 1);

        $cards = $this->cards();

        $this->assertSame(array_keys($cards), range(0, count($cards) - 1));
    }
}
