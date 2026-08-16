<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralClientMessageRequest;
use App\Http\Requests\StoreReferralClientRequest;
use App\Mail\ClientRequestMail;
use App\Models\CaseNotification;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Notifications\ReferralClientRequestActivity;
use App\Services\ReferralClientAccessService;
use App\Services\ReferralClientRequestService;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use LogicException;

class ReferralClientRequestController extends Controller
{
    private const SESSION_KEY = 'client_request_access';

    public function __construct(
        private readonly ReferralClientRequestService $requestService,
        private readonly ReferralClientAccessService $accessService,
    ) {}

    public function index(Request $request, Referral $referral)
    {
        $this->requestService->assertCanRead($referral, $request->user());

        // This is a JSON-only endpoint consumed by XHR. Direct browser
        // navigation (e.g. clicking a notification link) must land on the
        // referral page instead of a bare JSON dump.
        if (! $request->wantsJson() && ! $request->isJson()) {
            return redirect()->route('referrals.show', $referral);
        }

        $requests = $referral->clientRequests()
            ->where('is_deleted', false)
            ->with(['items', 'messages' => fn ($query) => $query->where('is_deleted', false)->orderBy('created_at'), 'messages.attachments'])
            ->latest()
            ->get()
            ->map(fn (ReferralClientRequest $clientRequest) => $this->agencyPayload($clientRequest));

        return response()->json(['data' => $requests]);
    }

    public function store(StoreReferralClientRequest $request, Referral $referral): RedirectResponse
    {
        try {
            $clientRequest = $this->requestService->createRequest(
                $referral,
                $request->user(),
                [
                    ...$request->validated(),
                    'items' => $request->validated('checklist', []),
                ],
            );
        } catch (LogicException $e) {
            // The referral/case is no longer open for client-facing writes
            // (e.g. case closed mid-session). Surface it as a form error so
            // Inertia can show it in the modal instead of a 500.
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        $delivery = $this->issueForClient($clientRequest, $request->user());
        $this->notifyCaseManager($clientRequest, 'created');

        return redirect()->route('referrals.show', $referral)->with('client_access_delivery', $delivery);
    }

    public function sendMessage(StoreReferralClientMessageRequest $request, ReferralClientRequest $clientRequest): RedirectResponse
    {
        $message = $this->requestService->sendAgencyMessage($clientRequest, $request->user(), $request->validated('body'));
        $clientRequest->loadMissing('referral');

        return redirect()->back()->with('success', 'Message sent.');
    }

    public function complete(Request $request, ReferralClientRequest $clientRequest): RedirectResponse
    {
        $this->requestService->complete($clientRequest, $request->user());

        return redirect()->back()->with('success', 'Client request completed.');
    }

    public function cancel(Request $request, ReferralClientRequest $clientRequest): RedirectResponse
    {
        $this->requestService->cancel($clientRequest, $request->user());

        return redirect()->back()->with('success', 'Client request cancelled.');
    }

    public function reopen(Request $request, ReferralClientRequest $clientRequest): RedirectResponse
    {
        $clientRequest = $this->requestService->reopen($clientRequest, $request->user());
        $delivery = $this->issueForClient($clientRequest, $request->user());

        return redirect()->back()->with('client_access_delivery', $delivery);
    }

    public function issue(Request $request, ReferralClientRequest $clientRequest): RedirectResponse
    {
        $this->requestService->assertCanManage($clientRequest, $request->user());
        $delivery = $this->issueForClient($clientRequest, $request->user());

        return redirect()->back()->with('client_access_delivery', $delivery);
    }

    public function reissue(Request $request, ReferralClientRequest $clientRequest): RedirectResponse
    {
        $this->requestService->assertCanManage($clientRequest, $request->user());
        $delivery = $this->issueForClient($clientRequest, $request->user());

        return redirect()->back()->with('client_access_delivery', $delivery);
    }

    public function revoke(Request $request, ReferralClientAccessLink $accessLink): RedirectResponse
    {
        $this->requestService->revokeAccessLink($accessLink, $request->user());

        return redirect()->back()->with('success', 'Client access revoked.');
    }

    public function exchange(Request $request): RedirectResponse
    {
        $token = $request->validate([
            'token' => ['required', 'string', 'min:40', 'max:256'],
        ])['token'];
        $link = $this->accessService->resolveUsableToken($token);
        if (! $link) {
            abort(404);
        }

        try {
            $link = $this->accessService->recordUse($link);
        } catch (LogicException) {
            abort(404);
        }

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'request_id' => $link->request_id,
            'link_id' => $link->id,
            'expires_at' => $link->expires_at->toIso8601String(),
        ]);

        return $this->capabilityResponse(redirect()->route('track.request.index'));
    }

    public function show(Request $request)
    {
        $clientRequest = $this->sessionRequest($request);
        if (! $clientRequest) {
            return $this->capabilityResponse(Inertia::render('Tracking/RequestAccess', [
                'exchangeUrl' => route('track.request.exchange'),
            ]), $request);
        }

        $clientRequest->loadMissing(['referral.agency', 'items', 'messages.attachments']);
        $payload = [
            'type' => $clientRequest->type,
            'title' => $clientRequest->title,
            'instructions' => $clientRequest->instructions,
            'due_at' => $clientRequest->due_at?->toIso8601String(),
            'status' => $clientRequest->status,
            'agency_name' => $clientRequest->referral?->agency?->name ?? 'the agency',
            'checklist' => $clientRequest->items
                ->where('is_deleted', false)
                ->map(fn ($item) => ['id' => $item->id, 'label' => $item->label, 'sort_order' => $item->sort_order])
                ->values(),
            'messages' => $clientRequest->messages
                ->where('is_deleted', false)
                ->where('kind', 'MESSAGE')
                ->sortBy('created_at')
                ->map(fn ($message) => $this->messagePayload($message))
                ->values(),
        ];

        return $this->capabilityResponse(Inertia::render('Tracking/Show', [
            'clientRequestPanel' => [
                'state' => 'ready',
                'activeRequest' => $payload,
                'actions' => [
                    'reply' => route('track.request.messages.store'),
                    'requestReplacement' => route('track.request.replacement'),
                    'exchange' => route('track.request.exchange'),
                ],
            ],
        ]), $request);
    }

    public function clientMessage(Request $request): RedirectResponse
    {
        $clientRequest = $this->sessionRequest($request);
        if (! $clientRequest || ! $this->requestService->canClientSubmit($clientRequest)) {
            abort(404);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
        ]);
        $files = $request->file('attachments', []);

        if (trim($validated['body'] ?? '') === '' && $files === []) {
            throw ValidationException::withMessages(['body' => 'Write a message or attach at least one document.']);
        }

        $session = $request->session()->get(self::SESSION_KEY);
        $link = ReferralClientAccessLink::query()->find($session['link_id']);
        if (! $link || $link->request_id !== $clientRequest->id || ! $this->accessService->isUsableLink($link)) {
            abort(404);
        }

        try {
            $this->requestService->sendClientMessage(
                $clientRequest,
                trim($validated['body'] ?? ''),
                $link,
                $files,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['attachments' => $e->getMessage()]);
        }
        $this->notifyClientReply($clientRequest);

        return $this->capabilityResponse(redirect()->route('track.request.index')->with('success', 'Message sent.'));
    }

    public function downloadAttachment(Request $request, ReferralClientMessageAttachment $attachment): RedirectResponse
    {
        $clientRequest = $this->sessionRequest($request);
        if (! $clientRequest) {
            abort(404);
        }

        $attachment->loadMissing('message');
        if (! $attachment->message || $attachment->message->request_id !== $clientRequest->id) {
            abort(404);
        }

        $url = app(StorageService::class)->temporaryUrl($attachment->file_path, 24);
        if (! $url) {
            abort(404, 'File not found or unavailable.');
        }

        return $this->capabilityResponse(redirect()->away($url));
    }

    public function downloadAgencyAttachment(Request $request, Referral $referral, ReferralClientMessageAttachment $attachment): RedirectResponse
    {
        $attachment->loadMissing('message.request');
        $clientRequest = $attachment->message?->request;

        if (! $clientRequest || $clientRequest->referral_id !== $referral->id) {
            abort(404);
        }

        $this->requestService->assertCanRead($clientRequest, $request->user());

        $url = app(StorageService::class)->temporaryUrl($attachment->file_path, 24);
        if (! $url) {
            abort(404, 'File not found or unavailable.');
        }

        return redirect()->away($url);
    }

    public function replacement(Request $request): RedirectResponse
    {
        $clientRequest = $this->sessionRequest($request);
        if ($clientRequest) {
            $this->notifyAgency($clientRequest, 'replacement_requested');
        }

        return $this->capabilityResponse(
            redirect()->route('track.request.index')->with('success', 'Your request has been received.'),
        );
    }

    private function issueForClient(ReferralClientRequest $clientRequest, User $issuer): array
    {
        $clientRequest->loadMissing('referral.agency', 'referral.caseFile.client');
        $client = $clientRequest->referral?->caseFile?->client;
        $email = $client?->email;
        if (! $email) {
            $this->recordDelivery($clientRequest, null, 'not_sent_no_email');

            return ['status' => 'no_email', 'message' => 'Request saved; no client email is available for delivery.'];
        }

        $issued = $this->accessService->issue($clientRequest, $issuer, [
            'name' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')),
            'email' => $email,
        ]);
        $magicLink = route('track.request.index').'#token='.rawurlencode($issued['raw_token']);
        Mail::to($email)->queue(new ClientRequestMail($clientRequest, $issued['raw_token'], $magicLink));
        $this->recordDelivery($clientRequest, $email, 'queued');

        return ['status' => 'queued', 'message' => 'Request saved and access email queued.'];
    }

    private function recordDelivery(ReferralClientRequest $clientRequest, ?string $clientEmail, string $status): void
    {
        $clientRequest->loadMissing('referral');

        // CaseNotification requires a recipient email. For a no-email client,
        // retain the delivery outcome in the owning case manager's safe
        // request notification instead of inventing a client recipient.
        if ($clientEmail === null) {
            $this->notifyCaseManager($clientRequest, 'delivery_'.$status);

            return;
        }

        CaseNotification::create([
            'case_id' => $clientRequest->referral?->case_id,
            'client_email' => $clientEmail,
            'type' => 'client_request_delivery',
            'title' => 'Client request access delivery',
            'message' => 'Client request access delivery status: '.$status.'.',
            'data' => [
                'request_id' => $clientRequest->id,
                'referral_id' => $clientRequest->referral_id,
                'status' => $status,
            ],
            'related_url' => '/referrals/'.$clientRequest->referral_id.'/client-requests',
        ]);
    }

    private function sessionRequest(Request $request): ?ReferralClientRequest
    {
        $session = $request->session()->get(self::SESSION_KEY);
        if (! is_array($session) || ! isset($session['request_id'], $session['link_id'], $session['expires_at'])) {
            return null;
        }

        $link = ReferralClientAccessLink::query()
            ->whereKey($session['link_id'])
            ->where('request_id', $session['request_id'])
            ->with(['request.referral.caseFile'])
            ->first();
        if (! $link
            || ($session['expires_at'] ?? null) !== $link->expires_at?->toIso8601String()
            || ! $this->accessService->isUsableLink($link)) {
            return null;
        }

        return $link->request;
    }

    private function notifyCaseManager(ReferralClientRequest $clientRequest, string $activity): void
    {
        $clientRequest->loadMissing('referral.caseFile');
        $owner = $clientRequest->referral?->caseFile?->user;
        if ($owner) {
            $owner->notify(new ReferralClientRequestActivity($activity, $clientRequest->id, $clientRequest->referral_id, $clientRequest->title, $clientRequest->status));
        }
    }

    private function notifyAgency(ReferralClientRequest $clientRequest, string $activity): void
    {
        $clientRequest->loadMissing('referral');
        $users = User::query()
            ->where('role', 'AGENCY')
            ->where('is_active', true)
            ->where('agcy_id', $clientRequest->referral?->agcy_id)
            ->get();
        Notification::send($users, new ReferralClientRequestActivity($activity, $clientRequest->id, $clientRequest->referral_id, $clientRequest->title, $clientRequest->status));
    }

    private function notifyClientReply(ReferralClientRequest $clientRequest): void
    {
        $this->notifyAgency($clientRequest, 'client_reply');
        $this->notifyCaseManager($clientRequest, 'client_reply');
    }

    private function agencyPayload(ReferralClientRequest $clientRequest): array
    {
        return [
            'id' => $clientRequest->id,
            'referral_id' => $clientRequest->referral_id,
            'type' => $clientRequest->type,
            'title' => $clientRequest->title,
            'instructions' => $clientRequest->instructions,
            'status' => $clientRequest->status,
            'due_at' => $clientRequest->due_at?->toIso8601String(),
            'checklist' => $clientRequest->items->map(fn ($item) => ['id' => $item->id, 'label' => $item->label])->values(),
            'messages' => $clientRequest->messages->map(fn ($message) => $this->messagePayload($message))->values(),
        ];
    }

    private function messagePayload($message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'sender_kind' => $message->sender_kind,
            'created_at' => $message->created_at?->toIso8601String(),
            'attachments' => $message->attachments
                ?->where('is_deleted', false)
                ->values()
                ->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_type' => $attachment->file_type,
                    'size' => $attachment->size,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                ])
                ->values(),
        ];
    }

    private function capabilityResponse($response, ?Request $request = null)
    {
        if ($response instanceof InertiaResponse) {
            $response = $response->toResponse($request ?? request());
        }

        return $response->withHeaders([
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
