<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use App\Models\ReferralServiceRequirement;
use App\Models\Service as AgencyService;
use App\Services\ReferralClientRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClientRequestDocumentGovernanceTest extends ReferralClientInboxTestCase
{
    public function test_duplicate_requirements_are_deduplicated_and_keep_all_provenance(): void
    {
        $context = $this->context();
        $service = AgencyService::create(['name' => 'Support', 'description' => 'Support', 'processing_days' => 5, 'agcy_id' => $context['agency']->id]);
        $first = ReferralServiceRequirement::create([
            'referral_id' => $context['referral']->id,
            'service_id' => $service->id,
            'name' => 'Valid ID',
            'description' => 'Government-issued ID',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $second = ReferralServiceRequirement::create([
            'referral_id' => $context['referral']->id,
            'service_id' => $service->id,
            'name' => 'VALID-ID',
            'description' => 'Any valid ID',
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $request = app(ReferralClientRequestService::class)->createRequest($context['referral'], $context['agencyUser'], [
            'type' => ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
            'title' => 'Documents',
            'instructions' => 'Upload your documents.',
            'items' => [[
                'label' => $first->name,
                'description' => $first->description,
                'source_requirement_id' => $first->id,
                'source_requirement_ids' => [$first->id, $second->id],
            ], [
                'label' => $second->name,
                'source_requirement_id' => $second->id,
            ]],
        ]);

        $item = $request->items->first();
        $this->assertCount(1, $request->items);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $item->sourceRequirements()->pluck('id')->all());
    }

    public function test_request_item_upload_replaces_previous_active_file(): void
    {
        Storage::fake('object-storage');
        $context = $this->context();
        $item = ReferralClientRequestItem::factory()->create(['request_id' => $context['clientRequest']->id]);
        $accessLink = $this->issue($context)['link'];
        $service = app(ReferralClientRequestService::class);

        $service->sendClientMessage(
            $context['clientRequest'],
            '',
            $accessLink,
            [UploadedFile::fake()->createWithContent('old.pdf', '%PDF-1.4 old')],
            [$item->id],
        );
        $old = $item->attachments()->first();

        $service->sendClientMessage(
            $context['clientRequest'],
            '',
            $accessLink->fresh(),
            [UploadedFile::fake()->createWithContent('new.pdf', '%PDF-1.4 new')],
            [$item->id],
        );
        $new = $item->attachments()->latest()->first();

        $this->assertTrue($old->fresh()->is_deleted);
        $this->assertFalse($new->fresh()->is_deleted);
    }
}
