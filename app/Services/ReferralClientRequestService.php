<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Coordinates the agency/client request inbox without issuing access links.
 */
class ReferralClientRequestService
{
    public function __construct(
        private readonly CaseEventRecorder $eventRecorder,
        private readonly StorageService $storage,
    ) {}

    /** Create a request and its optional document checklist/milestone. */
    public function createRequest(Referral $referral, User $actor, array $data): ReferralClientRequest
    {
        $this->assertCanManageReferral($referral, $actor);
        $type = $data['type'] ?? null;
        $items = array_values(array_filter($data['items'] ?? $data['checklist'] ?? [], static fn ($item) => filled(is_array($item) ? ($item['label'] ?? null) : $item)));

        if (! in_array($type, $this->requestTypes(), true)) {
            throw new InvalidArgumentException('Unsupported client request type.');
        }

        if ($type === ReferralClientRequest::TYPE_DOCUMENT_REQUEST && $items === []) {
            throw new InvalidArgumentException('Document requests require at least one checklist item.');
        }

        $request = DB::transaction(function () use ($referral, $actor, $data, $type, $items) {
            $request = ReferralClientRequest::create([
                'referral_id' => $referral->id,
                'creator_user_id' => $actor->id,
                'type' => $type,
                'title' => $data['title'],
                'instructions' => $data['instructions'],
                'status' => ReferralClientRequest::STATUS_OPEN,
                'due_at' => $data['due_at'] ?? null,
            ]);

            foreach ($items as $position => $item) {
                $request->items()->create([
                    'label' => is_array($item) ? $item['label'] : $item,
                    'sort_order' => is_array($item) ? ($item['sort_order'] ?? $position) : $position,
                ]);
            }

            $milestone = Milestone::create([
                'title' => $data['title'],
                'description' => 'Client request created.',
                'requirements' => $items === [] ? null : array_map(
                    static fn ($item) => is_array($item) ? $item['label'] : $item,
                    $items,
                ),
                'refr_id' => $referral->id,
                'client_request_id' => $request->id,
                'user_id' => $actor->id,
            ]);

            $this->eventRecorder->milestoneAdded($referral, $milestone, $actor->id);

            return $request;
        });

        $this->invalidateTrackingCaches($referral);

        return $this->loadRequest($request);
    }

    /** Add a message from an authorized agency user. */
    public function sendAgencyMessage(ReferralClientRequest $request, User $actor, string $body): ReferralClientMessage
    {
        $this->assertCanManageRequest($request, $actor);
        $this->assertClientFacingWriteAllowed($request->referral);
        $this->assertBody($body);

        $message = DB::transaction(fn () => ReferralClientMessage::create([
            'request_id' => $request->id,
            'body' => $body,
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => $actor->id,
            'kind' => ReferralClientMessage::KIND_MESSAGE,
        ]));

        $this->invalidateTrackingCaches($request->referral);

        return $message->load(['user', 'request']);
    }

    /** Add a client message; link token validation belongs to the access service. */
    public function sendClientMessage(ReferralClientRequest $request, string $body, ReferralClientAccessLink $accessLink, array $files = []): ReferralClientMessage
    {
        if (trim($body) === '' && $files === []) {
            throw new InvalidArgumentException('A message or at least one attachment is required.');
        }

        // Validate every file up front so a bad file fails the whole reply
        // before any storage or row writes happen.
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw new InvalidArgumentException('Invalid attachment supplied.');
            }
            $errors = $this->storage->validate($file, 'client_request_document');
            if (! empty($errors)) {
                throw new InvalidArgumentException($errors[0]);
            }
        }

        [$message, $referral] = DB::transaction(function () use ($request, $body, $accessLink, $files) {
            $lockedRequest = ReferralClientRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->id);
            $referral = Referral::query()->lockForUpdate()->findOrFail($lockedRequest->referral_id);
            $case = $referral->caseFile()->lockForUpdate()->first();
            $referral->setRelation('caseFile', $case);
            $lockedLink = ReferralClientAccessLink::query()->lockForUpdate()->findOrFail($accessLink->id);

            if ($lockedLink->request_id !== $lockedRequest->id) {
                throw new AuthorizationException('This access link does not belong to the request.');
            }
            $this->assertClientFacingWriteAllowed($referral);
            $this->assertOpen($lockedRequest);
            if ($lockedLink->revoked_at !== null || ! $lockedLink->expires_at?->isFuture()) {
                throw new LogicException('This access link is no longer usable.');
            }

            $message = ReferralClientMessage::create([
                'request_id' => $lockedRequest->id,
                'body' => $body,
                'sender_kind' => ReferralClientMessage::SENDER_CLIENT_ACCESS,
                'access_link_id' => $lockedLink->id,
                'kind' => ReferralClientMessage::KIND_MESSAGE,
            ]);

            foreach ($files as $file) {
                $result = $this->storage->store($file, 'client-request-attachments/'.$lockedRequest->id);
                if (! $result->success) {
                    throw new InvalidArgumentException($result->error ?? 'Failed to store attachment.');
                }

                $message->attachments()->create([
                    'file_name' => $result->originalName,
                    'file_path' => $result->path,
                    'file_type' => $result->type,
                    'size' => $result->size,
                ]);
            }

            $lockedRequest->update(['status' => ReferralClientRequest::STATUS_CLIENT_RESPONDED]);

            return [$message, $referral];
        });

        $this->invalidateTrackingCaches($referral);

        return $message->load(['accessLink', 'request', 'attachments']);
    }

    /** Whether the client may still submit a message for this request. */
    public function canClientSubmit(ReferralClientRequest $request): bool
    {
        $request->loadMissing('referral.caseFile');

        try {
            $this->assertClientFacingWriteAllowed($request->referral);
            $this->assertOpen($request);

            return true;
        } catch (LogicException) {
            return false;
        }
    }

    /** Complete an active request. */
    public function complete(ReferralClientRequest $request, User $actor): ReferralClientRequest
    {
        return $this->transition($request, $actor, ReferralClientRequest::STATUS_COMPLETED);
    }

    /** Cancel an active request. */
    public function cancel(ReferralClientRequest $request, User $actor): ReferralClientRequest
    {
        return $this->transition($request, $actor, ReferralClientRequest::STATUS_CANCELLED);
    }

    /** Reopen a terminal request without issuing or changing an access link. */
    public function reopen(ReferralClientRequest $request, User $actor): ReferralClientRequest
    {
        return $this->transition($request, $actor, ReferralClientRequest::STATUS_OPEN, true);
    }

    /** Revoke an access link for an authorized agency, case manager, or admin. */
    public function revokeAccessLink(ReferralClientAccessLink $accessLink, User $actor): ReferralClientAccessLink
    {
        $accessLink->loadMissing('request.referral.caseFile');
        $referral = $accessLink->request?->referral;

        if (! $referral) {
            throw new AuthorizationException('The access link is not attached to a referral.');
        }

        $this->assertCanRevoke($referral, $actor);

        $referral = DB::transaction(function () use ($accessLink, $actor) {
            $lockedLink = ReferralClientAccessLink::query()->lockForUpdate()->findOrFail($accessLink->id);
            $lockedLink->load('request.referral.caseFile');
            $referral = $lockedLink->request?->referral;
            if (! $referral) {
                throw new AuthorizationException('The access link is not attached to a referral.');
            }
            $this->assertCanRevoke($referral, $actor);
            if ($lockedLink->revoked_at === null) {
                $lockedLink->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);
            }

            return $referral;
        });

        $this->invalidateTrackingCaches($referral);

        return $accessLink->fresh(['request', 'revoker']);
    }

    /** Ensure the supplied user can read this referral's request inbox. */
    public function assertCanRead(ReferralClientRequest|Referral $subject, User $actor): void
    {
        $referral = $subject instanceof ReferralClientRequest ? $subject->loadMissing('referral')->referral : $subject;
        if (! $referral || ! $this->isAuthorizedReader($referral, $actor)) {
            throw new AuthorizationException('You may not view this referral request.');
        }
    }

    /** Ensure the supplied user is the active receiving agency user. */
    public function assertCanManage(ReferralClientRequest|Referral $subject, User $actor): void
    {
        $referral = $subject instanceof ReferralClientRequest ? $subject->loadMissing('referral')->referral : $subject;
        $this->assertCanManageReferral($referral, $actor);
    }

    private function transition(ReferralClientRequest $request, User $actor, string $status, bool $reopen = false): ReferralClientRequest
    {
        $lockedRequest = DB::transaction(function () use ($request, $actor, $status, $reopen) {
            $lockedRequest = ReferralClientRequest::query()->lockForUpdate()->findOrFail($request->id);
            $referral = Referral::query()->lockForUpdate()->findOrFail($lockedRequest->referral_id);
            $case = $referral->caseFile()->lockForUpdate()->first();
            $referral->setRelation('caseFile', $case);
            $lockedRequest->setRelation('referral', $referral);

            $this->assertCanManageReferral($referral, $actor);
            if ($reopen) {
                if (! in_array($lockedRequest->status, [ReferralClientRequest::STATUS_COMPLETED, ReferralClientRequest::STATUS_CANCELLED], true)) {
                    throw new LogicException('Only completed or cancelled requests can be reopened.');
                }
            } else {
                $this->assertOpen($lockedRequest);
            }

            $lockedRequest->update(['status' => $status]);
            if ($reopen) {
                // A legacy/current link must never become usable merely because the request reopened.
                $lockedRequest->accessLinks()
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);
            } elseif ($status === ReferralClientRequest::STATUS_COMPLETED) {
                // The client keeps view access until the due date so they can
                // verify their submission; extend the current link through it.
                $lockedRequest->accessLinks()
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->get()
                    ->each(function (ReferralClientAccessLink $link) use ($lockedRequest): void {
                        if ($lockedRequest->due_at !== null && $lockedRequest->due_at->isAfter($link->expires_at)) {
                            $link->update(['expires_at' => $lockedRequest->due_at]);
                        }
                    });
            } else {
                $lockedRequest->accessLinks()
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);
            }

            return $lockedRequest;
        });

        $this->invalidateTrackingCaches($lockedRequest->referral);

        return $this->loadRequest($lockedRequest->fresh());
    }

    private function assertCanManageRequest(ReferralClientRequest $request, User $actor): void
    {
        $request->loadMissing('referral');
        $this->assertCanManageReferral($request->referral, $actor);
    }

    private function assertCanManageReferral(?Referral $referral, User $actor): void
    {
        if (! $referral || $actor->role !== 'AGENCY' || ! $actor->is_active || $actor->agcy_id !== $referral->agcy_id) {
            throw new AuthorizationException('Only the receiving agency may manage this referral request.');
        }
        $this->assertClientFacingWriteAllowed($referral);
    }

    private function assertCanRevoke(Referral $referral, User $actor): void
    {
        $isReceivingAgency = $actor->role === 'AGENCY' && $actor->is_active && $actor->agcy_id === $referral->agcy_id;
        $referral->loadMissing('caseFile');

        if (! $isReceivingAgency && $actor->role !== 'CASE_MANAGER' && $actor->role !== 'ADMIN') {
            throw new AuthorizationException('You may not revoke this access link.');
        }
    }

    private function isAuthorizedReader(Referral $referral, User $actor): bool
    {
        $referral->loadMissing('caseFile');

        return ($actor->role === 'AGENCY' && $actor->is_active && $actor->agcy_id === $referral->agcy_id)
            || $actor->role === 'CASE_MANAGER'
            || $actor->role === 'ADMIN';
    }

    private function assertClientFacingWriteAllowed(Referral $referral): void
    {
        if (! $this->isClientFacingWriteAllowed($referral)) {
            throw new LogicException('Client-facing referral requests are closed for this referral.');
        }
    }

    /** Whether client-facing writes (create/reply/transition) are still allowed on this referral. */
    public function isClientFacingWriteAllowed(Referral $referral): bool
    {
        $referral->loadMissing('caseFile');
        $case = $referral->caseFile;

        return ! (in_array($referral->status, ['COMPLETED', 'REJECTED'], true)
            || $referral->is_deleted
            || ! $case
            || $case->is_deleted
            || $case->closed_at !== null
            || in_array($case->status, ['CLOSED', 'ARCHIVED'], true));
    }

    private function assertOpen(ReferralClientRequest $request): void
    {
        if (! in_array($request->status, [ReferralClientRequest::STATUS_OPEN, ReferralClientRequest::STATUS_CLIENT_RESPONDED], true)) {
            throw new LogicException('This request is no longer open.');
        }
    }

    private function assertBody(string $body): void
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('Message body is required.');
        }
    }

    private function requestTypes(): array
    {
        return [ReferralClientRequest::TYPE_DOCUMENT_REQUEST, ReferralClientRequest::TYPE_QUESTION, ReferralClientRequest::TYPE_INFORMATION_UPDATE];
    }

    private function loadRequest(ReferralClientRequest $request): ReferralClientRequest
    {
        return $request->load(['referral', 'creator', 'items', 'milestone', 'messages.user', 'messages.accessLink', 'messages.attachments', 'accessLinks']);
    }

    private function invalidateTrackingCaches(Referral $referral): void
    {
        $referral->loadMissing('caseFile');
        if ($referral->case_id) {
            TrackingService::invalidateTrackingCache($referral->case_id, $referral->id);
        }
    }
}
