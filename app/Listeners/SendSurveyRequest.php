<?php

namespace App\Listeners;

use App\Events\ReferralCompleted;
use App\Mail\SurveyRequestMail;
use App\Models\SurveyInvitation;
use App\Models\SystemSetting;
use App\Services\SurveyFormService;
use App\Services\SurveyInvitationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSurveyRequest implements ShouldQueueAfterCommit
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

        if (! $caseFile->client || ! $caseFile->client->email) {
            Log::warning('Survey request skipped: missing client email', [
                'referral_id' => $referral->id,
                'case_id' => $referral->case_id,
            ]);

            return;
        }

        // Prevent duplicate survey invitations for the same referral
        if (SurveyInvitation::where('case_id', $referral->case_id)
            ->where('agency_id', $referral->agcy_id)
            ->where('referral_id', $referral->id)
            ->exists()) {
            return;
        }

        // Get the agency's active survey form
        $activeForm = app(SurveyFormService::class)->getActiveFormForAgency($agency->id);

        if (! $activeForm) {
            Log::warning('Survey request skipped: no active survey form for agency', [
                'referral_id' => $referral->id,
                'agency_id' => $agency->id,
            ]);

            return;
        }

        // Build client name from the case file's client
        $clientName = trim($caseFile->client->first_name.' '.$caseFile->client->last_name);

        // Build service name from the first comma-separated value in required_services
        $serviceName = trim(explode(',', $referral->required_services ?? '')[0]) ?: 'General Service';

        // Create the survey invitation
        $created = app(SurveyInvitationService::class)->createInvitation(
            referralId: $referral->id,
            clientName: $clientName,
            clientEmail: $caseFile->client->email,
            serviceName: $serviceName,
            surveyFormId: $activeForm->id,
        );

        if (! $created) {
            return;
        }

        $invitation = $created->invitation;
        Log::info('Survey invitation created', [
            'invitation_id' => $invitation->id,
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'agency_id' => $referral->agcy_id,
        ]);

        // Queue the survey request email
        Mail::to($caseFile->client->email)->queue(new SurveyRequestMail($invitation, $created->rawToken));
    }
}
