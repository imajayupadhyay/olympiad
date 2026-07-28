<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_indexable_server_rendered_seo_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $response->assertSee('Online Olympiad Exams for Class 1-12 in India | National Olympiad Hunt', false);
        $response->assertSee('online olympiad exams, olympiad exams for class 1 to 12', false);
        $response->assertSee('index, follow, max-image-preview:large', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('EducationalOrganization', false);
        $response->assertSee('https://www.clarity.ms/tag/', false);
        $response->assertSee('xtjc6kiloc', false);
    }

    public function test_non_home_pages_receive_noindex_header(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->get('/student/dashboard')
            ->assertRedirect('/login')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->get('/olympiad-secure-login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_sitemap_only_exposes_the_public_homepage(): void
    {
        $siteUrl = rtrim(config('app.url'), '/');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>'.$siteUrl.'/</loc>', false);
        $response->assertDontSee('/admin', false);
        $response->assertDontSee('/student', false);
        $response->assertDontSee('/login', false);
        $response->assertDontSee('/olympiad-secure-login', false);
    }

    public function test_robots_file_does_not_reveal_admin_urls(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://neoexam.org/sitemap.xml', $robots);
        $this->assertStringNotContainsString('/admin', $robots);
        $this->assertStringNotContainsString('/olympiad-secure-login', $robots);
    }
}
