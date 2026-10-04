<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Question;
use App\Models\QuestionTag;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuestionTagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Subject $maths;
    private Subject $science;
    private ClassLevel $classFive;
    private ClassLevel $classSix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->maths = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1]);
        $this->science = Subject::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true, 'sort_order' => 2]);
        $this->classFive = ClassLevel::create(['level' => 5, 'label' => 'Class 5', 'is_active' => true, 'sort_order' => 5]);
        $this->classSix = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
    }

    private function questionPayload(array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $this->maths->id,
            'class_level_ids' => [$this->classFive->id],
            'difficulty' => 'medium',
            'question_type' => 'single',
            'question_text' => '<p>What is 1/2 + 1/4?</p>',
            'option_a' => '<p>3/4</p>',
            'option_b' => '<p>1/4</p>',
            'option_c' => '<p>1/2</p>',
            'option_d' => '<p>1</p>',
            'correct_options' => ['a'],
            'marks' => 1,
            'negative_marks' => 0,
        ], $overrides);
    }

    public function test_tag_list_is_scoped_to_the_selected_subject_and_classes(): void
    {
        $mathsFive = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Fractions', 'slug' => 'fractions', 'is_active' => true,
        ]);
        $mathsSix = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classSix->id,
            'name' => 'Integers', 'slug' => 'integers', 'is_active' => true,
        ]);
        QuestionTag::create([
            'subject_id' => $this->science->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Plants', 'slug' => 'plants', 'is_active' => true,
        ]);
        QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Retired Topic', 'slug' => 'retired-topic', 'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.question-tags.index', [
            'subject_id' => $this->maths->id,
            'class_level_ids' => [$this->classFive->id, $this->classSix->id],
        ]))->assertOk();

        $names = collect($response->json('tags'))->pluck('name')->all();

        $this->assertSame(['Fractions', 'Integers'], $names);
        $this->assertSame('Class 5', $response->json('tags.0.class_label'));
        $this->assertSame($mathsFive->id, $response->json('tags.0.id'));
        $this->assertSame($mathsSix->id, $response->json('tags.1.id'));
    }

    public function test_science_question_does_not_see_maths_tags_of_the_same_class(): void
    {
        QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Fractions', 'slug' => 'fractions', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)->getJson(route('admin.question-tags.index', [
            'subject_id' => $this->science->id,
            'class_level_ids' => [$this->classFive->id],
        ]))->assertOk()->assertJsonCount(0, 'tags');
    }

    public function test_creating_a_tag_creates_one_row_per_selected_class(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.question-tags.store'), [
            'name' => '  Percentages  ',
            'subject_id' => $this->maths->id,
            'class_level_ids' => [$this->classFive->id, $this->classSix->id],
        ])->assertCreated()->assertJsonCount(2, 'created_ids');

        $this->assertDatabaseHas('question_tags', [
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Percentages', 'slug' => 'percentages', 'created_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('question_tags', [
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classSix->id,
            'name' => 'Percentages', 'slug' => 'percentages',
        ]);
    }

    public function test_recreating_an_existing_tag_reuses_the_row_and_reactivates_it(): void
    {
        $existing = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Fractions', 'slug' => 'fractions', 'is_active' => false,
        ]);

        $this->actingAs($this->admin)->postJson(route('admin.question-tags.store'), [
            'name' => 'fractions',
            'subject_id' => $this->maths->id,
            'class_level_ids' => [$this->classFive->id],
        ])->assertCreated()->assertJsonPath('created_ids.0', $existing->id);

        $this->assertSame(1, QuestionTag::where('slug', 'fractions')->count());
        $this->assertTrue($existing->refresh()->is_active);
        $this->assertSame('Fractions', $existing->name);
    }

    public function test_question_can_be_saved_with_class_scoped_tags(): void
    {
        $tag = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Fractions', 'slug' => 'fractions', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->questionPayload(['tag_ids' => [$tag->id]]))
            ->assertRedirect(route('admin.questions.index'));

        $question = Question::latest('id')->first();
        $this->assertSame([$tag->id], $question->tags->pluck('id')->all());
    }

    public function test_a_tag_from_another_subject_or_class_is_rejected(): void
    {
        $scienceTag = QuestionTag::create([
            'subject_id' => $this->science->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Plants', 'slug' => 'plants', 'is_active' => true,
        ]);
        $classSixTag = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classSix->id,
            'name' => 'Integers', 'slug' => 'integers', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->questionPayload(['tag_ids' => [$scienceTag->id]]))
            ->assertSessionHasErrors('tag_ids');

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->questionPayload(['tag_ids' => [$classSixTag->id]]))
            ->assertSessionHasErrors('tag_ids');

        $this->assertSame(0, Question::count());
    }

    public function test_updating_a_question_replaces_its_tags_without_touching_the_category(): void
    {
        $fractions = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Fractions', 'slug' => 'fractions', 'is_active' => true,
        ]);
        $decimals = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Decimals', 'slug' => 'decimals', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->questionPayload([
                'topic' => 'Legacy topic text',
                'tag_ids' => [$fractions->id],
            ]));

        $question = Question::latest('id')->first();

        $this->actingAs($this->admin)
            ->put(route('admin.questions.update', $question), $this->questionPayload([
                'topic' => 'Legacy topic text',
                'tag_ids' => [$decimals->id],
                'is_active' => true,
            ]))
            ->assertRedirect(route('admin.questions.index'));

        $question->refresh()->load('tags');

        $this->assertSame([$decimals->id], $question->tags->pluck('id')->all());
        $this->assertSame('Legacy topic text', $question->topic);
        $this->assertNull($question->question_category_id);
    }

    public function test_question_search_matches_tag_names(): void
    {
        $tag = QuestionTag::create([
            'subject_id' => $this->maths->id, 'class_level_id' => $this->classFive->id,
            'name' => 'Photosynthesis', 'slug' => 'photosynthesis', 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.questions.store'), $this->questionPayload(['tag_ids' => [$tag->id]]));

        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['search' => 'Photosynth']))
            ->assertOk()
            ->assertSee('Photosynthesis');
    }

    public function test_students_cannot_read_or_create_question_tags(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->getJson(route('admin.question-tags.index', [
                'subject_id' => $this->maths->id,
                'class_level_ids' => [$this->classFive->id],
            ]))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($student)
            ->postJson(route('admin.question-tags.store'), [
                'name' => 'Sneaky',
                'subject_id' => $this->maths->id,
                'class_level_ids' => [$this->classFive->id],
            ])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(0, QuestionTag::count());
    }
}
