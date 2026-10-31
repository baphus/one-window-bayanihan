<?php

namespace Tests\Feature;

use App\Models\HelpdeskArticleFeedback;
use App\Models\User;
use App\Services\HelpdeskFeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HelpdeskArticleFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cookie_round_trip_updates_one_vote_and_reads_it_back(): void
    {
        $url = '/help/using-public-tracking-portal/feedback';
        $response = $this->getJson($url)->assertOk()->assertJson(['vote' => null]);
        $cookie = $response->getCookie('helpdesk_voter', false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->withCredentials()->withUnencryptedCookie('helpdesk_voter', $cookie->getValue());
        $this->postJson($url, ['vote' => 'yes'])->assertOk()->assertJson(['vote' => 'yes']);
        $this->postJson($url, ['vote' => 'no'])->assertOk();
        $this->assertDatabaseCount('helpdesk_article_feedback', 1);
        $this->assertDatabaseHas('helpdesk_article_feedback', ['article_slug' => 'using-public-tracking-portal', 'vote' => 'no']);
        $this->getJson($url)->assertOk()->assertJson(['vote' => 'no']);
    }

    public function test_guest_must_initialize_before_voting(): void
    {
        $this->postJson('/help/using-public-tracking-portal/feedback', ['vote' => 'yes'])->assertStatus(409);
        $this->assertDatabaseCount('helpdesk_article_feedback', 0);
    }

    public function test_account_votes_update_and_invalid_input_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $url = '/help/using-public-tracking-portal/feedback';
        $this->actingAs($user)->postJson($url, ['vote' => 'yes'])->assertOk();
        $this->postJson($url, ['vote' => 'no'])->assertOk();
        $this->assertDatabaseCount('helpdesk_article_feedback', 1);
        $this->assertDatabaseHas('helpdesk_article_feedback', ['identity_hash' => hash('sha256', 'user:'.$user->id), 'vote' => 'no']);
        foreach ([null, 'maybe', ['yes']] as $invalid) {
            $this->postJson($url, ['vote' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('vote');
        }
    }

    public function test_missing_article_cannot_receive_feedback(): void
    {
        $this->getJson('/help/nonexistent-article/feedback')->assertNotFound();
        $this->postJson('/help/nonexistent-article/feedback', ['vote' => 'yes'])->assertNotFound();
        $this->assertDatabaseCount('helpdesk_article_feedback', 0);
    }

    public function test_retention_uses_last_vote_update(): void
    {
        $this->freezeTime();
        foreach (['expired' => now()->subMonthsNoOverflow(12)->subSecond(), 'current' => now()] as $identity => $updated) {
            HelpdeskArticleFeedback::insert(['id' => (string) Str::uuid(), 'article_slug' => 'using-public-tracking-portal',
                'identity_hash' => hash('sha256', $identity), 'vote' => 'no', 'created_at' => now()->subYears(2), 'updated_at' => $updated]);
        }
        $this->assertNull(app(HelpdeskFeedbackService::class)->currentVote('using-public-tracking-portal', hash('sha256', 'expired')));
        $this->assertSame('no', app(HelpdeskFeedbackService::class)->currentVote('using-public-tracking-portal', hash('sha256', 'current')));
        $this->artisan('app:cleanup-helpdesk-article-feedback')->assertSuccessful();
        $this->assertDatabaseCount('helpdesk_article_feedback', 1);
        $this->assertDatabaseHas('helpdesk_article_feedback', ['identity_hash' => hash('sha256', 'current')]);
    }

    public function test_summary_is_admin_only_and_includes_zero_vote_articles(): void
    {
        $this->get('/admin/helpdesk-feedback')->assertRedirect();
        foreach (['OFW', 'CASE_MANAGER', 'AGENCY'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/admin/helpdesk-feedback')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))->get('/admin/helpdesk-feedback')
            ->assertOk()->assertInertia(fn ($page) => $page->component('Admin/HelpdeskFeedback/Index')
            ->has('articles')->where('articles.0.total', 0)->where('articles.0.percent', null));
    }
}
