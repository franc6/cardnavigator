<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds User model instances with realistic name, email, and pre-hashed password values for tests and seeders.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Cached hash of the literal string "password", shared across all generated users in a test run
     * so that Hash::make() runs only once per process — bcrypt is intentionally slow.
     *
     * @var string|null
     */
    protected static ?string $password;

    /**
     * Default attribute values for a generated User.
     *
     * @return array<string, mixed> Randomized name and email, verified-now timestamp, the cached hash, and a random remember_token.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * State modifier: leave the email address unverified.
     *
     * @return static The factory in the "unverified" state.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * State modifier: grant admin privileges to the generated user.
     *
     * @return static The factory in the "admin" state.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
