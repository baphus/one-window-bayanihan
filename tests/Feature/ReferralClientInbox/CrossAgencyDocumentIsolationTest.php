<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Services\ReferralClientRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class CrossAgencyDocumentIsolationTest extends ReferralClientInboxTestCase
{
    public function test_other_agency_cannot_create_or_manage_request(): void
    {
        $context = $this->context();
        $service = app(ReferralClientRequestService::class);

        $this->expectException(AuthorizationException::class);
        $service->sendAgencyMessage($context['clientRequest'], $context['otherAgencyUser'], 'Cross-agency attempt');
    }

    public function test_existing_document_endpoint_remains_case_scoped(): void
    {
        $context = $this->context();
        $document = CaseDocument::create([
            'id' => Str::uuid()->toString(),
            'file_name' => 'private.pdf',
            'file_path' => 'private/private.pdf',
            'file_type' => 'application/pdf',
            'case_id' => $context['case']->id,
            'user_id' => $context['manager']->id,
        ]);
        $otherCase = CaseFile::factory()->create();

        $this->actingAs($context['otherAgencyUser'])
            ->getJson(route('cases.documents.show', [$otherCase->id, $document->id]))
            ->assertForbidden();
    }
}
