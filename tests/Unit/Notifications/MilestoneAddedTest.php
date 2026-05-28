<?php

namespace Tests\Unit\Notifications;

use App\Models\Milestone;
use App\Models\Referral;
use App\Notifications\MilestoneAdded;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class MilestoneAddedTest extends TestCase
{
    public function test_it_returns_expected_channels(): void
    {
        $milestone = (new Milestone)->forceFill([
            'id' => 'milestone-1',
            'title' => 'First Milestone',
            'description' => 'Desc',
            'refr_id' => 'ref-1',
        ]);

        $referral = (new Referral)->forceFill([
            'id' => 'ref-1',
            'case_id' => 'case-1',
        ]);

        $notification = new MilestoneAdded($milestone, $referral);

        $this->assertSame(['database', 'mail'], $notification->via((object) []));
    }

    public function test_it_returns_expected_database_payload(): void
    {
        $milestone = (new Milestone)->forceFill([
            'id' => 'milestone-1',
            'title' => 'First Milestone',
            'description' => 'Desc',
            'refr_id' => 'ref-1',
        ]);

        $referral = (new Referral)->forceFill([
            'id' => 'ref-1',
            'case_id' => 'case-1',
        ]);

        $notification = new MilestoneAdded($milestone, $referral);

        $this->assertSame([
            'type' => 'milestone_added',
            'referral_id' => 'ref-1',
            'case_id' => 'case-1',
            'milestone_id' => 'milestone-1',
            'milestone_title' => 'First Milestone',
            'message' => "New milestone 'First Milestone' added to referral",
            'url' => route('referrals.show', $referral),
        ], $notification->toDatabase((object) []));
    }

    public function test_it_returns_a_mail_message(): void
    {
        $milestone = (new Milestone)->forceFill([
            'id' => 'milestone-1',
            'title' => 'First Milestone',
            'description' => 'Desc',
            'refr_id' => 'ref-1',
        ]);

        $referral = (new Referral)->forceFill([
            'id' => 'ref-1',
            'case_id' => 'case-1',
        ]);
        $referral->setRelation('caseFile', (object) ['case_number' => 'CF-001']);

        $notification = new MilestoneAdded($milestone, $referral);

        $this->assertInstanceOf(MailMessage::class, $notification->toMail((object) []));
    }
}
