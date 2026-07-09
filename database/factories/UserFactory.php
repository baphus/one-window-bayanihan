<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('P@ssw0rd!'),
            'role' => 'CASE_MANAGER',
            'contact_number' => fake()->phoneNumber(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Seed MFA enrollment so the CheckMfaEnrolled middleware doesn't
     * redirect ADMIN users away from the page under test.
     *
     * The mfa_secret is auto-encrypted by the model's 'encrypted' cast,
     * and recovery codes are stored as HMAC-SHA256 hashes.
     */
    public function mfaEnabled(): static
    {
        $key = config('app.key');
        $plainCodes = ['ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX', 'YZ12-3456-7890'];

        return $this->state(fn (array $attributes) => [
            'mfa_secret' => 'A3B4C5D6E7F8G9H0I1J2K3L4M5N6O7P8',
            'mfa_recovery_codes' => array_map(
                fn ($code) => hash_hmac('sha256', $code, $key),
                $plainCodes,
            ),
            'mfa_enabled_at' => now(),
        ]);
    }
}
