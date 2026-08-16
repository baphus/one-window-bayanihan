<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\ReferralClientMessageAttachment;
use App\Services\ReferralClientRequestService;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ClientRequestAttachmentUploadTest extends ReferralClientInboxTestCase
{
    public function test_client_can_upload_documents_with_reply(): void
    {
        Config::set('filesystems.default', 'object-storage');
        Storage::fake('object-storage');

        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']])
            ->assertRedirect(route('track.request.index'));

        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 client document', 'application/pdf');

        $this->post(route('track.request.messages.store'), [
            'body' => 'Here is my passport.',
            'attachments' => [$file],
        ])->assertRedirect(route('track.request.index'));

        $this->assertDatabaseHas('referral_client_messages', [
            'request_id' => $context['clientRequest']->id,
            'sender_kind' => 'CLIENT_ACCESS',
        ]);

        $attachment = ReferralClientMessageAttachment::first();
        $this->assertNotNull($attachment);
        $this->assertSame('passport.pdf', $attachment->file_name);
        $this->assertStringStartsWith('client-request-attachments/'.$context['clientRequest']->id.'/', $attachment->file_path);
        Storage::disk('object-storage')->assertExists($attachment->file_path);

        // The client sees the attachment in the request thread.
        $this->get(route('track.request.index'))->assertInertia(fn ($page) => $page
            ->component('Tracking/Show')
            ->where('clientRequestPanel.activeRequest.messages.0.attachments.0.file_name', 'passport.pdf'));
    }

    public function test_reply_requires_message_or_attachment(): void
    {
        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);

        $this->from(route('track.request.index'))
            ->post(route('track.request.messages.store'), [])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('referral_client_messages', 0);
    }

    public function test_reply_rejects_invalid_attachment_type(): void
    {
        Storage::fake('object-storage');

        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);

        $file = UploadedFile::fake()->createWithContent('virus.exe', 'MZ executable', 'application/x-msdownload');

        $this->from(route('track.request.index'))
            ->post(route('track.request.messages.store'), [
                'body' => 'Here it is.',
                'attachments' => [$file],
            ])->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('referral_client_messages', 0);
        $this->assertDatabaseCount('referral_client_message_attachments', 0);
    }

    public function test_attachment_download_requires_matching_capability(): void
    {
        Storage::fake('object-storage');

        $context = $this->context();
        $other = $this->context();
        $issued = $this->issue($context);

        // Bind the session to the first request, then create an attachment on another request.
        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);

        $otherIssued = $this->issue($other);
        $otherMessage = app(ReferralClientRequestService::class)->sendClientMessage(
            $other['clientRequest'],
            'secret attachment',
            $otherIssued['link'],
        );
        $attachment = $otherMessage->attachments()->create([
            'file_name' => 'secret.pdf',
            'file_path' => 'client-request-attachments/'.$other['clientRequest']->id.'/secret.pdf',
            'file_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->get(route('track.request.attachments.download', $attachment))->assertNotFound();
    }

    public function test_attachment_download_without_session_is_not_found(): void
    {
        Storage::fake('object-storage');

        $context = $this->context();
        $issued = $this->issue($context);

        $message = app(ReferralClientRequestService::class)->sendClientMessage(
            $context['clientRequest'],
            'A reply',
            $issued['link'],
        );
        $attachment = $message->attachments()->create([
            'file_name' => 'passport.pdf',
            'file_path' => 'client-request-attachments/'.$context['clientRequest']->id.'/passport.pdf',
            'file_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->get(route('track.request.attachments.download', $attachment))->assertNotFound();
    }

    public function test_agency_can_download_attachment_on_own_referral(): void
    {
        Storage::fake('object-storage');

        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);
        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 client document', 'application/pdf');
        $this->post(route('track.request.messages.store'), [
            'body' => 'Docs attached.',
            'attachments' => [$file],
        ])->assertRedirect(route('track.request.index'));

        $attachment = ReferralClientMessageAttachment::first();

        $this->mock(StorageService::class, function ($mock): void {
            $mock->shouldReceive('temporaryUrl')->once()->andReturn('https://storage.test/client-request.pdf');
        });

        $this->actingAs($context['agencyUser'])
            ->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $attachment->id]))
            ->assertRedirect('https://storage.test/client-request.pdf');
    }

    public function test_other_agency_cannot_download_attachment(): void
    {
        Storage::fake('object-storage');

        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);
        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 client document', 'application/pdf');
        $this->post(route('track.request.messages.store'), [
            'body' => 'Docs attached.',
            'attachments' => [$file],
        ]);

        $attachment = ReferralClientMessageAttachment::first();

        $this->actingAs($context['otherAgencyUser'])
            ->get(route('referrals.client-requests.attachments.download', [$context['referral']->id, $attachment->id]))
            ->assertForbidden();
    }

    public function test_agency_sees_attachments_in_request_payload(): void
    {
        Storage::fake('object-storage');

        $context = $this->context();
        $issued = $this->issue($context);

        $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);
        $file = UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 client document', 'application/pdf');
        $this->post(route('track.request.messages.store'), [
            'body' => 'Docs attached.',
            'attachments' => [$file],
        ])->assertRedirect(route('track.request.index'));

        $this->actingAs($context['agencyUser'])
            ->getJson(route('referrals.client-requests.index', $context['referral']))
            ->assertOk()
            ->assertJsonPath('data.0.messages.0.attachments.0.file_name', 'passport.pdf');
    }
}
