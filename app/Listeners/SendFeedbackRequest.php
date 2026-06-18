<?php

namespace App\Listeners;

use App\Events\ReferralCompleted;
use App\Mail\FeedbackRequestMail;
use App\Models\CaseNotification;
use App\Models\Feedback;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        // Prevent duplicate feedback requests — skip if feedback already exists
        if (Feedback::where('case_id', $referral->case_id)
            ->where('agency_id', $referral->agcy_id)
            ->where('referral_id', $referral->id)
            ->exists()) {
            return;
        }
        $caseFile = $event->caseFile;
        $agency = $event->agency;
        $trackingToken = Str::uuid()->toString();

        if (! $caseFile->client || ! $caseFile->client->email) {
            Log::warning('Feedback request skipped: missing client email', [
                'referral_id' => $referral->id,
                'case_id' => $referral->case_id,
            ]);

            return;
        }

        CaseNotification::create([
            'case_id' => $referral->case_id,
            'client_email' => $caseFile->client->email,
            'type' => 'feedback_request',
            'title' => 'We Value Your Feedback',
            'message' => sprintf(
                'Your referral to %s (Ref: %s) has been completed. We value your experience and invite you to share your feedback.',
                $agency->name,
                $referral->id,
            ),
            'data' => [
                'referral_id' => $referral->id,
                'tracking_token' => $trackingToken,
                'agency_id' => $referral->agcy_id,
                'service_name' => $referral->required_services,
            ],
        ]);

        Mail::to($caseFile->client->email)->queue(new FeedbackRequestMail(
            referral: $referral,
            agency: $agency,
            caseFile: $caseFile,
            trackingToken: $trackingToken,
        ));
    }
}
