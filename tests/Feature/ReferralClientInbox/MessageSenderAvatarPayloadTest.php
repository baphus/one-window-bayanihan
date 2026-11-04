<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Enums\UserRole;
use App\Models\ReferralClientMessage;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\StorageService;
use Illuminate\Support\Facades\URL;

class MessageSenderAvatarPayloadTest extends ReferralClientInboxTestCase
{
    public function test_referral_request_messages_expose_authenticated_sender_avatar_urls(): void
    {
        $context = $this->context();
        $agencyUser = $context['agencyUser'];
        $agencyUser->avatar_url = 'private/agency-avatar.jpg';
        $agencyUser->save();
        $clientUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $context['client']->id,
            'avatar_url' => 'private/client-avatar.jpg',
        ]);
        $request = $context['clientRequest'];
        $issued = $this->issue($context);
        $agencyMessage = ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => $agencyUser->id,
        ]);
        $ownerMessage = ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER,
            'user_id' => $clientUser->id,
        ]);
        $anonymousMessage = ReferralClientMessage::factory()->create([
            'request_id' => $request->id,
            'sender_kind' => ReferralClientMessage::SENDER_CLIENT_ACCESS,
            'user_id' => null,
            'access_link_id' => $issued['link']->id,
        ]);

        $history = app(ReferralService::class)->getClientRequestHistory($context['referral']);
        $messages = collect($history[0]['messages'])->keyBy('id');

        $this->assertSame(URL::temporarySignedRoute(StorageService::TEMP_FILE_ROUTE, now()->addMinutes(5), [
            'disk' => 'private', 'path' => 'private/agency-avatar.jpg',
        ]), $messages[$agencyMessage->id]['sender_avatar_url']);
        $this->assertNotNull($messages[$ownerMessage->id]['sender_avatar_url']);
        $this->assertNull($messages[$anonymousMessage->id]['sender_avatar_url']);
    }
}
