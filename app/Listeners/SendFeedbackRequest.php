<?php

namespace App\Listeners;

use App\Events\ReferralCompleted;
use App\Mail\FeedbackRequestMail;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Services\FeedbackInvitationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendFeedbackRequest implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'default';

    /**
     * Handle the event.
     */
    public function handle(ReferralCompleted $event): void
    {
        if (! SystemSetting::getValue('feedback_enabled', true)) {
            return;
        }

        $referral = $event->referral;
        $caseFile = $event->caseFile;
        $agency = $event->agency;

        // Prevent duplicate feedback requests — skip if feedback already exists
        if (Feedback::where('case_id', $referral->case_id)
            ->where('agency_id', $referral->agcy_id)
            ->where('referral_id', $referral->id)
            ->exists()) {
            return;
        }

        if (! $caseFile->client || ! $caseFile->client->email) {
            Log::warning('Feedback request skipped: missing client email', [
                'referral_id' => $referral->id,
                'case_id' => $referral->case_id,
            ]);

            return;
        }

        // Resolve service_id from referral's required_services text
        $serviceId = $this->resolveServiceId($referral->required_services, $referral->agcy_id);

        // Create invitation with a secure token
        $result = app(FeedbackInvitationService::class)->createInvitation(
            caseId: $referral->case_id,
            agencyId: $referral->agcy_id,
            referralId: $referral->id,
            clientEmail: $caseFile->client->email,
            serviceId: $serviceId,
        );

        $invitation = $result['invitation'];
        $rawToken = $result['token'];

        Log::info('Feedback invitation created', [
            'invitation_id' => $invitation->id,
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'agency_id' => $referral->agcy_id,
        ]);

        Mail::to($caseFile->client->email)->queue(new FeedbackRequestMail(
            invitation: $invitation,
            token: $rawToken,
        ));
    }

    /**
     * Resolve a service ID from the referral's required_services text.
     *
     * Takes the first service name from the comma-separated list
     * and matches it to a service record for the agency.
     */
    private function resolveServiceId(?string $requiredServices, string $agencyId): ?string
    {
        if (empty($requiredServices)) {
            return null;
        }

        // Take the first service name from comma-separated list
        $firstService = trim(explode(',', $requiredServices)[0]);

        if (empty($firstService)) {
            return null;
        }

        $service = Service::where('agcy_id', $agencyId)
            ->where('name', $firstService)
            ->first();

        return $service?->id;
    }
}
