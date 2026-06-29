<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminHomepageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_homepage_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.content'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Content/Index')
                ->has('sections')
                ->where('stats.total', count(HomepageSection::defaults()))
                ->where('sections.0.key', 'seo')
            );
    }

    public function test_admin_can_update_a_homepage_section_used_by_public_homepage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        HomepageSection::ensureDefaults();
        $hero = HomepageSection::where('key', 'hero')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.content.homepage.update', $hero), [
                'is_enabled' => true,
                'content' => [
                    ...$hero->content,
                    'badge_text' => 'Managed from admin',
                    'headline_highlight' => 'dynamic content',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Home/Index')
                ->where('homepageContent.hero.badge_text', 'Managed from admin')
                ->where('homepageContent.hero.headline_highlight', 'dynamic content')
            );
    }

    public function test_admin_can_hide_and_reset_homepage_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        HomepageSection::ensureDefaults();
        $faq = HomepageSection::where('key', 'faq')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.content.homepage.update', $faq), [
                'is_enabled' => false,
                'content' => [
                    ...$faq->content,
                    'title' => 'Custom FAQ title',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($faq->fresh()->is_enabled);

        $this->actingAs($admin)
            ->post(route('admin.content.homepage.reset', $faq))
            ->assertRedirect();

        $faq->refresh();

        $this->assertTrue($faq->is_enabled);
        $this->assertSame(HomepageSection::defaultFor('faq')['content']['title'], $faq->content['title']);
    }
}
