<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDraft;
use App\Models\User;
use App\Services\CaseDraftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaseDraftExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_honors_the_supplied_day_cutoff(): void
    {
        $owner = User::factory()->create();
        $expired = $this->createDraft($owner, now()->subDays(30));
        $active = $this->createDraft($owner, now()->subDays(29));

        $this->assertSame(1, app(CaseDraftService::class)->expireStaleDrafts(30));
        $this->assertSame(CaseDraft::STATE_DISCARDED, $expired->refresh()->state);
        $this->assertSame(CaseDraft::STATE_EDITING, $active->refresh()->state);
    }

    public function test_expiry_processes_a_large_stale_set_in_bounded_chunks(): void
    {
        $owner = User::factory()->create();
        $staleIds = [];

        for ($index = 0; $index < 205; $index++) {
            $staleIds[] = $this->createDraft($owner, now()->subDays(90))->id;
        }

        $expired = app(CaseDraftService::class)->expireStaleDrafts(90);

        $this->assertSame(205, $expired);
        $this->assertSame(205, CaseDraft::whereIn('id', $staleIds)->where('state', CaseDraft::STATE_DISCARDED)->count());
    }

    public function test_expiry_audit_is_attributed_to_the_system(): void
    {
        $owner = User::factory()->create();
        $draft = $this->createDraft($owner, now()->subDays(90));

        app(CaseDraftService::class)->expireStaleDrafts(90);

        $audit = AuditLog::where('entity_id', $draft->id)
            ->where('action', 'DELETE')
            ->firstOrFail();

        $this->assertNull($audit->user_id);
        $this->assertSame('expired', $audit->new_value['reason']);
    }

    public function test_terminal_draft_wins_over_stale_revision_for_save_and_discard(): void
    {
        $owner = User::factory()->create();
        $draft = $this->createDraft($owner);
        $service = app(CaseDraftService::class);
        $service->discard($draft, $owner->id, 1);

        try {
            $service->save($draft->id, $this->payload(), $owner->id, 999);
            $this->fail('Expected terminal save rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }

        try {
            $service->discard($draft->id, $owner->id, 999);
            $this->fail('Expected terminal discard rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
    }

    public function test_expiry_command_reports_and_expires_stale_drafts(): void
    {
        $owner = User::factory()->create();
        $draft = $this->createDraft($owner, now()->subDays(90));

        $this->artisan('case-drafts:expire-stale')
            ->expectsOutput('Expired 1 stale case drafts.')
            ->assertExitCode(0);

        $this->assertSame(CaseDraft::STATE_DISCARDED, $draft->refresh()->state);
    }

    public function test_expiry_then_save_is_a_deterministic_terminal_race(): void
    {
        $owner = User::factory()->create();
        $draft = $this->createDraft($owner, now()->subDays(90));
        $service = app(CaseDraftService::class);

        $this->assertSame(1, $service->expireStaleDrafts(90));

        try {
            $service->save($draft->id, $this->payload(), $owner->id, 1);
            $this->fail('Expected terminal save rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
    }

    public function test_expiry_then_publish_is_a_deterministic_terminal_race(): void
    {
        $owner = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $draft = app(CaseDraftService::class)->create(
            array_merge($this->payload(), ['category_ids' => [$category->id]]),
            $owner->id
        );
        DB::table('case_drafts')->where('id', $draft->id)->update(['updated_at' => now()->subDays(90)]);

        $service = app(CaseDraftService::class);
        $this->assertSame(1, $service->expireStaleDrafts(90));

        try {
            $service->publish($draft->id, $owner->id, 1);
            $this->fail('Expected terminal publish rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
    }

    private function createDraft(User $owner, ?Carbon $updatedAt = null): CaseDraft
    {
        $draft = CaseDraft::create([
            'owner_id' => $owner->id,
            'payload_encrypted' => $this->payload(),
            'payload_schema_version' => 1,
            'revision' => 1,
            'state' => CaseDraft::STATE_EDITING,
        ]);

        if ($updatedAt !== null) {
            DB::table('case_drafts')->where('id', $draft->id)->update(['updated_at' => $updatedAt]);
        }

        return $draft->refresh();
    }

    private function payload(): array
    {
        return [
            'schema_version' => 1,
            'client_source' => 'NEW',
            'client' => [
                'first_name' => 'New',
                'last_name' => 'Client',
                'date_of_birth' => '1990-01-01',
                'sex' => 'MALE',
                'contact_number' => '09170000000',
                'email' => 'new@example.test',
            ],
            'client_type' => 'OFW',
            'consent' => ['accepted_at' => now()->toIso8601String(), 'notice_version' => 'v1'],
        ];
    }
}
