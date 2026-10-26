<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailAvailabilityEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_taken_email_and_mirrors_the_soft_deleted_semantics_of_the_review_check(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        User::factory()->create(['email' => 'softdeleted@example.com'])->delete();

        $this->getJson('/api/register/email-availability?email=taken@example.com')
            ->assertOk()
            ->assertJson(['taken' => true]);

        // Trashed accounts block re-registration, same as the review-time check.
        $this->getJson('/api/register/email-availability?email=softdeleted@example.com')
            ->assertOk()
            ->assertJson(['taken' => true]);

        $this->getJson('/api/register/email-availability?email=free@example.com')
            ->assertOk()
            ->assertJson(['taken' => false]);
    }

    public function test_rejects_invalid_email_with_validation_error(): void
    {
        $this->getJson('/api/register/email-availability?email=not-an-email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
