<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    private function seedSchools(): void
    {
        School::insert([
            ['name' => 'Delhi Public School, Panvel', 'address' => '27 Sangurli, Panvel', 'is_active' => true],
            ['name' => 'St. Xavier School', 'address' => 'Mount Road, Chennai', 'is_active' => true],
            ['name' => 'New Delhi Model School', 'address' => 'CP, New Delhi', 'is_active' => true],
            ['name' => 'Old Convent (Closed)', 'address' => 'Nowhere', 'is_active' => false],
        ]);
    }

    public function test_search_returns_matches_with_address_and_ranks_prefix_first(): void
    {
        $this->seedSchools();

        $data = $this->getJson(route('schools.search', ['q' => 'delhi']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $names = array_column($data, 'name');
        // Prefix match ("Delhi Public…") ranks above the contains match ("New Delhi…").
        $this->assertSame('Delhi Public School, Panvel', $names[0]);
        $this->assertContains('New Delhi Model School', $names);
        $this->assertArrayHasKey('address', $data[0]);
    }

    public function test_search_ignores_inactive_and_short_queries(): void
    {
        $this->seedSchools();

        $this->getJson(route('schools.search', ['q' => 'd']))
            ->assertOk()->assertJsonCount(0, 'data');

        $names = array_column($this->getJson(route('schools.search', ['q' => 'convent']))->json('data'), 'name');
        $this->assertNotContains('Old Convent (Closed)', $names);
    }

    public function test_registration_persists_selected_school_and_address(): void
    {
        $class = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);

        $this->post('/register', [
            'name'           => 'Riya Sharma',
            'email'          => 'riya@example.com',
            'class_level_id' => $class->id,
            'school'         => 'Delhi Public School, Panvel',
            'school_address' => '27 Sangurli, Panvel',
        ])->assertRedirect(route('register.olympiads', absolute: false));

        $user = User::where('email', 'riya@example.com')->firstOrFail();
        $this->assertSame('Delhi Public School, Panvel', $user->school);
        $this->assertSame('27 Sangurli, Panvel', $user->school_address);
    }
}
