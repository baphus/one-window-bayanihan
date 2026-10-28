<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Models\ActivityEvent;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Services\ActivityScope;
use App\Services\ActivityVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ActivityController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $this->perPage($request, 25);
        $search = trim((string) $request->input('search', ''));
        $filters = [
            'date_from' => $request->filled('date_from') ? (string) $request->input('date_from') : '',
            'date_to' => $request->filled('date_to') ? (string) $request->input('date_to') : '',
            'module' => $request->filled('module') ? (string) $request->input('module') : '',
            'per_page' => $request->filled('per_page') ? (string) $request->input('per_page') : '',
            'search' => $search,
        ];

        $query = ActivityScope::applyTo(ActivityEvent::query(), $user);
        $query->when($filters['date_from'] !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $filters['date_to']))
            ->when($filters['module'] !== '', fn (Builder $q) => $q->whereIn('module', explode(',', $filters['module'])))
            ->when($search !== '', function (Builder $q) use ($search) {
                $term = "%{$search}%";

                $q->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('description', 'ILIKE', $term)
                        ->orWhere('actor_name', 'ILIKE', $term)
                        ->orWhere('module', 'ILIKE', $term)
                        ->orWhere('action', 'ILIKE', $term);
                });
            });

        $events = $query->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($filters);

        $this->presentEvents($events);

        $availableModules = collect(ActivityVisibility::modulesFor($user->role))
            ->map(fn (string $module) => AuditModule::tryFromLegacy($module))
            ->filter()
            ->unique(fn (AuditModule $module) => $module->value)
            ->sortBy(fn (AuditModule $module) => $module->value)
            ->map(fn (AuditModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
            ])
            ->values()
            ->all();

        return Inertia::render('Activity/Index', [
            'events' => $events,
            'filters' => (object) $filters,
            'availableModules' => $availableModules,
            'viewTitle' => 'Activity',
        ]);
    }

    public function caseActivity(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $case = CaseFile::where('is_deleted', false)->findOrFail($id);

        if (! $user->isAdmin() && ! $user->isCaseManager()
            && ! $case->referrals()->where('agcy_id', $user->agcy_id)
                ->whereNotIn('status', ['COMPLETED', 'REJECTED'])->exists()) {
            abort(403, 'You do not have access to this case.');
        }

        $events = ActivityEvent::query()
            ->whereIn('module', ActivityVisibility::modulesFor($user->role))
            ->whereIn('action', ActivityVisibility::actionsFor($user->role))
            ->whereIn('entity_id', ActivityScope::caseEntityIds($case->id))
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return response()->json($this->presentEvents($events));
    }

    public function referralActivity(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $referral = Referral::where('is_deleted', false)->findOrFail($id);

        if (! $user->isAdmin() && ! $user->isCaseManager() && $referral->agcy_id !== $user->agcy_id) {
            abort(403, 'You do not have access to this referral.');
        }

        $events = ActivityEvent::query()
            ->whereIn('module', ActivityVisibility::modulesFor($user->role))
            ->whereIn('action', ActivityVisibility::actionsFor($user->role))
            ->whereIn('entity_id', ActivityScope::referralEntityIds($referral->id))
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return response()->json($this->presentEvents($events));
    }

    private function presentEvents(CursorPaginator|LengthAwarePaginator $events): CursorPaginator|LengthAwarePaginator
    {
        $links = $this->resolveLinks($events->getCollection());
        $events->setCollection(
            $events->getCollection()
                ->map(fn (ActivityEvent $event) => $this->presentEvent($event, $links))
                ->values()
        );

        return $events;
    }

    /**
     * Resolve all links for one page in batches. Child activity rows point to
     * their owning referral through different foreign-key chains, so each table
     * gets one lookup instead of one lookup per rendered row.
     *
     * @return array<string, string|null>
     */
    private function resolveLinks(Collection $events): array
    {
        $links = [];
        $idsByModule = [];

        foreach ($events as $event) {
            $module = AuditModule::tryFromLegacy((string) $event->module);
            $entityId = $event->entity_id;

            if ($module === null || $entityId === null) {
                continue;
            }

            if ($module === AuditModule::CASE) {
                $links[(string) $entityId] = route('cases.show', $entityId);

                continue;
            }

            if ($module === AuditModule::REFERRAL) {
                $links[(string) $entityId] = route('referrals.show', $entityId);

                continue;
            }

            $idsByModule[$module->value][] = (string) $entityId;
        }

        $ids = static fn (string $module): array => array_values(array_unique($idsByModule[$module] ?? []));
        $referralIds = [];

        $milestoneIds = $ids(AuditModule::MILESTONE->value);
        if ($milestoneIds !== []) {
            $referralIds = array_replace(
                $referralIds,
                DB::table('milestones')->whereIn('id', $milestoneIds)->pluck('refr_id', 'id')->all()
            );
        }

        $attachmentIds = $ids(AuditModule::REFERRAL_ATTACHMENT->value);
        if ($attachmentIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_attachments')->whereIn('id', $attachmentIds)->pluck('referral_id', 'id')->all());
        }

        $serviceRequirementIds = $ids(AuditModule::REFERRAL_SERVICE_REQUIREMENT->value);
        if ($serviceRequirementIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_service_requirements')->whereIn('id', $serviceRequirementIds)->pluck('referral_id', 'id')->all()
            );
        }

        $commentIds = $ids(AuditModule::REFERRAL_COMMENT->value);
        if ($commentIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_comments')->whereIn('id', $commentIds)->pluck('refr_id', 'id')->all()
            );
        }

        $requestIds = $ids(AuditModule::REFERRAL_CLIENT_REQUEST->value);
        if ($requestIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_client_requests')->whereIn('id', $requestIds)->pluck('referral_id', 'id')->all()
            );
        }

        $requestItemIds = $ids(AuditModule::REFERRAL_CLIENT_REQUEST_ITEM->value);
        if ($requestItemIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_client_request_items as items')
                ->join('referral_client_requests as requests', 'requests.id', '=', 'items.request_id')
                ->whereIn('items.id', $requestItemIds)
                ->pluck('requests.referral_id', 'items.id')
                ->all()
            );
        }

        $messageIds = $ids(AuditModule::REFERRAL_CLIENT_MESSAGE->value);
        if ($messageIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_client_messages as messages')
                ->join('referral_client_requests as requests', 'requests.id', '=', 'messages.request_id')
                ->whereIn('messages.id', $messageIds)
                ->pluck('requests.referral_id', 'messages.id')
                ->all()
            );
        }

        $messageAttachmentIds = $ids(AuditModule::REFERRAL_CLIENT_MESSAGE_ATTACHMENT->value);
        if ($messageAttachmentIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_client_message_attachments as attachments')
                ->join('referral_client_messages as messages', 'messages.id', '=', 'attachments.message_id')
                ->join('referral_client_requests as requests', 'requests.id', '=', 'messages.request_id')
                ->whereIn('attachments.id', $messageAttachmentIds)
                ->pluck('requests.referral_id', 'attachments.id')
                ->all()
            );
        }

        $accessLinkIds = $ids(AuditModule::REFERRAL_CLIENT_ACCESS_LINK->value);
        if ($accessLinkIds !== []) {
            $referralIds = array_replace($referralIds, DB::table('referral_client_access_links as links')
                ->join('referral_client_requests as requests', 'requests.id', '=', 'links.request_id')
                ->whereIn('links.id', $accessLinkIds)
                ->pluck('requests.referral_id', 'links.id')
                ->all()
            );
        }

        $caseDocumentIds = $ids(AuditModule::CASE_DOCUMENT->value);
        if ($caseDocumentIds !== []) {
            foreach (DB::table('case_documents')->whereIn('id', $caseDocumentIds)
                ->get(['id', 'case_id', 'referral_id']) as $document) {
                $links[(string) $document->id] = $document->referral_id !== null
                    ? route('referrals.show', $document->referral_id)
                    : ($document->case_id !== null ? route('cases.show', $document->case_id) : null);
            }
        }

        foreach ($referralIds as $entityId => $referralId) {
            $links[(string) $entityId] = $referralId !== null
                ? route('referrals.show', $referralId)
                : null;
        }

        return $links;
    }

    private function presentEvent(ActivityEvent $event, array $links): array
    {
        $module = AuditModule::tryFromLegacy($event->module);
        $actorRole = $event->actor_role;

        if ($actorRole !== null) {
            $actorRole = UserRole::tryFrom($actorRole)?->label() ?? $actorRole;
        }

        return [
            'id' => (string) $event->id,
            'occurredAt' => $event->occurred_at?->toISOString(),
            'actor' => $event->actor_name,
            'actorRole' => $actorRole,
            'module' => $module?->value ?? $event->module,
            'moduleLabel' => $module?->label() ?? $event->module,
            'action' => $event->action,
            'description' => $event->description,
            'entityId' => $event->entity_id,
            'entityType' => $module?->value ?? $event->module,
            'link' => $links[(string) $event->entity_id] ?? null,
        ];
    }
}
