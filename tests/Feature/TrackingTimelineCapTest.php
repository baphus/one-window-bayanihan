<?php

namespace Tests\Feature;

use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public tracking endpoint is unauthenticated and re-renders on every
 * refresh, so the event read has to be bounded. Bounding it must not change
 * what the timeline means: the cap keeps the NEWEST events, and presentation
 * stays oldest-first.
 */
class TrackingTimelineCapTest extends TestCase
{
    use RefreshDatabase;

    private const CAP = 200;

    private function trackCase(CaseFile $case): CaseFile
    {
        return $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ]);
    }

    /**
     * @return array<int, string> Timeline titles in payload order.
     */
    private function timelineTitles(CaseFile $case): array
    {
        $data = app(TrackingService::class)->buildTrackingData($this->trackCase($case));

        return array_column($data['milestoneTimeline'], 'title');
    }

    private function seedEvents(CaseFile $case, int $count): void
    {
        $base = now()->subDays($count + 1);

        foreach (range(1, $count) as $index) {
            CaseEvent::create([
                'case_id' => $case->id,
                'type' => 'case_opened',
                'title' => "Event {$index}",
                'actor_type' => 'system',
                'occurred_at' => $base->copy()->addMinutes($index),
            ]);
        }
    }

    public function test_timeline_is_capped_at_two_hundred_events(): void
    {
        $case = CaseFile::factory()->create();
        $this->seedEvents($case, self::CAP + 75);

        $titles = $this->timelineTitles($case);

        $this->assertCount(self::CAP, $titles);
    }

    public function test_the_cap_keeps_the_newest_events_not_the_oldest(): void
    {
        $case = CaseFile::factory()->create();
        $this->seedEvents($case, self::CAP + 75);

        $titles = $this->timelineTitles($case);

        // 275 events seeded; the newest 200 are 76..275.
        $this->assertContains('Event 275', $titles, 'The most recent event must survive the cap.');
        $this->assertContains('Event 76', $titles, 'The newest 200 include the 76th event.');
        $this->assertNotContains('Event 75', $titles, 'Events older than the newest 200 are trimmed.');
        $this->assertNotContains('Event 1', $titles, 'The oldest event must not push recent activity out.');
    }

    public function test_presentation_order_stays_oldest_first(): void
    {
        $case = CaseFile::factory()->create();
        $this->seedEvents($case, self::CAP + 75);

        $titles = $this->timelineTitles($case);

        $this->assertSame('Event 76', $titles[0], 'The timeline still starts with its oldest retained event.');
        $this->assertSame('Event 275', $titles[array_key_last($titles)], 'The timeline still ends with the newest event.');

        $expected = range(76, 275);
        $actual = array_map(fn (string $title) => (int) str_replace('Event ', '', $title), $titles);
        $this->assertSame($expected, $actual, 'Retained events must come back in ascending chronological order.');
    }

    public function test_events_under_the_cap_are_all_returned_in_order(): void
    {
        $case = CaseFile::factory()->create();
        $this->seedEvents($case, 12);

        $titles = $this->timelineTitles($case);

        $this->assertCount(12, $titles);
        $this->assertSame('Event 1', $titles[0]);
        $this->assertSame('Event 12', $titles[11]);
    }

    public function test_same_instant_events_keep_a_stable_order(): void
    {
        $case = CaseFile::factory()->create();
        $at = now()->subHours(2);

        foreach (['Alpha', 'Bravo', 'Charlie'] as $title) {
            CaseEvent::create([
                'case_id' => $case->id,
                'type' => 'case_opened',
                'title' => $title,
                'actor_type' => 'system',
                'occurred_at' => $at,
            ]);
        }

        // Insertion order breaks ties via `sequence`, in both directions.
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->timelineTitles($case));
    }
}
