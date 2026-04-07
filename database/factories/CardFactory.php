<?php

namespace Database\Factories;

use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds Card model instances with realistic, randomized attribute values for tests and seeders.
 *
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Default attribute values for a generated Card.
     *
     * @return array<string, mixed> Randomized name, foreign_transaction_fee, and preference.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'foreign_transaction_fee' => fake()->numberBetween(0, 3),
            'preference' => fake()->numberBetween(0, 10),
        ];
    }
}
