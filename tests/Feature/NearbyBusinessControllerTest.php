<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use App\Models\User;
use App\Services\NearbyPlacesService;
use Exception;
use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class NearbyBusinessControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index — access control
    // -------------------------------------------------------------------------

    #[TestDox('Guests are redirected away from the dashboard')]
    public function test_dashboard_requires_authentication(): void
    {
        // Arrange — no authenticated user.

        // Act
        $response = $this->get(route('dashboard'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    #[TestDox('An authenticated user can view the dashboard')]
    public function test_dashboard_is_accessible_to_authenticated_users(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Assert
        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // index — without coordinates
    // -------------------------------------------------------------------------

    #[TestDox('The dashboard renders with empty results when no coordinates are provided')]
    public function test_dashboard_shows_empty_results_without_coordinates(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Assert
        $response->assertOk();
        $this->assertEmpty($response->viewData('results'));
        $this->assertNull($response->viewData('search'));
    }

    #[TestDox('The dashboard always shows the Find Nearby Businesses button')]
    #[DataProvider('locateButtonScenarioProvider')]
    public function test_dashboard_always_shows_locate_button(array $params): void
    {
        // Arrange
        $user = User::factory()->create();
        Cache::flush();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', $params));

        // Assert
        $response->assertOk();
        $response->assertSee(__('Find Nearby Businesses'));
        $this->assertStringNotContainsString('hidden text-center py-4', $response->getContent());
    }

    public static function locateButtonScenarioProvider(): array
    {
        return [
            'no coordinates' => [[]],
            'with coordinates' => [['latitude' => 40.7128, 'longitude' => -74.0060]],
        ];
    }

    // -------------------------------------------------------------------------
    // index — with coordinates (non-production uses fake data)
    // -------------------------------------------------------------------------

    #[TestDox('Providing coordinates triggers a fake-data search and returns results')]
    public function test_dashboard_returns_results_when_coordinates_provided(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cache::flush();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]));

        // Assert
        $response->assertOk();
        $this->assertNotEmpty($response->viewData('results'));
        $this->assertNotNull($response->viewData('search'));
    }

    #[TestDox('Each result contains the expected array keys')]
    public function test_dashboard_results_contain_expected_keys(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cache::flush();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]));

        // Assert
        $results = $response->viewData('results');
        foreach ($results as $result) {
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('api_category', $result);
            $this->assertArrayHasKey('recommended_card', $result);
            $this->assertArrayHasKey('recommended_percentage', $result);
            $this->assertArrayHasKey('is_default', $result);
            $this->assertArrayHasKey('needs_setup', $result);
        }
    }

    #[TestDox('The recommended card is the one with the highest cashback percentage')]
    public function test_dashboard_recommends_card_with_highest_percentage(): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);
        $low = Card::factory()->create(['preference' => 0]);
        $high = Card::factory()->create(['preference' => 1]);
        Percentage::factory()->create(['card_id' => $low->id,  'category' => 'Dining', 'percentage' => 1]);
        Percentage::factory()->create(['card_id' => $high->id, 'category' => 'Dining', 'percentage' => 5]);
        Cache::flush();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]));

        // Assert
        $dining = collect($response->viewData('results'))->firstWhere('api_category', 'restaurant');
        $this->assertSame($high->name, $dining['recommended_card']);
    }

    #[TestDox('When percentages are tied, the card with the lower preference value wins')]
    public function test_dashboard_breaks_percentage_tie_by_preference(): void
    {
        // Arrange
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);
        $preferred = Card::factory()->create(['preference' => 1]);
        $lessPreferred = Card::factory()->create(['preference' => 2]);
        Percentage::factory()->create(['card_id' => $preferred->id,     'category' => 'Dining', 'percentage' => 3]);
        Percentage::factory()->create(['card_id' => $lessPreferred->id, 'category' => 'Dining', 'percentage' => 3]);
        Cache::flush();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]));

        // Assert
        $dining = collect($response->viewData('results'))->firstWhere('api_category', 'restaurant');
        $this->assertSame($preferred->name, $dining['recommended_card']);
    }

    #[TestDox('Results are served from the cache on repeated requests for the same coordinates')]
    public function test_dashboard_caches_results(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cache::flush();
        $lat = 1.0;
        $lon = 2.0;
        $params = ['latitude' => $lat, 'longitude' => $lon];

        // Mirror the service's grid-snapping so the expected key matches exactly.
        $latGrid = 0.0005;
        $snappedLat = round($lat / $latGrid) * $latGrid;
        $lonGrid = $latGrid / cos(deg2rad($snappedLat));
        $snappedLon = round($lon / $lonGrid) * $lonGrid;
        $cacheKey = "nearby_businesses_{$snappedLat}_{$snappedLon}";

        // Act — first request populates the cache.
        $this->actingAs($user)->get(route('dashboard', $params));

        // Assert
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[TestDox('A repeated search with the same coordinates exposes the snapped cached location to the view')]
    public function test_dashboard_exposes_cached_location_on_repeated_search(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cache::flush();
        $params = ['latitude' => 40.7128, 'longitude' => -74.0060];

        // Act — first request populates the cache; second request should see it.
        $this->actingAs($user)->get(route('dashboard', $params));
        $response = $this->actingAs($user)->get(route('dashboard', $params));

        // Assert
        $response->assertOk();
        $cached = $response->viewData('cachedLocation');
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('latitude', $cached);
        $this->assertArrayHasKey('longitude', $cached);
        $response->assertSee(__('Cached location'));
    }

    #[TestDox('Geolocation jitter inside a single grid cell still hits the cache')]
    public function test_dashboard_cache_survives_geolocation_jitter(): void
    {
        // Arrange — two coordinates close enough to fall in the same ~55 m grid cell.
        $user = User::factory()->create();
        Cache::flush();

        // Act — first request populates the cache; second request uses jittered coords.
        $this->actingAs($user)->get(route('dashboard', ['latitude' => 40.7128, 'longitude' => -74.0060]));
        $response = $this->actingAs($user)->get(route('dashboard', ['latitude' => 40.71281, 'longitude' => -74.00601]));

        // Assert
        $response->assertOk();
        $this->assertNotNull($response->viewData('cachedLocation'));
    }

    // -------------------------------------------------------------------------
    // index — validation
    // -------------------------------------------------------------------------

    #[TestDox('Non-numeric coordinates are rejected with validation errors')]
    #[DataProvider('invalidCoordinateProvider')]
    public function test_dashboard_rejects_non_numeric_coordinates(array $params): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', $params));

        // Assert
        $response->assertSessionHasErrors();
    }

    // -------------------------------------------------------------------------
    // search
    // -------------------------------------------------------------------------

    #[TestDox('Guests cannot post to the search endpoint')]
    public function test_search_requires_authentication(): void
    {
        // Arrange — no authenticated user.

        // Act
        $response = $this->post(route('dashboard.search'), [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        // Assert
        $response->assertRedirect(route('login'));
    }

    #[TestDox('A valid search post redirects to the dashboard with the coordinates appended')]
    public function test_search_redirects_to_dashboard_with_coordinates(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('dashboard.search'), [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        // Assert
        $response->assertRedirect(route('dashboard', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]));
    }

    #[TestDox('Search rejects invalid coordinate inputs')]
    #[DataProvider('invalidCoordinateProvider')]
    public function test_search_validation(array $params): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('dashboard.search'), $params);

        // Assert
        $response->assertSessionHasErrors();
    }

    // -------------------------------------------------------------------------
    // places.show — modal data
    // -------------------------------------------------------------------------

    #[TestDox('The place detail page passes friendly names and current category to the view for the modal')]
    public function test_place_show_passes_category_modal_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_test_place';
        Cache::put("place_detail_{$placeId}", [
            'api_category' => 'restaurant',
            'friendly_category' => 'Dining',
            'name' => 'Test Place',
            'address' => '1 Main St',
            'is_outside_us' => false,
            'recommended_card' => null,
            'is_default' => false,
            'needs_setup' => false,
        ]);
        Category::factory()->create(['name' => 'restaurant',   'friendly_name' => 'Dining']);
        Category::factory()->create(['name' => 'grocery_store', 'friendly_name' => 'Grocery']);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert
        $response->assertViewHas('currentFriendlyName', 'Dining');
        $response->assertViewHas('friendlyNames');
        $this->assertCount(2, $response->viewData('friendlyNames'));
    }

    // -------------------------------------------------------------------------
    // show — place detail page
    // -------------------------------------------------------------------------

    #[TestDox('Guests are redirected away from the place detail page')]
    public function test_place_show_requires_authentication(): void
    {
        // Arrange — no authenticated user.

        // Act
        $response = $this->get(route('places.show', 'some-id'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    #[TestDox('A place detail request returns 404 when the place ID contains illegal characters')]
    #[DataProvider('invalidPlaceIdProvider')]
    public function test_place_show_returns_404_for_invalid_place_id_format(string $placeId): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert
        $response->assertNotFound();
    }

    #[TestDox('A place detail request returns 404 when the place is not in the cache')]
    public function test_place_show_returns_404_when_not_cached(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cache::forget('place_detail_nonexistent');

        // Act
        $response = $this->actingAs($user)->get(route('places.show', 'nonexistent'));

        // Assert
        $response->assertNotFound();
    }

    #[TestDox('The place detail page renders name, address, category, and API type')]
    public function test_place_show_renders_place_details(): void
    {
        // Arrange
        $faker = Factory::create();
        $user = User::factory()->create();
        $placeId = $faker->uuid;
        $place = [
            'place_id' => $placeId,
            'name' => $faker->company,
            'address' => $faker->address . ', USA',
            'api_category' => 'restaurant',
            'friendly_category' => 'Dining',
            'is_outside_us' => false,
            'recommended_card' => null,
            'recommended_percentage' => null,
            'is_default' => false,
            'needs_setup' => false,
        ];
        Cache::put("place_detail_{$placeId}", $place, 60);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert
        $response->assertOk();
        $response->assertSee($place['name']);
        $response->assertSee($place['address']);
        $response->assertSee('Dining');
        $response->assertSee('restaurant');
    }

    #[TestDox('The place detail page lists cards sorted by percentage descending for US places')]
    public function test_place_show_sorts_cards_by_percentage_descending(): void
    {
        // Arrange
        $faker = Factory::create();
        $fakerUs = Factory::create('en_US');
        $user = User::factory()->create();
        $cardLow = Card::factory()->create(['name' => $faker->unique()->word . ' Low',  'preference' => 1, 'foreign_transaction_fee' => 0]);
        $cardHigh = Card::factory()->create(['name' => $faker->unique()->word . ' High', 'preference' => 2, 'foreign_transaction_fee' => 0]);
        Percentage::factory()->create(['card_id' => $cardLow->id,  'category' => 'Dining', 'percentage' => 1]);
        Percentage::factory()->create(['card_id' => $cardHigh->id, 'category' => 'Dining', 'percentage' => 5]);
        $placeId = $faker->uuid;
        Cache::put("place_detail_{$placeId}", [
            'place_id' => $placeId,
            'name' => $faker->company,
            'address' => $fakerUs->address . ', USA',
            'api_category' => 'restaurant',
            'friendly_category' => 'Dining',
            'is_outside_us' => false,
            'recommended_card' => $cardHigh->name,
            'recommended_percentage' => 5,
            'is_default' => false,
            'needs_setup' => false,
        ], 60);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert — high-percentage card must appear before low-percentage card
        $content = $response->getContent();
        $response->assertOk();
        $this->assertLessThan(strpos($content, $cardLow->name), strpos($content, $cardHigh->name));
    }

    #[TestDox('For non-US places cards with a foreign transaction fee appear after no-FTF cards')]
    public function test_place_show_ftf_cards_appear_last_for_non_us_places(): void
    {
        // Arrange
        $faker = Factory::create();
        $fakerFr = Factory::create('fr_FR');
        $user = User::factory()->create();
        // FTF card has a higher percentage but should still be pushed to the bottom
        $noFtf = Card::factory()->create(['name' => $faker->unique()->word . ' NoFTF',  'preference' => 2, 'foreign_transaction_fee' => 0]);
        $hasFtf = Card::factory()->create(['name' => $faker->unique()->word . ' HasFTF', 'preference' => 1, 'foreign_transaction_fee' => 3]);
        Percentage::factory()->create(['card_id' => $noFtf->id,  'category' => 'Dining', 'percentage' => 1]);
        Percentage::factory()->create(['card_id' => $hasFtf->id, 'category' => 'Dining', 'percentage' => 5]);
        $placeId = $faker->uuid;
        Cache::put("place_detail_{$placeId}", [
            'place_id' => $placeId,
            'name' => $faker->company,
            'address' => $fakerFr->address,
            'api_category' => 'restaurant',
            'friendly_category' => 'Dining',
            'is_outside_us' => true,
            'recommended_card' => $noFtf->name,
            'recommended_percentage' => 1,
            'is_default' => false,
            'needs_setup' => false,
        ], 60);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert — no-FTF card must appear before the FTF card despite lower percentage
        $content = $response->getContent();
        $response->assertOk();
        $this->assertLessThan(strpos($content, $hasFtf->name), strpos($content, $noFtf->name));
    }

    // -------------------------------------------------------------------------
    // index — service-thrown errors surface as a google_places error
    // -------------------------------------------------------------------------

    #[TestDox('A service exception is surfaced to the dashboard as a google_places error and cards are still loaded')]
    public function test_dashboard_surfaces_google_places_error_when_service_throws(): void
    {
        // Arrange
        $user = User::factory()->create();
        Card::factory()->create(['name' => 'Zeta Card', 'preference' => 5]);
        Card::factory()->create(['name' => 'Alpha Card', 'preference' => 1]);
        $mock = Mockery::mock(NearbyPlacesService::class);
        $mock->shouldReceive('fetchNearbyBusinesses')->andThrow(new Exception('Boom from Google.'));
        $this->app->instance(NearbyPlacesService::class, $mock);

        // Act
        $response = $this->actingAs($user)->get(route('dashboard', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]));

        // Assert
        $response->assertOk();
        $errors = $response->viewData('errors');
        $this->assertTrue($errors->has('google_places'));
        $this->assertSame('Boom from Google.', $errors->first('google_places'));
        $this->assertEmpty($response->viewData('results'));
        $this->assertNull($response->viewData('cachedLocation'));
        $this->assertSame(
            ['Alpha Card', 'Zeta Card'],
            $response->viewData('cards')->pluck('name')->all(),
        );
        $response->assertSee(__('Unable to Load Nearby Businesses'));
    }

    #[TestDox('The dashboard returns cards ordered by name ascending on the happy path')]
    public function test_dashboard_returns_cards_ordered_by_name_ascending(): void
    {
        // Arrange
        $user = User::factory()->create();
        Card::factory()->create(['name' => 'Mango', 'preference' => 0]);
        Card::factory()->create(['name' => 'Apple', 'preference' => 9]);
        Card::factory()->create(['name' => 'Banana', 'preference' => 5]);
        Cache::flush();

        // Act
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Assert
        $this->assertSame(
            ['Apple', 'Banana', 'Mango'],
            $response->viewData('cards')->pluck('name')->all(),
        );
    }

    // -------------------------------------------------------------------------
    // show — additional branches
    // -------------------------------------------------------------------------

    #[TestDox('Place show falls back to the Default category when friendly_category is null')]
    public function test_place_show_falls_back_to_default_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_default_fallback';
        Cache::put("place_detail_{$placeId}", [
            'place_id' => $placeId,
            'name' => 'Untyped Place',
            'address' => '1 Main St',
            'api_category' => 'unknown',
            'friendly_category' => null,
            'is_outside_us' => false,
            'recommended_card' => null,
            'recommended_percentage' => null,
            'is_default' => true,
            'needs_setup' => true,
        ], 60);
        $defaultWinner = Card::factory()->create(['name' => 'Default Hero', 'preference' => 0, 'foreign_transaction_fee' => 0]);
        $other = Card::factory()->create(['name' => 'Other', 'preference' => 1, 'foreign_transaction_fee' => 0]);
        Percentage::factory()->create(['card_id' => $defaultWinner->id, 'category' => 'Default', 'percentage' => 5]);
        Percentage::factory()->create(['card_id' => $other->id,         'category' => 'Default', 'percentage' => 1]);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert
        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, $other->name), strpos($content, $defaultWinner->name));
    }

    #[TestDox('Place show assigns category_percentage=0 to a card with no matching percentage row')]
    public function test_place_show_card_without_percentage_gets_zero(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_no_pct';
        Cache::put("place_detail_{$placeId}", [
            'place_id' => $placeId,
            'name' => 'A Place',
            'address' => '1 Main St',
            'api_category' => 'restaurant',
            'friendly_category' => 'Dining',
            'is_outside_us' => false,
            'recommended_card' => null,
            'recommended_percentage' => null,
            'is_default' => false,
            'needs_setup' => false,
        ], 60);
        $withPct = Card::factory()->create(['name' => 'WithPct', 'preference' => 1, 'foreign_transaction_fee' => 0]);
        $withoutPct = Card::factory()->create(['name' => 'WithoutPct', 'preference' => 2, 'foreign_transaction_fee' => 0]);
        Percentage::factory()->create(['card_id' => $withPct->id, 'category' => 'Dining', 'percentage' => 4]);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert — WithPct (percentage 4) should appear before WithoutPct (defaulted to 0)
        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, $withoutPct->name), strpos($content, $withPct->name));
    }

    #[TestDox('Place show currentFriendlyName is null when the api_category is not in the categories table')]
    public function test_place_show_current_friendly_name_null_when_category_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $placeId = 'ChIJ_no_category_row';
        Cache::put("place_detail_{$placeId}", [
            'place_id' => $placeId,
            'name' => 'A Place',
            'address' => '1 Main St',
            'api_category' => 'never_seen_type',
            'friendly_category' => null,
            'is_outside_us' => false,
            'recommended_card' => null,
            'recommended_percentage' => null,
            'is_default' => false,
            'needs_setup' => true,
        ], 60);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert
        $response->assertViewHas('currentFriendlyName', null);
    }

    #[TestDox('Within-US place ordering ignores the foreign transaction fee and sorts purely by percentage')]
    public function test_place_show_within_us_ignores_ftf(): void
    {
        // Arrange — same percentages, only FTF differs; ordering should be by name as a stable tiebreaker
        $user = User::factory()->create();
        $placeId = 'ChIJ_us_only';
        Cache::put("place_detail_{$placeId}", [
            'place_id' => $placeId,
            'name' => 'A Place',
            'address' => '1 Main St',
            'api_category' => 'restaurant',
            'friendly_category' => 'Dining',
            'is_outside_us' => false,
            'recommended_card' => null,
            'recommended_percentage' => null,
            'is_default' => false,
            'needs_setup' => false,
        ], 60);
        // An FTF card with a HIGHER percentage must still come first when within the US.
        $ftfWinner = Card::factory()->create(['name' => 'FtfWinner', 'preference' => 1, 'foreign_transaction_fee' => 3]);
        $noFtfLoser = Card::factory()->create(['name' => 'NoFtfLoser', 'preference' => 2, 'foreign_transaction_fee' => 0]);
        Percentage::factory()->create(['card_id' => $ftfWinner->id,  'category' => 'Dining', 'percentage' => 5]);
        Percentage::factory()->create(['card_id' => $noFtfLoser->id, 'category' => 'Dining', 'percentage' => 1]);

        // Act
        $response = $this->actingAs($user)->get(route('places.show', $placeId));

        // Assert
        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, $noFtfLoser->name), strpos($content, $ftfWinner->name));
    }

    // -------------------------------------------------------------------------
    // Data providers
    // -------------------------------------------------------------------------

    public static function invalidPlaceIdProvider(): array
    {
        return [
            'path traversal' => ['../../etc/passwd'],
            'slash in id' => ['abc/def'],
            'null byte' => ["abc\x00def"],
            'space in id' => ['abc def'],
        ];
    }

    public static function invalidCoordinateProvider(): array
    {
        // Empty values are handled by filled() and skip validation entirely (valid "no search" state).
        // Only non-numeric values reach the validator and should produce session errors.
        return [
            'latitude not numeric' => [['latitude' => 'abc', 'longitude' => 0.0]],
            'longitude not numeric' => [['latitude' => 0.0,   'longitude' => 'abc']],
        ];
    }
}
