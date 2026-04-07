<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class PercentageControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    #[TestDox('Guests are redirected away from the percentages index')]
    public function test_percentages_index_requires_authentication(): void
    {
        // Arrange — no authenticated user.

        // Act
        $response = $this->get(route('percentages.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    #[TestDox('An authenticated user can view the percentages index')]
    public function test_percentages_index_is_accessible_to_authenticated_users(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('percentages.index'));

        // Assert
        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // Index data
    // -------------------------------------------------------------------------

    #[TestDox('The percentages index passes cards ordered by preference to the view')]
    public function test_percentages_index_passes_cards_ordered_by_preference(): void
    {
        // Arrange
        $user = User::factory()->create();
        $second = Card::factory()->create(['preference' => 2]);
        $first = Card::factory()->create(['preference' => 1]);

        // Act
        $response = $this->actingAs($user)->get(route('percentages.index'));

        // Assert
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertEquals([$first->id, $second->id], $cards->pluck('id')->all());
    }

    #[TestDox('The percentages index passes distinct category friendly names in alphabetical order')]
    public function test_percentages_index_passes_sorted_categories(): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['friendly_name' => 'Gas']);
        Category::factory()->create(['friendly_name' => 'Dining']);
        Category::factory()->create(['friendly_name' => 'Dining']); // duplicate

        // Act
        $response = $this->actingAs($user)->get(route('percentages.index'));

        // Assert
        $categories = $response->viewData('categories');
        $this->assertEquals(['Dining', 'Gas'], $categories->all());
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    #[TestDox('Posting valid percentages updates all matching rows and redirects')]
    public function test_update_saves_percentages_and_redirects(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();
        Category::factory()->create(['friendly_name' => 'Dining']);
        $percentage = Percentage::factory()->create([
            'card_id' => $card->id,
            'category' => 'Dining',
            'percentage' => 1,
        ]);

        // Act
        $response = $this->actingAs($user)->post(route('percentages.update'), [
            'percentages' => [
                'Dining' => [$card->id => 5],
            ],
        ]);

        // Assert
        $response->assertRedirect(route('percentages.index'));
        $this->assertDatabaseHas('percentages', ['id' => $percentage->id, 'percentage' => 5]);
    }

    #[TestDox('Update rejects invalid percentage values')]
    #[DataProvider('invalidPercentageDataProvider')]
    public function test_update_validation(mixed $value, string $errorKey): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();
        Category::factory()->create(['friendly_name' => 'Dining']);

        // Act
        $response = $this->actingAs($user)->post(route('percentages.update'), [
            'percentages' => [
                'Dining' => [$card->id => $value],
            ],
        ]);

        // Assert
        $response->assertSessionHasErrors($errorKey);
    }

    #[TestDox('Posting percentages does not affect rows for other cards or categories')]
    public function test_update_does_not_affect_unrelated_rows(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();
        $other = Card::factory()->create();
        Category::factory()->create(['friendly_name' => 'Dining']);
        Percentage::factory()->create(['card_id' => $card->id,  'category' => 'Dining', 'percentage' => 1]);
        $unrelated = Percentage::factory()->create(['card_id' => $other->id, 'category' => 'Dining', 'percentage' => 3]);

        // Act
        $this->actingAs($user)->post(route('percentages.update'), [
            'percentages' => [
                'Dining' => [$card->id => 5],
            ],
        ]);

        // Assert
        $this->assertDatabaseHas('percentages', ['id' => $unrelated->id, 'percentage' => 3]);
    }

    // -------------------------------------------------------------------------
    // Key validation
    // -------------------------------------------------------------------------

    #[TestDox('Update rejects a category key that does not exist in the categories table')]
    public function test_update_rejects_unknown_category_key(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('percentages.update'), [
            'percentages' => [
                'Phantom' => [$card->id => 5],
            ],
        ]);

        // Assert
        $response->assertSessionHasErrors('categories.0');
    }

    #[TestDox('Update rejects a card ID key that does not exist in the cards table')]
    public function test_update_rejects_unknown_card_id_key(): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['friendly_name' => 'Dining']);

        // Act
        $response = $this->actingAs($user)->post(route('percentages.update'), [
            'percentages' => [
                'Dining' => [99999 => 5],
            ],
        ]);

        // Assert
        $response->assertSessionHasErrors('card_ids.0');
    }

    // -------------------------------------------------------------------------
    // Data providers
    // -------------------------------------------------------------------------

    public static function invalidPercentageDataProvider(): array
    {
        return [
            'value is missing' => ['',    'percentages.Dining.*'],
            'value is negative' => [-1,    'percentages.Dining.*'],
            'value exceeds max' => [256,   'percentages.Dining.*'],
            'value is not an integer' => ['abc', 'percentages.Dining.*'],
        ];
    }
}
