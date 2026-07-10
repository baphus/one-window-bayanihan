<?php

namespace Tests\Feature;

use App\Http\Controllers\ChatbotController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ChatbotKeywordMatchTest extends TestCase
{
    use RefreshDatabase;

    private ChatbotController $controller;

    private ReflectionMethod $matchArticles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = app(ChatbotController::class);
        $this->matchArticles = new ReflectionMethod($this->controller, 'matchArticles');
        $this->matchArticles->setAccessible(true);
    }

    private function publicUser(): array
    {
        return ['label' => 'a public OFW (not logged in)', 'groups' => ['OFW & Public']];
    }

    public function test_colors_question_includes_tracking_portal(): void
    {
        $result = $this->matchArticles->invoke(
            $this->controller,
            'What do the different colors mean for case status tracking?',
            $this->publicUser(),
        );

        $this->assertNotEmpty($result);
        $slugs = array_map(fn ($r) => ($r['type'] ?? '?').':'.($r['slug'] ?? $r['key'] ?? '?'), $result);

        // The tracking portal article contains "color-coded status indicators"
        // which is the only source for color information
        $this->assertContains('helpdesk:using-public-tracking-portal', $slugs,
            'Tracking portal article should be in top matches. Got: '.json_encode($slugs)
        );
    }

    public function test_login_question_includes_tracking_portal(): void
    {
        $result = $this->matchArticles->invoke(
            $this->controller,
            'How do I log in to the tracking portal?',
            $this->publicUser(),
        );

        $this->assertNotEmpty($result);
        $slugs = array_map(fn ($r) => ($r['type'] ?? '?').':'.($r['slug'] ?? $r['key'] ?? '?'), $result);

        $this->assertContains('helpdesk:using-public-tracking-portal', $slugs,
            'Tracking portal article should be in top matches. Got: '.json_encode($slugs)
        );
    }

    public function test_track_case_question_includes_tracking_portal(): void
    {
        $result = $this->matchArticles->invoke(
            $this->controller,
            'How do I track my case?',
            $this->publicUser(),
        );

        $this->assertNotEmpty($result);
        $slugs = array_map(fn ($r) => ($r['type'] ?? '?').':'.($r['slug'] ?? $r['key'] ?? '?'), $result);

        $this->assertContains('helpdesk:using-public-tracking-portal', $slugs,
            'Tracking portal article should be in top matches. Got: '.json_encode($slugs)
        );
    }

    public function test_troubleshooting_question_includes_article(): void
    {
        $result = $this->matchArticles->invoke(
            $this->controller,
            'Why is my OTP not arriving?',
            $this->publicUser(),
        );

        $this->assertNotEmpty($result);
        $slugs = array_map(fn ($r) => ($r['type'] ?? '?').':'.($r['slug'] ?? $r['key'] ?? '?'), $result);

        $this->assertContains('helpdesk:troubleshooting-common-issues', $slugs,
            'Troubleshooting article should be in top matches. Got: '.json_encode($slugs)
        );
    }

    public function test_services_question_includes_services_article(): void
    {
        $result = $this->matchArticles->invoke(
            $this->controller,
            'What services are available for OFWs?',
            $this->publicUser(),
        );

        $this->assertNotEmpty($result);
        $slugs = array_map(fn ($r) => ($r['type'] ?? '?').':'.($r['slug'] ?? $r['key'] ?? '?'), $result);

        $this->assertContains('helpdesk:ofw-assistance-services-available', $slugs,
            'Services article should be in top matches. Got: '.json_encode($slugs)
        );
    }

    public function test_status_meanings_question_includes_statuses_article(): void
    {
        $result = $this->matchArticles->invoke(
            $this->controller,
            'What does case status mean?',
            $this->publicUser(),
        );

        $this->assertNotEmpty($result);
        $slugs = array_map(fn ($r) => ($r['type'] ?? '?').':'.($r['slug'] ?? $r['key'] ?? '?'), $result);

        $this->assertContains('helpdesk:understanding-case-statuses-tracker-numbers', $slugs,
            'Statuses article should be in top matches. Got: '.json_encode($slugs)
        );
    }
}
