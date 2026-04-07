<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds Category model instances with randomized API type and friendly name values for tests and seeders.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Default attribute values for a generated Category.
     *
     * @return array<string, mixed> Randomized name (Places API type) and friendly_name.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'friendly_name' => fake()->unique()->word(),
        ];
    }
}
