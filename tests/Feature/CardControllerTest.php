<?php

namespace Tests\Feature;

use App\Exceptions\UnsupportedImageFormatException;
use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use App\Models\User;
use App\Services\CardImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a real, in-memory PNG of the given dimensions for upload tests.
     */
    private static function pngBytes(int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 80, 130, 200));
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();

        return $bytes;
    }

    private static function uploadedPng(int $width, int $height, string $filename = 'card.png'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cardimg');
        file_put_contents($tmp, self::pngBytes($width, $height));

        return new UploadedFile($tmp, $filename, 'image/png', null, true);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    #[TestDox('Guests are redirected away from the cards index')]
    public function test_cards_index_requires_authentication(): void
    {
        // Arrange — no authenticated user.

        // Act
        $response = $this->get(route('cards.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    #[TestDox('An authenticated user can view the cards index')]
    public function test_cards_index_is_accessible_to_authenticated_users(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('cards.index'));

        // Assert
        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // Index ordering
    // -------------------------------------------------------------------------

    #[TestDox('Cards are listed in ascending preference order')]
    public function test_cards_are_ordered_by_preference(): void
    {
        // Arrange
        $user = User::factory()->create();
        $third = Card::factory()->create(['preference' => 3]);
        $first = Card::factory()->create(['preference' => 1]);
        $second = Card::factory()->create(['preference' => 2]);

        // Act
        $response = $this->actingAs($user)->get(route('cards.index'));

        // Assert
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertEquals([$first->id, $second->id, $third->id], $cards->pluck('id')->all());
    }

    #[TestDox('The Add Card form defaults the preference input to the number of existing cards displayed')]
    public function test_add_card_form_defaults_preference_to_existing_card_count(): void
    {
        // Arrange
        $user = User::factory()->create();
        Card::factory()->count(3)->create();

        // Act
        $response = $this->actingAs($user)->get(route('cards.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('id="new_pref"', false);
        $response->assertSee('value="3"', false);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    #[TestDox('A valid POST request creates a card and redirects to the percentages index')]
    public function test_store_creates_card_and_redirects_to_percentages(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'New Card',
            'foreign_transaction_fee' => 3,
            'preference' => 0,
        ]);

        // Assert
        $response->assertRedirect(route('percentages.index'));
        $this->assertDatabaseHas('cards', ['name' => 'New Card']);
    }

    #[TestDox('Adding a card creates one percentage row per distinct category friendly name')]
    public function test_store_creates_percentage_rows_for_all_categories(): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['friendly_name' => 'Dining']);
        Category::factory()->create(['friendly_name' => 'Gas']);
        Category::factory()->create(['friendly_name' => 'Dining']); // duplicate — must not produce a duplicate row

        // Act
        $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'New Card',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
        ]);

        // Assert
        $card = Card::where('name', 'New Card')->firstOrFail();
        $this->assertDatabaseHas('percentages', ['card_id' => $card->id, 'category' => 'Dining']);
        $this->assertDatabaseHas('percentages', ['card_id' => $card->id, 'category' => 'Gas']);
        $this->assertSame(2, Percentage::where('card_id', $card->id)->count());
    }

    #[TestDox('Store rejects invalid input')]
    #[DataProvider('invalidCardDataProvider')]
    public function test_store_validation(array $overrides, string $errorField): void
    {
        // Arrange
        $user = User::factory()->create();
        Card::factory()->create(['name' => 'Taken']);
        $payload = array_merge([
            'name' => 'Valid Name',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
        ], $overrides);

        // Act
        $response = $this->actingAs($user)->post(route('cards.store'), $payload);

        // Assert
        $response->assertSessionHasErrors($errorField);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    #[TestDox('A PATCH request updates all editable card fields')]
    public function test_update_modifies_card_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create(['name' => 'Old', 'foreign_transaction_fee' => 0, 'preference' => 1]);

        // Act
        $response = $this->actingAs($user)->patch(route('cards.update', $card), [
            'name' => 'Updated',
            'foreign_transaction_fee' => 3,
            'preference' => 5,
        ]);

        // Assert
        $response->assertRedirect(route('cards.index'));
        $card->refresh();
        $this->assertSame('Updated', $card->name);
        $this->assertSame(3, $card->foreign_transaction_fee);
        $this->assertSame(5, $card->preference);
    }

    #[TestDox('A card can be saved with its own existing name without triggering the unique rule')]
    public function test_update_allows_same_name_on_same_card(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create(['name' => 'My Card']);

        // Act
        $response = $this->actingAs($user)->patch(route('cards.update', $card), [
            'name' => 'My Card',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
        ]);

        // Assert
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cards.index'));
    }

    #[TestDox('Update rejects invalid input')]
    #[DataProvider('invalidCardDataProvider')]
    public function test_update_validation(array $overrides, string $errorField): void
    {
        // Arrange
        $user = User::factory()->create();
        Card::factory()->create(['name' => 'Taken']);
        $card = Card::factory()->create();
        $payload = array_merge([
            'name' => 'Valid Name',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
        ], $overrides);

        // Act
        $response = $this->actingAs($user)->patch(route('cards.update', $card), $payload);

        // Assert
        $response->assertSessionHasErrors($errorField);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    #[TestDox('Deleting a card removes it from the database and redirects to the cards index')]
    public function test_destroy_deletes_card_and_redirects(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();

        // Act
        $response = $this->actingAs($user)->delete(route('cards.destroy', $card));

        // Assert
        $response->assertRedirect(route('cards.index'));
        $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    }

    #[TestDox('Deleting a card also removes its associated percentage rows')]
    public function test_destroy_cascades_to_percentages(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create();
        $percentage = Percentage::factory()->create(['card_id' => $card->id]);

        // Act
        $this->actingAs($user)->delete(route('cards.destroy', $card));

        // Assert
        $this->assertDatabaseMissing('percentages', ['id' => $percentage->id]);
    }

    // -------------------------------------------------------------------------
    // Image endpoint
    // -------------------------------------------------------------------------

    #[TestDox('The image endpoint returns 404 when the card has no image')]
    public function test_image_returns_404_when_no_image_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create(['image_data' => null]);

        // Act
        $response = $this->actingAs($user)->get(route('cards.image', $card));

        // Assert
        $response->assertNotFound();
    }

    #[TestDox('The image endpoint serves a known-safe MIME type unchanged')]
    public function test_image_serves_allowed_mime_type(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create([
            'image_data' => base64_encode('fake-image-bytes'),
            'image_mime' => 'image/jpeg',
        ]);

        // Act
        $response = $this->actingAs($user)->get(route('cards.image', $card));

        // Assert
        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    #[TestDox('The image endpoint falls back to image/png when the stored MIME is not on the allowlist')]
    #[DataProvider('disallowedMimeProvider')]
    public function test_image_falls_back_to_png_for_disallowed_mime(string $mime): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create([
            'image_data' => base64_encode('fake-image-bytes'),
            'image_mime' => $mime,
        ]);

        // Act
        $response = $this->actingAs($user)->get(route('cards.image', $card));

        // Assert
        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public static function disallowedMimeProvider(): array
    {
        return [
            'text/html' => ['text/html'],
            'application/javascript' => ['application/javascript'],
            'null stored' => [''],
        ];
    }

    // -------------------------------------------------------------------------
    // Data providers
    // -------------------------------------------------------------------------

    public static function invalidCardDataProvider(): array
    {
        return [
            'name is missing' => [['name' => ''],                      'name'],
            'name exceeds max length' => [['name' => str_repeat('a', 256)],    'name'],
            'name is already taken' => [['name' => 'Taken'],                 'name'],
            'foreign_transaction_fee is missing' => [['foreign_transaction_fee' => ''],   'foreign_transaction_fee'],
            'foreign_transaction_fee is negative' => [['foreign_transaction_fee' => -1],   'foreign_transaction_fee'],
            'foreign_transaction_fee exceeds max' => [['foreign_transaction_fee' => 101],  'foreign_transaction_fee'],
            'preference is missing' => [['preference' => ''],                'preference'],
            'preference is negative' => [['preference' => -1],                'preference'],
            'preference exceeds max' => [['preference' => 256],               'preference'],
        ];
    }

    // -------------------------------------------------------------------------
    // Image upload, image URL, and color
    // -------------------------------------------------------------------------

    #[TestDox('An uploaded PNG is detected, resized, and persisted as base64 with the correct MIME')]
    public function test_store_persists_uploaded_image(): void
    {
        // Arrange
        $user = User::factory()->create();
        $file = self::uploadedPng(1000, 600);

        // Act
        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'With Image',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
            'image_file' => $file,
        ]);

        // Assert
        $response->assertRedirect(route('percentages.index'));
        $card = Card::where('name', 'With Image')->firstOrFail();
        $this->assertSame('image/png', $card->image_mime);
        $info = getimagesizefromstring(base64_decode($card->image_data));
        $this->assertSame(400, $info[0]);
        $this->assertSame(240, $info[1]);
    }

    #[TestDox('A valid hex color is persisted on the new card')]
    public function test_store_persists_color(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'Colorful Card',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
            'color' => '#abcdef',
        ]);

        // Assert
        $card = Card::where('name', 'Colorful Card')->firstOrFail();
        $this->assertSame('#abcdef', $card->color);
    }

    #[TestDox('Invalid color hex strings are rejected')]
    #[DataProvider('invalidColorProvider')]
    public function test_store_rejects_invalid_color(string $color): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'Bad Color',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
            'color' => $color,
        ]);

        // Assert
        $response->assertSessionHasErrors('color');
    }

    public static function invalidColorProvider(): array
    {
        return [
            'missing leading hash' => ['ABCDEF'],
            'short form' => ['#abc'],
            'eight chars' => ['#abcdef12'],
            'non-hex character' => ['#zzzzzz'],
        ];
    }

    #[TestDox('An unsupported image upload is surfaced as an image_file validation error')]
    public function test_store_rejects_unsupported_image_with_validation_error(): void
    {
        // Arrange
        $user = User::factory()->create();
        $message = 'This image format is not supported on this server.';
        $this->mock(CardImageService::class, function ($mock) use ($message) {
            $mock->shouldReceive('fromUpload')
                ->once()
                ->andThrow(new UnsupportedImageFormatException($message));
        });

        // Act
        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'Bad Upload',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
            'image_file' => self::uploadedPng(100, 100),
        ]);

        // Assert
        $response->assertSessionHasErrors(['image_file' => $message]);
        $this->assertDatabaseMissing('cards', ['name' => 'Bad Upload']);
    }

    #[TestDox('Updating a card with a new image replaces the stored bytes')]
    public function test_update_replaces_image(): void
    {
        // Arrange
        $user = User::factory()->create();
        $card = Card::factory()->create([
            'name' => 'Old',
            'image_data' => base64_encode('old-bytes'),
            'image_mime' => 'image/png',
        ]);

        // Act
        $response = $this->actingAs($user)->patch(route('cards.update', $card), [
            'name' => 'Old',
            'foreign_transaction_fee' => 0,
            'preference' => 0,
            'image_file' => self::uploadedPng(500, 500),
        ]);

        // Assert
        $response->assertRedirect(route('cards.index'));
        $card->refresh();
        $this->assertNotSame(base64_encode('old-bytes'), $card->image_data);
        $this->assertSame('image/png', $card->image_mime);
        $info = getimagesizefromstring(base64_decode($card->image_data));
        $this->assertSame(400, $info[0]);
    }
}
