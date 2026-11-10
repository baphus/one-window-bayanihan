<?php

namespace App\Listeners;

use App\Events\ReferralCompleted;
use App\Models\SurveyInvitation;
use App\Models\SystemSetting;
use App\Services\SurveyFormService;
use App\Services\SurveyInvitationService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

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

        if (! $caseFile->email || ! $caseFile->consent_given_at) {
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
            app(SurveyInvitationService::class)->queuePendingInvitation($referral->id);

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

        $clientName = trim(($caseFile->first_name ?? '').' '.($caseFile->last_name ?? ''));

        // Build service name from the first comma-separated value in required_services
        $serviceName = trim(explode(',', $referral->required_services ?? '')[0]) ?: 'General Service';

        // Create the survey invitation
        $created = app(SurveyInvitationService::class)->createInvitation(
            referralId: $referral->id,
            clientName: $clientName,
            clientEmail: $caseFile->email,
            serviceName: $serviceName,
            surveyFormId: $activeForm->id,
        );

        if (! $created) {
            app(SurveyInvitationService::class)->queuePendingInvitation($referral->id);

            return;
        }

        $invitation = $created['invitation'];
        Log::info('Survey invitation created', [
            'invitation_id' => $invitation->id,
            'referral_id' => $referral->id,
            'case_id' => $referral->case_id,
            'agency_id' => $referral->agcy_id,
        ]);

        // Queue the survey request email
        app(SurveyInvitationService::class)->queuePendingInvitation($referral->id);
    }
}
