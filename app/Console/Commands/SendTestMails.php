<?php

namespace App\Console\Commands;

use App\Mail\ClientUpdateMail;
use App\Mail\EmailChangedNotification;
use App\Mail\OtpMail;
use App\Mail\ReferralOverdueMail;
use App\Mail\SurveyRequestMail;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\SurveyInvitation;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use App\Notifications\MilestoneAdded;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use App\Notifications\SystemAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendTestMails extends Command
{
    protected $signature = 'app:send-test-mails {to? : Recipient email}';

    protected $description = 'Send one of each email type to Mailpit for visual review';

    public function handle(): int
    {
        $to = $this->argument('to') ?? 'test@bayanihan.gov.ph';

        $this->components->info("Sending test emails to {$to} via default mailer...");

        $referral = Referral::with('caseFile.client', 'agency', 'milestones')->inRandomOrder()->first();
        if (! $referral) {
            $referral = Referral::factory()->create();
        }
        $case = $referral->caseFile;
        $milestone = $referral->milestones()->first();
        if (! $milestone) {
            $milestone = new Milestone(['title' => 'Documents Submitted']);
        }

        // ── Mailables (these go through Mail::send → proper CSS inlining) ──

        $this->components->task('OTP (login)', fn () => Mail::to($to)->send(new OtpMail('123456', 'login')));
        $this->components->task('OTP (email_change)', fn () => Mail::to($to)->send(new OtpMail('654321', 'email_change')));
        $this->components->task('OTP (track)', fn () => Mail::to($to)->send(new OtpMail('789012', 'track')));
        $this->components->task('Email Changed', fn () => Mail::to($to)->send(new EmailChangedNotification('old@email.com', 'new@email.com', 'Juan Dela Cruz')));
        $this->components->task('Client Update', fn () => Mail::to($to)->send(new ClientUpdateMail($case, 'Your case has been updated. The agency is now reviewing your documents.', 'Case Manager')));
        $this->components->task('Survey Request', function () use ($to, $referral) {
            $invitation = SurveyInvitation::where('agency_id', $referral->agcy_id)->first();
            $rawToken = Str::random(64);
            if (! $invitation) {
                $invitation = new SurveyInvitation([
                    'client_name' => 'Juan Dela Cruz',
                    'service_name' => 'OFW Assistance',
                    'agency_id' => $referral->agcy_id,
                ]);
                $invitation->setRelation('agency', $referral->agency);
            }
            Mail::to($to)->send(new SurveyRequestMail($invitation, $rawToken));
        });
        $this->components->task('Overdue Referral', fn () => Mail::to($to)->send(new ReferralOverdueMail($referral, 7)));

        // ── Notifications (via Notification::send → proper mail channel → CSS inlining) ──

        $notifiable = new class($to)
        {
            public string $email;

            public function __construct(string $email)
            {
                $this->email = $email;
            }

            public function routeNotificationFor(string $driver, ?object $notification = null): mixed
            {
                return match ($driver) {
                    'mail' => $this->email,
                    'database' => new class
                    {
                        public function create(array $data): object
                        {
                            return (object) $data;
                        }
                    },
                    default => null,
                };
            }

            public function getKey(): mixed
            {
                return null;
            }
        };

        $this->components->task('Referral Created', fn () => Notification::sendNow($notifiable, new ReferralCreated($referral)));
        $this->components->task('Referral Status Changed', fn () => Notification::sendNow($notifiable, new ReferralStatusChanged($referral, 'pending', 'in_review')));
        $this->components->task('Case Status Updated', fn () => Notification::sendNow($notifiable, new CaseStatusUpdated($case, 'open', 'in_progress')));
        $this->components->task('Case Updated', fn () => Notification::sendNow($notifiable, new CaseUpdated($case, 'Admin User', ['status' => ['old' => 'Open', 'new' => 'In Progress']])));
        $this->components->task('Milestone Added', fn () => Notification::sendNow($notifiable, new MilestoneAdded($milestone, $referral)));
        $this->components->task('System Alert', fn () => Notification::sendNow($notifiable, new SystemAlertNotification('queue_failure', 'critical', 'Queue worker has stopped responding.')));

        $this->components->success('All 13 test emails sent! Check http://localhost:8025');

        return self::SUCCESS;
    }
}
