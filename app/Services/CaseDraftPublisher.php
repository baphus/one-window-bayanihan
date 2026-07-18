<?php

namespace App\Services;

use App\DTOs\CaseDraftPayload;
use App\Events\CaseDraftPublished;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDraft;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CaseDraftPublisher
{
    public function __construct(
        private readonly CaseEventRecorder $eventRecorder,
        private readonly PhilippineAddressService $addressService,
        private readonly ?CaseDraftIdentifierGeneratorContract $identifierGenerator = null,
    ) {}

    public function publish(CaseDraft|string $draft, string $ownerId, int $expectedRevision): CaseFile
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->publishAttempt($draft, $ownerId, $expectedRevision);
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '23505' || $attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to publish draft after bounded identifier retries.');
    }

    private function publishAttempt(CaseDraft|string $draft, string $ownerId, int $expectedRevision): CaseFile
    {
        return DB::transaction(function () use ($draft, $ownerId, $expectedRevision) {
            $draft = CaseDraft::whereKey($draft instanceof CaseDraft ? $draft->id : $draft)->lockForUpdate()->firstOrFail();
            if ((string) $draft->owner_id !== (string) $ownerId) {
                throw new AuthorizationException('You do not own this draft.');
            }
            if ($draft->isPublished()) {
                return $draft->publishedCase()->firstOrFail();
            }
            if (! $draft->isEditing()) {
                throw new HttpException(410, 'This draft is no longer publishable.');
            }
            if ($draft->revision !== $expectedRevision) {
                throw new HttpException(409, 'Draft revision is stale.');
            }

            $payload = CaseDraftPayload::fromArray($draft->payload_encrypted ?? []);
            $this->lockReferences($payload);
            $payload->validateForPublish();

            $client = $payload->clientSource() === 'EXISTING'
                ? Client::whereKey($payload->sourceClientId())->lockForUpdate()->firstOrFail()
                : $this->createClient($payload);

            $selectedNokId = $payload->clientSource() === 'NEW'
                ? ($this->materializeChildren($client, $payload)['selectedNokId'] ?? null)
                : $payload->selectedNokId();

            [$caseNumber, $trackerNumber] = ($this->identifierGenerator ?? new CaseDraftIdentifierGenerator)->generate();
            $case = CaseFile::create([
                'case_number' => $caseNumber,
                'tracker_number' => $trackerNumber,
                'client_type' => $payload->toArray()['client_type'] ?? 'OFW',
                'vulnerability_indicator' => $payload->toArray()['vulnerability_indicator'] ?? null,
                'nok_vulnerability_indicator' => $payload->toArray()['nok_vulnerability_indicator'] ?? null,
                'summary' => $payload->toArray()['summary'] ?? null,
                'status' => 'OPEN',
                'consent_given_at' => $payload->consentAcceptedAt() ? now()->parse($payload->consentAcceptedAt()) : null,
                'user_id' => $ownerId,
                'client_id' => $client->id,
                'category_id' => $payload->categoryIds()[0] ?? null,
                'case_issue_id' => $payload->toArray()['case_issue_id'] ?? null,
            ]);
            $case->consent_notice_version = $payload->consentNoticeVersion();
            $case->selected_nok_id = $selectedNokId;
            $case->selected_nok_evidence = $selectedNokId
                ? ['client_id' => $client->id, 'nok_id' => $selectedNokId, 'source' => $payload->clientSource()]
                : null;
            $case->save();
            $case->categories()->sync($payload->categoryIds());
            $this->eventRecorder->caseOpened($case, $ownerId);

            $draft->state = CaseDraft::STATE_PUBLISHED;
            $draft->published_case_id = $case->id;
            $draft->published_at = now();
            $draft->payload_encrypted = null;
            $draft->selected_nok_id = $selectedNokId;
            $draft->selected_nok_evidence = $selectedNokId ? ['case_id' => $case->id, 'nok_id' => $selectedNokId] : null;
            $draft->consent_notice_version = $payload->consentNoticeVersion();
            $draft->consent_accepted_at = $payload->consentAcceptedAt();
            $draft->save();
            AuditLog::create([
                'action' => 'PUBLISH', 'module' => 'case_draft', 'entity_id' => $draft->id,
                'description' => 'Case draft published.', 'old_value' => ['state' => CaseDraft::STATE_EDITING],
                'new_value' => ['state' => CaseDraft::STATE_PUBLISHED, 'published_case_id' => $case->id],
                'user_id' => $ownerId, 'timestamp' => now(),
            ]);

            $recipientEmail = app(CaseRecipientResolver::class)->resolve($case);
            event(new CaseDraftPublished($case->id, $recipientEmail, 'case-draft:'.$case->id.':published'));

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'categories', 'caseIssue']);
        });
    }

    private function lockReferences(CaseDraftPayload $payload): void
    {
        $this->lockCategories($payload->categoryIds());
        $issue = null;
        if ($payload->caseIssueId()) {
            $issue = CaseIssue::whereKey($payload->caseIssueId())
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->first();
            if (! $issue) {
                throw ValidationException::withMessages(['case_issue_id' => 'The selected case issue is not active.']);
            }
        }

        if ($payload->clientSource() === 'EXISTING') {
            $client = Client::whereKey($payload->sourceClientId())
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->first();
            if (! $client) {
                throw ValidationException::withMessages(['source_client_id' => 'The source client is not active.']);
            }
            if ($payload->selectedNokId()) {
                $selectedNok = $client->nextOfKin()
                    ->whereKey($payload->selectedNokId())
                    ->where('is_deleted', false)
                    ->lockForUpdate()
                    ->first();
                if (! $selectedNok) {
                    throw ValidationException::withMessages(['selected_nok_id' => 'The selected next of kin does not belong to the source client.']);
                }
            }
        }
    }

    private function lockCategories(array $ids): Collection
    {
        $categories = CaseCategory::whereIn('id', $ids)->where('is_active', true)->where('is_deleted', false)->lockForUpdate()->get();
        if ($categories->count() !== count($ids)) {
            throw ValidationException::withMessages(['category_ids' => 'All selected categories must be active.']);
        }

        return $categories;
    }

    private function createClient(CaseDraftPayload $payload): Client
    {
        $data = $payload->toArray()['client'] ?? [];

        return Client::create([
            'first_name' => $data['first_name'] ?? '', 'last_name' => $data['last_name'] ?? '',
            'middle_initial' => $data['middle_initial'] ?? null, 'suffix' => $data['suffix'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null, 'sex' => isset($data['sex']) ? strtoupper($data['sex']) : null,
            'email' => $data['email'] ?? null, 'contact_number' => $data['contact_number'] ?? null,
        ]);
    }

    private function materializeChildren(Client $client, CaseDraftPayload $payload): array
    {
        $data = $payload->toArray();
        if (! empty($data['address'])) {
            ClientAddress::create(array_merge(['client_id' => $client->id], $this->resolveAddress($data['address'])));
        }
        if (! empty($data['employment'])) {
            ClientEmployment::create(array_merge(['client_id' => $client->id], $data['employment']));
        }
        $selectedNokId = null;
        foreach ($payload->nextOfKin() as $nokData) {
            $nok = NextOfKin::create(array_merge(['client_id' => $client->id], $nokData));
            if ($payload->selectedNokId() === ($nokData['id'] ?? null)) {
                $selectedNokId = $nok->id;
            }
        }

        return ['selectedNokId' => $selectedNokId];
    }

    private function resolveAddress(array $address): array
    {
        $codes = array_filter([
            $address['region'] ?? null,
            $address['province'] ?? null,
            $address['city_municipality'] ?? null,
            $address['barangay'] ?? null,
        ]);
        $names = $this->addressService->resolveNames($codes);

        foreach (['region', 'province', 'city_municipality', 'barangay'] as $key) {
            if (array_key_exists($key, $address)) {
                $address[$key] = $names[$address[$key]] ?? $address[$key];
            }
        }

        return $address;
    }
}
