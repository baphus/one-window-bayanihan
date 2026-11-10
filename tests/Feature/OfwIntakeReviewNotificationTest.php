<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\IntakeReviewMail;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfwIntakeReviewNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'client_type' => 'OFW',
            'summary' => 'Please help me.',
            'vulnerability_indicator' => 'None',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-06-15',
            'sex' => 'MALE',
            'email' => 'juan@example.test',
            'contact_number' => '09171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'vulnerability_indicator' => 'None',
            'return_reason' => 'OTHERS',
            'consent' => true,
            'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
            'proposed_assessments' => ['LEGAL_ASSISTANCE'],
            'documents' => collect(['passport_front_page', 'arrival_stamp', 'oec', 'sworn_affidavit'])
                ->mapWithKeys(fn (string $slot) => [$slot => UploadedFile::fake()->image($slot.'.jpg')])->all(),
        ];
    }

    public function test_successful_intake_emails_the_ofw_and_active_case_managers_only(): void
    {
        Storage::fake('object-storage');
        Mail::fake();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $inactiveManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'is_active' => false]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = User::factory()->create(['role' => UserRole::AGENCY->value]);
        $activeManagerEmails = User::where('role', UserRole::CASE_MANAGER->value)
            ->where('is_active', true)
            ->pluck('email');

        $response = $this->actingAsWithMfa($ofw)->post(route('ofw.intake.store'), $this->payload());

        $response->assertRedirect(route('ofw.dashboard'));
        $this->assertDatabaseHas('cases', [
            'ofw_user_id' => $ofw->id,
            'email' => 'juan@example.test',
            'status' => CaseFile::STATUS_PENDING_REVIEW,
        ]);
        Mail::assertQueued(IntakeReviewMail::class, $activeManagerEmails->count() + 1);
        Mail::assertQueued(IntakeReviewMail::class, function (IntakeReviewMail $mail) use ($ofw): bool {
            $this->assertSame('New case awaiting review', $mail->envelope()->subject);
            $this->assertStringContainsString('being reviewed by a case manager', strtolower(strip_tags($mail->render())));

            return $mail->hasTo($ofw->email);
        });
        foreach ($activeManagerEmails as $email) {
            Mail::assertQueued(IntakeReviewMail::class, fn (IntakeReviewMail $mail) => $mail->hasTo($email));
        }
        Mail::assertQueued(IntakeReviewMail::class, fn (IntakeReviewMail $mail) => ! $mail->hasTo($inactiveManager->email)
            && ! $mail->hasTo($admin->email)
            && ! $mail->hasTo($agency->email)
        );
    }

    public function test_invalid_intake_does_not_send_review_email(): void
    {
        Mail::fake();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $payload = $this->payload();
        $payload['summary'] = '';

        $this->actingAsWithMfa($ofw)->post(route('ofw.intake.store'), $payload)->assertSessionHasErrors('summary');
        Mail::assertNothingQueued();
    }

    public function test_intake_rejects_an_empty_vulnerability_status_without_sending_mail(): void
    {
        Mail::fake();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $payload = $this->payload();
        $payload['vulnerability_indicator'] = '';

        $this->actingAsWithMfa($ofw)->post(route('ofw.intake.store'), $payload)
            ->assertSessionHasErrors('vulnerability_indicator');
        $this->assertSame(0, CaseFile::count());
        Mail::assertNothingQueued();
    }

    public function test_intake_without_linked_client_sends_review_mail_using_case_email(): void
    {
        Storage::fake('object-storage');
        Mail::fake();
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);

        $this->actingAsWithMfa($ofw)->post(route('ofw.intake.store'), $this->payload())
            ->assertRedirect(route('ofw.dashboard'));

        Mail::assertQueued(IntakeReviewMail::class, fn (IntakeReviewMail $mail) => $mail->hasTo($ofw->email));
    }
}
