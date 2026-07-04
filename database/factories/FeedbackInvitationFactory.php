<?php

namespace Database\Factories;

use App\Helpers\DefaultServqualQuestions;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\FeedbackInvitation;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FeedbackInvitationFactory extends Factory
{
    protected $model = FeedbackInvitation::class;

    public function definition(): array
    {
        $questions = DefaultServqualQuestions::get();
        $fullToken = Str::random(40);

        return [
            'case_id' => CaseFile::factory(),
            'agency_id' => Agency::factory(),
            'referral_id' => Referral::factory(),
            'client_email' => $this->faker->safeEmail(),
            'token_prefix' => substr($fullToken, 0, 10),
            'token_hash' => hash('sha256', $fullToken),
            'service_name' => $this->faker->company.' Service',
            'snapshot_source' => 'agency_active_form',
            'form_snapshot' => $questions,
            'rating_labels' => FeedbackInvitation::RATING_LABELS,
            'expires_at' => now()->addDays(7),
            'submitted_at' => null,
            'used_feedback_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
        ]);
    }

    public function withToken(string $token): static
    {
        return $this->state([
            'token_prefix' => substr($token, 0, 10),
            'token_hash' => hash('sha256', $token),
        ]);
    }
}
