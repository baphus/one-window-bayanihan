<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HelpdeskArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_props_and_direct_urls_exclude_internal_articles(): void
    {
        $this->get('/help')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Helpdesk/Index')
                ->where('helpdeskArticles', fn ($articles) => collect($articles)->every(fn ($article) => $article['audience'] === 'public')));
        $this->get('/help/getting-started-system-admin')->assertNotFound();
        $this->get('/help/getting-started-case-managers')->assertNotFound();
        $this->getJson('/help/getting-started-system-admin/feedback')->assertNotFound();
        $this->postJson('/help/getting-started-system-admin/feedback', ['vote' => 'yes'])->assertNotFound();
    }

    public function test_role_matrix_and_unknown_audience_fail_closed(): void
    {
        $service = app(HelpdeskArticleService::class);
        foreach ([null, 'OFW', 'CASE_MANAGER', 'AGENCY', 'ADMIN'] as $role) {
            $user = $role ? User::factory()->make(['role' => $role]) : null;
            $this->assertNotNull($service->findAllowed('using-public-tracking-portal', $user));
            $this->assertSame($role === 'ADMIN', $service->findAllowed('getting-started-system-admin', $user) !== null);
            $this->assertSame(in_array($role, ['CASE_MANAGER', 'ADMIN'], true), $service->findAllowed('getting-started-case-managers', $user) !== null);
            $this->assertSame(in_array($role, ['AGENCY', 'ADMIN'], true), $service->findAllowed('getting-started-agency-focal', $user) !== null);
            $this->assertSame($role === 'ADMIN', HelpdeskArticleService::allows('unknown', $role));
        }
        $this->assertCount(46, $service->all());
        foreach ($service->all() as $article) {
            $this->assertContains($article['audience'], ['public', 'staff', 'CASE_MANAGER', 'AGENCY', 'ADMIN']);
        }
    }
}
