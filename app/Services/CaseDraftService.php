<?php

namespace App\Services;

use App\DTOs\CaseDraftPayload;
use App\Models\AuditLog;
use App\Models\CaseDraft;
use App\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CaseDraftService
{
    public function __construct(private readonly CaseDraftPublisher $publisher) {}

    public function create(array $payload, string $ownerId): CaseDraft
    {
        $document = CaseDraftPayload::fromArray($payload);
        $this->validateSourceClient($document);

        return DB::transaction(function () use ($document, $ownerId) {
            $draft = CaseDraft::create([
                'owner_id' => $ownerId,
                'source_client_id' => $document->sourceClientId(),
                'payload_encrypted' => $document->toArray(),
                'payload_schema_version' => CaseDraftPayload::SCHEMA_VERSION,
                'revision' => 1,
                'state' => CaseDraft::STATE_EDITING,
            ]);

            $this->audit('CREATE', $draft, ['state' => CaseDraft::STATE_EDITING, 'revision' => 1], $ownerId);

            return $draft;
        });
    }

    public function save(CaseDraft|string $draft, array $payload, string $ownerId, int $expectedRevision): CaseDraft
    {
        $document = CaseDraftPayload::fromArray($payload);

        return DB::transaction(function () use ($draft, $document, $ownerId, $expectedRevision) {
            $draft = $this->locked($draft);
            $this->authorizeOwner($draft, $ownerId);
            $this->assertEditable($draft);
            $this->assertRevision($draft, $expectedRevision);
            $this->validateSourceClient($document);

            $draft->update([
                'source_client_id' => $document->sourceClientId(),
                'payload_encrypted' => $document->toArray(),
                'payload_schema_version' => CaseDraftPayload::SCHEMA_VERSION,
                'revision' => $draft->revision + 1,
            ]);

            return $draft->refresh();
        });
    }

    public function discard(CaseDraft|string $draft, string $ownerId, int $expectedRevision): CaseDraft
    {
        return DB::transaction(function () use ($draft, $ownerId, $expectedRevision) {
            $draft = $this->locked($draft);
            $this->authorizeOwner($draft, $ownerId);
            $this->assertEditable($draft);
            $this->assertRevision($draft, $expectedRevision);

            $draft->update([
                'state' => CaseDraft::STATE_DISCARDED,
                'payload_encrypted' => null,
                'discarded_at' => now(),
            ]);
            $this->audit('DELETE', $draft, ['state' => CaseDraft::STATE_DISCARDED], $ownerId);

            return $draft->refresh();
        });
    }

    public function publish(CaseDraft|string $draft, string $ownerId, int $expectedRevision): mixed
    {
        return $this->publisher->publish($draft, $ownerId, $expectedRevision);
    }

    public function expireStaleDrafts(int $days = 90): int
    {
        $cutoff = now()->subDays($days);
        $expired = 0;

        CaseDraft::where('state', CaseDraft::STATE_EDITING)
            ->where('updated_at', '<=', $cutoff)
            ->chunkById(100, function ($drafts) use ($cutoff, &$expired) {
                foreach ($drafts as $candidate) {
                    DB::transaction(function () use ($candidate, $cutoff, &$expired) {
                        $draft = CaseDraft::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $draft || ! $draft->isEditing() || $draft->updated_at->gt($cutoff)) {
                            return;
                        }

                        $draft->update([
                            'state' => CaseDraft::STATE_DISCARDED,
                            'payload_encrypted' => null,
                            'discarded_at' => now(),
                        ]);
                        $this->audit('DELETE', $draft, [
                            'state' => CaseDraft::STATE_DISCARDED,
                            'reason' => 'expired',
                        ], null);
                        $expired++;
                    });
                }
            });

        return $expired;
    }

    public function response(CaseDraft $draft, mixed $publishedCase = null): array
    {
        return [
            'id' => $draft->id,
            'revision' => $draft->revision,
            'saved_at' => $draft->updated_at?->toIso8601String(),
            'state' => $draft->state,
            'published_case' => $publishedCase,
        ];
    }

    private function locked(CaseDraft|string $draft): CaseDraft
    {
        $id = $draft instanceof CaseDraft ? $draft->id : $draft;

        return CaseDraft::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function validateSourceClient(CaseDraftPayload $payload): void
    {
        if ($payload->clientSource() !== 'EXISTING') {
            return;
        }

        $client = Client::whereKey($payload->sourceClientId())->where('is_deleted', false)->first();
        if (! $client) {
            throw ValidationException::withMessages(['source_client_id' => 'The source client is not active.']);
        }

        if ($payload->selectedNokId() !== null && ! $client->nextOfKin()->whereKey($payload->selectedNokId())->where('is_deleted', false)->exists()) {
            throw ValidationException::withMessages(['selected_nok_id' => 'The selected next of kin does not belong to the source client.']);
        }
    }

    private function authorizeOwner(CaseDraft $draft, string $ownerId): void
    {
        if ((string) $draft->owner_id !== (string) $ownerId) {
            throw new AuthorizationException('You do not own this draft.');
        }
    }

    private function assertRevision(CaseDraft $draft, int $expectedRevision): void
    {
        if ($draft->revision !== $expectedRevision) {
            throw new HttpException(409, 'Draft revision is stale.');
        }
    }

    private function assertEditable(CaseDraft $draft): void
    {
        if (! $draft->isEditing()) {
            throw new HttpException(410, 'This draft is no longer editable.');
        }
    }

    private function audit(string $action, CaseDraft $draft, array $metadata, ?string $userId = null): void
    {
        AuditLog::create([
            'action' => $action,
            'module' => 'case_draft',
            'entity_id' => $draft->id,
            'description' => 'Case draft '.$action.' command completed.',
            'old_value' => null,
            'new_value' => $metadata,
            'user_id' => $userId,
            'timestamp' => now(),
        ]);
    }
}
