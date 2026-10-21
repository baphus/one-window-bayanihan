<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\IntakeReviewMail;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OfwIntakeReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_intake_emails_the_ofw_and_active_case_managers_only(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => $client->id]);
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $inactiveManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'is_active' => false]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = User::factory()->create(['role' => UserRole::AGENCY->value]);

        $response = $this->actingAs($ofw)->post(route('ofw.intake.store'), [
            'summary' => 'Please help me.',
            'vulnerability_indicator' => 'None',
            'consent' => true,
        ]);

        $response->assertRedirect(route('ofw.dashboard'));
        $this->assertDatabaseHas('cases', [
            'client_id' => $client->id,
            'status' => CaseFile::STATUS_PENDING_REVIEW,
        ]);
        Mail::assertQueued(IntakeReviewMail::class, 2);
        Mail::assertQueued(IntakeReviewMail::class, function (IntakeReviewMail $mail) use ($ofw): bool {
            $this->assertSame('New case awaiting review', $mail->envelope()->subject);
            $this->assertStringContainsString(
                'being reviewed by a case manager',
                strtolower(strip_tags($mail->render())),
            );

            return $mail->hasTo($ofw->email);
        });
        Mail::assertQueued(IntakeReviewMail::class, fn (IntakeReviewMail $mail) => $mail->hasTo($caseManager->email));
        Mail::assertQueued(IntakeReviewMail::class, fn (IntakeReviewMail $mail) => ! $mail->hasTo($inactiveManager->email)
            && ! $mail->hasTo($admin->email)
            && ! $mail->hasTo($agency->email)
        );
    }

    public function test_invalid_intake_does_not_send_review_email(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => $client->id]);

        $response = $this->actingAs($ofw)->post(route('ofw.intake.store'), [
            'summary' => '',
            'vulnerability_indicator' => 'None',
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('summary');
        Mail::assertNothingQueued();
    }

    public function test_intake_without_linked_client_does_not_send_review_email(): void
    {
        Mail::fake();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'client_id' => null]);

        $response = $this->actingAs($ofw)->post(route('ofw.intake.store'), [
            'summary' => 'Please help me.',
            'vulnerability_indicator' => 'None',
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('client');
        Mail::assertNothingQueued();
    }
}
