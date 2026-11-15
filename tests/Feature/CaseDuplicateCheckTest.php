<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseDuplicateCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaseDuplicateCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_refreshes_second_row_using_confirmed_concerns_then_allows_retry(): void
    {
        Mail::fake();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'created_at' => now()->subDay(), 'first_name' => 'Unrelated']);
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'return_reason' => 'OTHERS', 'proposed_assessments' => ['DISTRESSED_OFW']]);
        $open = $this->record();
        $documents = [];
        foreach (['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'] as $slot) {
            $documents[] = CaseDocument::factory()->create(['case_id' => $pending->id, 'category' => $slot, 'referral_id' => null])->id;
        }
        $payload = ['confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'reviewed_document_ids' => $documents,
            'category_ids' => [CaseCategory::factory()->create()->id]];
        $url = route('cases.review-pending');
        $this->actingAsWithMfa($manager)->from($url)->post(route('cases.review-approve', $pending), $payload)
            ->assertRedirect($url)->assertSessionHasErrors('duplicate_check');
        $this->assertSame(CaseFile::STATUS_PENDING_REVIEW, $pending->fresh()->status);
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('cases.data.0.duplicate_check.matches', [])
            ->where('cases.data.1.id', $pending->id)
            ->where('cases.data.1.duplicate_check.matches.0.id', $open->id));
        $payload['duplicate_seen_ids'] = [$open->id];
        $this->post(route('cases.review-approve', $pending), $payload)->assertRedirect(route('cases.index'));
        $this->assertSame('OPEN', $pending->fresh()->status);
    }

    public function test_failed_check_is_visible_and_does_not_block_approval(): void
    {
        Mail::fake();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'return_reason' => 'OTHERS']);
        $this->mock(CaseDuplicateCheckService::class)->shouldReceive('forCase')->andReturn(['available' => false, 'matches' => []]);
        $this->actingAsWithMfa($manager)->get(route('cases.review-pending'))->assertInertia(fn ($page) => $page
            ->where('cases.data.0.duplicate_check.available', false));
        $documents = [];
        foreach (['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'] as $slot) {
            $documents[] = CaseDocument::factory()->create(['case_id' => $pending->id, 'category' => $slot, 'referral_id' => null])->id;
        }
        $this->post(route('cases.review-approve', $pending), [
            'confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'reviewed_document_ids' => $documents,
            'category_ids' => [CaseCategory::factory()->create()->id],
        ])->assertRedirect(route('cases.index'))->assertSessionHas('warning');
        $this->assertSame('OPEN', $pending->fresh()->status);
    }

    public function test_shared_account_without_shared_named_concern_is_not_duplicate(): void
    {
        $owner = User::factory()->create(['role' => 'OFW']);
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'ofw_user_id' => $owner->id]);
        $candidate = $this->record(['ofw_user_id' => $owner->id, 'confirmed_assessments' => ['DISTRESSED_OFW'], 'proposed_assessments' => ['LEGAL_ASSISTANCE']]);
        $service = app(CaseDuplicateCheckService::class);
        $this->assertSame([], $service->forCase($pending, ['LEGAL_ASSISTANCE', 'OTHER'])['matches']);
        $candidate->update(['confirmed_assessments' => []]);
        $this->assertSame([$candidate->id], array_column($service->forCase($pending, ['LEGAL_ASSISTANCE'])['matches'], 'id'));
    }

    public function test_corrupt_birth_date_reports_unavailable_instead_of_throwing(): void
    {
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW]);
        DB::table('cases')->where('id', $pending->id)->update(['date_of_birth' => 'invalid-ciphertext']);
        $this->assertFalse(app(CaseDuplicateCheckService::class)->forCase($pending->fresh(), ['LEGAL_ASSISTANCE'])['available']);
    }

    private function record(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->withIntake()->create(array_merge([
            'client_type' => 'OFW', 'first_name' => 'Ana', 'middle_name' => 'Maria',
            'last_name' => 'Santos', 'suffix' => null, 'date_of_birth' => '1990-01-02',
            'confirmed_assessments' => ['LEGAL_ASSISTANCE'], 'proposed_assessments' => ['LEGAL_ASSISTANCE'],
        ], $attributes));
    }

    public function test_matches_open_ofw_account_and_name_identity_only_with_shared_named_concern(): void
    {
        $owner = User::factory()->create();
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'ofw_user_id' => $owner->id]);
        $open = $this->record(['ofw_user_id' => $owner->id, 'first_name' => " \tANA ", 'middle_name' => 'Maria', 'last_name' => 'Santos']);

        $result = app(CaseDuplicateCheckService::class)->forCase($pending, ['LEGAL_ASSISTANCE']);
        $this->assertTrue($result['available']);
        $this->assertSame([$open->id], array_column($result['matches'], 'id'));
        $this->assertSame(['same_ofw_account', 'same_name_and_birth_date'], $result['matches'][0]['reasons']);
        $this->assertSame(['LEGAL_ASSISTANCE'], $result['matches'][0]['shared_assessments']);
    }

    public function test_requires_identity_or_same_ofw_account_and_shared_concerns(): void
    {
        $owner = User::factory()->create();
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'ofw_user_id' => $owner->id, 'suffix' => 'Sr']);
        $this->record(['ofw_user_id' => $owner->id, 'client_type' => 'NEXT_OF_KIN']);
        $this->record(['first_name' => 'Different']);
        $this->record(['suffix' => 'Jr']);
        $this->record(['middle_name' => null]);
        $this->record(['confirmed_assessments' => null, 'proposed_assessments' => ['DISTRESSED_OFW']]);
        $this->record(['status' => 'CLOSED']);
        $this->record(['status' => 'OPEN', 'is_deleted' => true]);

        $this->assertSame([], app(CaseDuplicateCheckService::class)->forCase($pending, ['OTHER'])['matches']);
        $this->assertSame([], app(CaseDuplicateCheckService::class)->forCase($pending, ['LEGAL_ASSISTANCE'])['matches']);
    }

    public function test_identity_normalization_preserves_optional_parts_and_birth_date(): void
    {
        $pending = $this->record(['status' => CaseFile::STATUS_PENDING_REVIEW, 'first_name' => "Ana\t", 'last_name' => 'Santos']);
        $match = $this->record(['first_name' => "  ANA\t", 'last_name' => ' Santos ']);
        $this->record(['date_of_birth' => '1991-01-02']);

        $result = app(CaseDuplicateCheckService::class)->forCase($pending, ['LEGAL_ASSISTANCE']);
        $this->assertSame([$match->id], array_column($result['matches'], 'id'));
        $this->assertSame('same_name_and_birth_date', $result['matches'][0]['reasons'][0]);
        $this->assertNull($result['matches'][0]['opened_at']);
    }
}
