<?php

namespace Tests\Feature;

use App\Mail\ClientUpdateMail;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\CaseSwimlaneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client-safe swimlane timeline for OFW and public surfaces.
 *
 * Allowlist-built from the same event log as the internal builder, then
 * mapped to a code-free shape at the boundary. The allowlist test below is
 * the contract guard: no raw status codes as values, none of the banned
 * staff keys anywhere in the payload.
 */
class CaseClientSwimlaneTimelineTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const BANNED_KEYS = [
        'status',
        'isTerminal',
        'terminalAt',
        'segmentCount',
        'isOpen',
        'receivedCount',
        'readyToClose',
        'caseManagerLane',
        'note',
    ];

    /** @var list<string> */
    private const BANNED_VALUES = [
        'PENDING',
        'PROCESSING',
        'FOR_COMPLIANCE',
        'COMPLETED',
        'REJECTED',
        'CASE_MANAGER',
    ];

    private function recorder(): CaseEventRecorder
    {
        return app(CaseEventRecorder::class);
    }

    private function swimlane(CaseFile $case): array
    {
        return app(CaseSwimlaneService::class)->buildClientSwimlaneTimeline(CaseFile::findOrFail($case->id));
    }

    private function makeCase(): CaseFile
    {
        $client = Client::factory()->create();

        return CaseFile::factory()->create(['client_id' => $client->id]);
    }

    public function test_multi_transition_referral_has_contiguous_client_labeled_segments(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        $t1 = now()->subDays(3)->setMicroseconds(0);
        $t2 = now()->subDays(2)->setMicroseconds(0);
        $t3 = now()->subDays(2)->addHour()->setMicroseconds(0);
        $t4 = now()->subDay()->setMicroseconds(0);

        $this->travelTo($t1, fn () => $this->recorder()->referralSent($referral));
        $this->travelTo($t2, fn () => $this->recorder()->referralStatusChanged($referral, 'PENDING', 'PROCESSING'));
        $this->travelTo($t3, fn () => $this->recorder()->milestoneAdded($referral, $milestone));
        $this->travelTo($t4, fn () => $this->recorder()->referralStatusChanged($referral, 'PROCESSING', 'FOR_COMPLIANCE'));
        $referral->update(['status' => 'FOR_COMPLIANCE']);

        $lane = $this->swimlane($case)['referrals'][0];

        $this->assertSame(
            ['Awaiting receipt', 'In process', 'Needs documents'],
            array_column($lane['segments'], 'label')
        );
        $this->assertSame($t1->toISOString(), $lane['sentAt']);
        $this->assertSame('Needs documents', $lane['statusLabel']);
        $this->assertTrue($lane['isCurrent']);

        $segments = $lane['segments'];
        $this->assertCount(3, $segments);
        $this->assertSame($segments[0]['end'], $segments[1]['start']);
        $this->assertSame($segments[1]['end'], $segments[2]['start']);
        $this->assertNull($segments[2]['end']);
        $this->assertSame(1, $segments[1]['milestoneCount']);

        foreach ($segments as $segment) {
            $this->assertSame(['label', 'start', 'end', 'milestoneCount'], array_keys($segment));
        }
    }

    public function test_terminal_referral_folds_into_final_labeled_segment(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $t1 = now()->subDays(2)->setMicroseconds(0);
        $t2 = now()->subDay()->setMicroseconds(0);

        $this->travelTo($t1, fn () => $this->recorder()->referralSent($referral));
        $this->travelTo($t2, fn () => $this->recorder()->referralStatusChanged($referral, 'PENDING', 'COMPLETED'));
        $referral->update(['status' => 'COMPLETED']);

        $payload = $this->swimlane($case);
        $lane = $payload['referrals'][0];

        // The finished state is carried by the label text, not a staff boolean.
        $last = $lane['segments'][count($lane['segments']) - 1];
        $this->assertSame('Completed', $last['label']);
        $this->assertSame($t2->toISOString(), $last['end']);
        $this->assertSame('Completed', $lane['statusLabel']);

        // No convergence concept on the client payload.
        $this->assertArrayNotHasKey('caseManagerLane', $payload);
        $this->assertNull($payload['resolvedAt']);
        $this->assertSame(['referrals', 'active', 'milestones'], array_keys($payload['totals']));
    }

    public function test_rejected_referral_uses_unable_to_assist_label(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $this->recorder()->referralSent($referral);
        $this->recorder()->referralStatusChanged($referral, 'PENDING', 'REJECTED');
        $referral->update([
            'status' => 'REJECTED',
            'decision_comment' => 'Internal remark that must never leak',
            'rejection_reason' => 'OUTSIDE_MANDATE',
        ]);

        $lane = $this->swimlane($case)['referrals'][0];

        $this->assertSame('Unable to assist', $lane['statusLabel']);
        $last = $lane['segments'][count($lane['segments']) - 1];
        $this->assertSame('Unable to assist', $last['label']);
        $this->assertNotNull($last['end']);
    }

    public function test_resolved_at_set_on_case_close(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $t4 = now()->setMicroseconds(0);

        $this->recorder()->referralSent($referral);
        $this->recorder()->referralStatusChanged($referral, 'PENDING', 'COMPLETED');
        $referral->update(['status' => 'COMPLETED']);
        $this->travelTo($t4, fn () => $this->recorder()->caseClosed($case));
        $case->update(['status' => 'CLOSED', 'closed_at' => $t4]);

        $payload = $this->swimlane($case);

        $this->assertSame($t4->toISOString(), $payload['resolvedAt']);
        $this->assertArrayNotHasKey('caseClosedAt', $payload);
    }

    public function test_legacy_zero_event_referral_renders_with_no_codes(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $lane = $this->swimlane($case)['referrals'][0];

        $this->assertSame($referral->refresh()->created_at->toISOString(), $lane['sentAt']);
        $this->assertSame([], $lane['segments']);
        $this->assertSame('Awaiting receipt', $lane['statusLabel']);
        $this->assertTrue($lane['isCurrent']);
    }

    public function test_referrals_sort_ascending_by_sent_at(): void
    {
        $case = $this->makeCase();
        $first = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $second = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $t1 = now()->subDays(2)->setMicroseconds(0);
        $t2 = now()->subDay()->setMicroseconds(0);

        // Record out of order — sentAt order must win.
        $this->travelTo($t2, fn () => $this->recorder()->referralSent($second));
        $this->travelTo($t1, fn () => $this->recorder()->referralSent($first));

        $lanes = $this->swimlane($case)['referrals'];

        $this->assertCount(2, $lanes);
        $this->assertSame($t1->toISOString(), $lanes[0]['sentAt']);
        $this->assertSame($t2->toISOString(), $lanes[1]['sentAt']);
        $this->assertSame($first->agency->name, $lanes[0]['agency']);
        $this->assertSame($second->agency->name, $lanes[1]['agency']);
    }

    public function test_payload_contains_no_staff_keys_or_raw_codes(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        $this->recorder()->caseOpened($case);
        $this->recorder()->referralSent($referral);
        $this->recorder()->referralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $this->recorder()->milestoneAdded($referral, $milestone);
        $this->recorder()->referralStatusChanged($referral, 'PROCESSING', 'FOR_COMPLIANCE');
        $referral->update(['status' => 'FOR_COMPLIANCE']);

        $payload = $this->swimlane($case);

        $this->assertSame(
            ['caseOpenedAt', 'resolvedAt', 'generatedAt', 'referrals', 'totals'],
            array_keys($payload)
        );
        $this->assertSame(
            ['agency', 'service', 'sentAt', 'statusLabel', 'isCurrent', 'segments', 'milestones', 'milestoneCount'],
            array_keys($payload['referrals'][0])
        );

        $this->assertNoBannedContent($payload);
    }

    public function test_client_update_mail_renders_lane_strip_without_codes(): void
    {
        $case = $this->makeCase();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $this->recorder()->referralSent($referral);
        $this->recorder()->referralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $referral->update(['status' => 'PROCESSING']);

        $html = (new ClientUpdateMail($case->fresh(), 'Your referral is now being processed.', 'system'))->render();

        $this->assertStringContainsString($referral->refresh()->agency->name, $html);
        $this->assertStringContainsString('In process', $html);

        foreach (self::BANNED_VALUES as $code) {
            $this->assertStringNotContainsString($code, $html, "Raw code {$code} leaked into client email HTML");
        }
    }

    private function assertNoBannedContent(mixed $value, string $path = '$'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $this->assertNotContains(
                        $key,
                        self::BANNED_KEYS,
                        "Banned staff key '{$key}' present at {$path}"
                    );
                }
                $this->assertNoBannedContent($item, $path.'.'.$key);
            }

            return;
        }

        if (is_string($value)) {
            $this->assertNotContains(
                $value,
                self::BANNED_VALUES,
                "Raw internal code '{$value}' present at {$path}"
            );
        }
    }
}
