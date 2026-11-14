<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ClientUpdateMail;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseStatusUpdatedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_email_lists_every_assigned_category_and_case_details(): void
    {
        $caseManager = User::factory()->create([
            'name' => 'Internal Caseworker',
            'email' => 'cm@example.com',
        ]);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'summary' => 'Needs legal aid and repatriation.',
        ]);

        $legal = CaseCategory::factory()->create(['name' => 'Legal']);
        $repatriation = CaseCategory::factory()->create(['name' => 'Repatriation']);
        foreach ([$legal, $repatriation] as $category) {
            Referral::factory()->create([
                'case_id' => $case->id,
                'category_id' => $category->id,
                'concern' => 'Assistance requested',
            ]);
        }

        $html = (new CaseStatusUpdated($case->fresh(), 'OPEN', 'CLOSED', 'Stu Torres'))
            ->toMail($caseManager)
            ->render();

        // The primary category alone used to be the only one printed.
        $this->assertStringContainsString('Repatriation', $html);
        $this->assertStringContainsString('Needs legal aid and repatriation.', $html);
        $this->assertStringContainsString('Internal Caseworker', $html);
        $this->assertStringContainsString('Stu Torres', $html);
    }

    public function test_subject_names_the_case_and_new_status(): void
    {
        $case = CaseFile::factory()->create();
        $manager = User::factory()->create();

        $message = (new CaseStatusUpdated($case, 'OPEN', 'CLOSED'))->toMail($manager);

        $this->assertSame("Case {$case->case_number} is now Closed", $message->subject);
    }

    public function test_client_email_carries_details_but_not_internal_fields(): void
    {
        $caseManager = User::factory()->create(['name' => 'Internal Caseworker']);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email' => 'ofw@example.com']);
        $case = CaseFile::factory()->create([
            'user_id' => $caseManager->id,
            'ofw_user_id' => $ofw->id,
            'summary' => 'Internal note: do not share.',
        ]);

        $category = CaseCategory::factory()->create(['name' => 'Legal']);
        Referral::factory()->create([
            'case_id' => $case->id,
            'category_id' => $category->id,
            'concern' => 'Passport assistance',
            'required_services' => 'Passport assistance',
        ]);

        $html = (new ClientUpdateMail($case->fresh(), 'Your case is being processed.', 'system'))
            ->render();

        $this->assertStringContainsString('Legal', $html);
        $this->assertStringContainsString('Passport assistance', $html);
        $this->assertStringNotContainsString('Internal Caseworker', $html);
        $this->assertStringNotContainsString('Internal note: do not share.', $html);
    }
}
