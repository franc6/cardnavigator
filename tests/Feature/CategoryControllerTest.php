<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('A category update request returns 404 when the place ID contains illegal characters')]
    #[DataProvider('invalidPlaceIdProvider')]
    public function test_update_returns_404_for_invalid_place_id_format(string $placeId): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['friendly_name' => 'Dining']);

        // Act
        $response = $this->actingAs($user)->post(route('places.category.update', $placeId), [
            'friendly_name' => 'Dining',
        ]);

        // Assert
        $response->assertNotFound();
    }

    public static function invalidPlaceIdProvider(): array
    {
        return [
            'path traversal' => ['../../etc/passwd'],
            'slash in id' => ['abc/def'],
            'space in id' => ['abc def'],
        ];
    }

    #[TestDox('Guests cannot post to the update category endpoint')]
    public function test_update_requires_authentication(): void
    {
        // Arrange
        $placeId = 'ChIJ_test_place';

        // Act
        $response = $this->post(route('places.category.update', $placeId), [
            'friendly_name' => 'Dining',
        ]);

        // Assert
        $response->assertRedirect(route('login'));
    }

    #[TestDox('Updating a category saves the friendly name and redirects to the place')]
    public function test_update_saves_and_redirects(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_test_place';
        Cache::put("place_detail_{$placeId}", ['api_category' => 'restaurant', 'name' => 'Test Place']);
        Category::factory()->create(['name' => 'dining_generic', 'friendly_name' => 'Dining']);

        // Act
        $response = $this->actingAs($user)->post(route('places.category.update', $placeId), [
            'friendly_name' => 'Dining',
        ]);

        // Assert
        $this->assertDatabaseHas('categories', ['name' => 'restaurant', 'friendly_name' => 'Dining']);
        $response->assertRedirect(route('places.show', $placeId));
    }

    #[TestDox('Updating a category returns 404 when the place is not cached')]
    public function test_update_returns_404_when_place_not_cached(): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'dining_generic', 'friendly_name' => 'Dining']);

        // Act
        $response = $this->actingAs($user)->post(route('places.category.update', 'nonexistent'), [
            'friendly_name' => 'Dining',
        ]);

        // Assert
        $response->assertNotFound();
    }

    #[TestDox('Updating a category migrates existing percentage rows when the friendly name changes')]
    public function test_update_migrates_percentages_on_name_change(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();
        $placeId = 'ChIJ_test_place';
        Cache::put("place_detail_{$placeId}", ['api_category' => 'restaurant', 'name' => 'Test Place']);
        Category::factory()->create(['name' => 'restaurant',   'friendly_name' => 'OldName']);
        Category::factory()->create(['name' => 'dining_generic', 'friendly_name' => 'Dining']);
        $percentage = Percentage::factory()->create(['card_id' => $card->id, 'category' => 'OldName', 'percentage' => 2]);

        // Act
        $this->actingAs($user)->post(route('places.category.update', $placeId), [
            'friendly_name' => 'Dining',
        ]);

        // Assert
        $this->assertDatabaseHas('percentages', ['id' => $percentage->id, 'category' => 'Dining']);
        $this->assertDatabaseMissing('percentages', ['category' => 'OldName']);
    }

    #[TestDox('Updating a category rejects an empty friendly name')]
    public function test_update_rejects_empty_friendly_name(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_test_place';
        Cache::put("place_detail_{$placeId}", ['api_category' => 'restaurant', 'name' => 'Test Place']);

        // Act
        $response = $this->actingAs($user)->post(route('places.category.update', $placeId), [
            'friendly_name' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors('friendly_name');
    }

    #[TestDox('Updating a category rejects a friendly name not present in the categories table')]
    public function test_update_rejects_unknown_friendly_name(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_test_place';
        Cache::put("place_detail_{$placeId}", ['api_category' => 'restaurant', 'name' => 'Test Place']);

        // Act
        $response = $this->actingAs($user)->post(route('places.category.update', $placeId), [
            'friendly_name' => 'NonExistentCategory',
        ]);

        // Assert
        $response->assertSessionHasErrors('friendly_name');
    }
}
