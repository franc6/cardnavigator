<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Percentage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds Percentage model instances tied to a randomly generated Card and category for tests and seeders.
 *
 * @extends Factory<Percentage>
 */
class PercentageFactory extends Factory
{
    /**
     * Default attribute values for a generated Percentage.
     *
     * @return array<string, mixed> A new Card via factory, a random category, and a percentage 1-5.
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'category' => fake()->unique()->word(),
            'percentage' => fake()->numberBetween(1, 5),
        ];
    }
}
