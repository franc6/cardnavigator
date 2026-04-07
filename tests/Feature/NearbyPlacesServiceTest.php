<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use App\Services\NearbyPlacesService;
use Exception;
use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class NearbyPlacesServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): NearbyPlacesService
    {
        return app(NearbyPlacesService::class);
    }

    private function placeWithoutCountry(string $type = 'restaurant'): array
    {
        return [
            'displayName' => ['text' => 'A Place'],
            'types' => [$type],
            'formattedAddress' => '123 Main St, USA',
            'addressComponents' => [
                ['longName' => 'United States', 'shortName' => 'US', 'types' => ['country', 'political']],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // normalizePlace — foreign-transaction-fee filtering
    // -------------------------------------------------------------------------

    public static function foreignTransactionFeeProvider(): array
    {
        return [
            'non-US place (Paris) excludes FTF cards' => [
                'placeData' => [
                    'displayName' => ['text' => 'Le Café'],
                    'types' => ['restaurant'],
                    'formattedAddress' => '12 Rue de Rivoli, Paris, France',
                    'addressComponents' => [['longName' => 'France', 'shortName' => 'FR', 'types' => ['country', 'political']]],
                ],
                'lat' => 48.8566,
                'lon' => 2.3522,
                'expectedWinner' => 'noFtf',
            ],
            'US place includes FTF cards' => [
                'placeData' => [
                    'displayName' => ['text' => 'The Diner'],
                    'types' => ['restaurant'],
                    'formattedAddress' => '123 Main St, Chicago, IL 60601, USA',
                    'addressComponents' => [['longName' => 'United States', 'shortName' => 'US', 'types' => ['country', 'political']]],
                ],
                'lat' => 41.8781,
                'lon' => -87.6298,
                'expectedWinner' => 'hasFtf',
            ],
            'border zone Canadian address excludes FTF cards' => [
                'placeData' => [
                    'displayName' => ['text' => 'The Poutinerie'],
                    'types' => ['restaurant'],
                    'formattedAddress' => '1 Main St, Winnipeg, MB R3C 0V8',
                    'addressComponents' => [['longName' => 'Canada', 'shortName' => 'CA', 'types' => ['country', 'political']]],
                ],
                'lat' => 49.399,
                'lon' => -97.0,
                'expectedWinner' => 'noFtf',
            ],
        ];
    }

    #[DataProvider('foreignTransactionFeeProvider')]
    #[TestDox('Foreign transaction fee cards are included or excluded based on the place country')]
    public function test_ftf_card_eligibility_by_country(array $placeData, float $lat, float $lon, string $expectedWinner): void
    {
        // Arrange
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);
        $noFtf = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 1]);
        $hasFtf = Card::factory()->create(['foreign_transaction_fee' => 3, 'preference' => 0]);
        Percentage::factory()->create(['card_id' => $noFtf->id,  'category' => 'Dining', 'percentage' => 1]);
        Percentage::factory()->create(['card_id' => $hasFtf->id, 'category' => 'Dining', 'percentage' => 5]);

        // Act
        $result = $this->service()->normalizePlace($placeData, $lat, $lon);

        // Assert
        $expected = $expectedWinner === 'noFtf' ? $noFtf->name : $hasFtf->name;
        $this->assertSame($expected, $result['recommended_card']);
    }

    // -------------------------------------------------------------------------
    // normalizePlace — caching
    // -------------------------------------------------------------------------

    #[TestDox('normalizePlace caches individual place data keyed by place_id')]
    public function test_normalize_place_caches_by_place_id(): void
    {
        // Arrange
        $faker = Factory::create();
        $placeId = $faker->uuid;
        $placeName = $faker->company;
        Cache::forget("place_detail_{$placeId}");

        // Act
        $this->service()->normalizePlace([
            'id' => $placeId,
            'displayName' => ['text' => $placeName],
            'types' => ['restaurant'],
            'formattedAddress' => $faker->streetAddress . ', ' . $faker->city . ', USA',
        ], 40.7128, -74.0060);

        // Assert
        $cached = Cache::get("place_detail_{$placeId}");
        $this->assertNotNull($cached);
        $this->assertSame($placeName, $cached['name']);
        $this->assertSame($placeId, $cached['place_id']);
    }

    #[TestDox('normalizePlace does not write to the cache when the place has no id')]
    public function test_normalize_place_skips_cache_when_id_missing(): void
    {
        // Arrange
        Cache::flush();

        // Act
        $normalized = $this->service()->normalizePlace([
            'displayName' => ['text' => 'No-id Place'],
            'types' => ['restaurant'],
            'formattedAddress' => '1 Main St, USA',
        ], 40.7128, -74.0060);

        // Assert
        $this->assertNull($normalized['place_id']);
        $this->assertNull(Cache::get('place_detail_'));
    }

    // -------------------------------------------------------------------------
    // normalizePlace — field defaults
    // -------------------------------------------------------------------------

    #[TestDox('normalizePlace defaults the name to "Unknown business" when displayName is missing')]
    public function test_normalize_place_defaults_name_when_display_name_missing(): void
    {
        // Arrange — no displayName
        $place = [
            'id' => 'no_name_id',
            'types' => ['restaurant'],
            'formattedAddress' => '1 Main St, USA',
        ];

        // Act
        $normalized = $this->service()->normalizePlace($place, 40.7128, -74.0060);

        // Assert
        $this->assertSame(__('Unknown business'), $normalized['name']);
    }

    #[TestDox('normalizePlace defaults the address to an empty string when formattedAddress is missing')]
    public function test_normalize_place_defaults_address_when_missing(): void
    {
        // Arrange
        $place = [
            'id' => 'no_address_id',
            'displayName' => ['text' => 'A Place'],
            'types' => ['restaurant'],
        ];

        // Act
        $normalized = $this->service()->normalizePlace($place, 40.7128, -74.0060);

        // Assert
        $this->assertSame('', $normalized['address']);
    }

    #[TestDox('normalizePlace sets api_category to "unknown" when types is missing or empty')]
    public function test_normalize_place_sets_unknown_api_category_when_types_missing(): void
    {
        // Arrange
        $missingTypes = ['id' => 'a', 'displayName' => ['text' => 'A']];
        $emptyTypes = ['id' => 'b', 'displayName' => ['text' => 'B'], 'types' => []];

        // Act
        $a = $this->service()->normalizePlace($missingTypes, 40.7128, -74.0060);
        $b = $this->service()->normalizePlace($emptyTypes, 40.7128, -74.0060);

        // Assert
        $this->assertSame('unknown', $a['api_category']);
        $this->assertSame('unknown', $b['api_category']);
    }

    #[TestDox('normalizePlace sets friendly_category to null when the Category is not in the database')]
    public function test_normalize_place_friendly_category_null_when_category_missing(): void
    {
        // Arrange — no Category row for 'restaurant'

        // Act
        $normalized = $this->service()->normalizePlace($this->placeWithoutCountry('restaurant'), 40.7128, -74.0060);

        // Assert
        $this->assertNull($normalized['friendly_category']);
    }

    // -------------------------------------------------------------------------
    // normalizePlace — is_default and needs_setup branches
    // -------------------------------------------------------------------------

    #[TestDox('normalizePlace falls back to the Default card and marks is_default=true when the category has no card')]
    public function test_normalize_place_falls_back_to_default_card(): void
    {
        // Arrange — friendly category exists in DB, but no Percentage for it.
        // A Default-category Percentage is the fallback.
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);
        $defaultCard = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 0]);
        Percentage::factory()->create(['card_id' => $defaultCard->id, 'category' => 'Default', 'percentage' => 1]);

        // Act
        $normalized = $this->service()->normalizePlace($this->placeWithoutCountry('restaurant'), 40.7128, -74.0060);

        // Assert
        $this->assertTrue($normalized['is_default']);
        $this->assertSame($defaultCard->name, $normalized['recommended_card']);
        $this->assertTrue($normalized['needs_setup']);
    }

    #[TestDox('normalizePlace sets is_default=false when a category-specific card exists')]
    public function test_normalize_place_is_default_false_on_happy_path(): void
    {
        // Arrange
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);
        $card = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 0]);
        Percentage::factory()->create(['card_id' => $card->id, 'category' => 'Dining', 'percentage' => 5]);

        // Act
        $normalized = $this->service()->normalizePlace($this->placeWithoutCountry('restaurant'), 40.7128, -74.0060);

        // Assert
        $this->assertFalse($normalized['is_default']);
        $this->assertSame($card->name, $normalized['recommended_card']);
        $this->assertFalse($normalized['needs_setup']);
    }

    #[TestDox('normalizePlace returns needs_setup=true when the Category row is missing')]
    public function test_normalize_place_needs_setup_when_category_missing(): void
    {
        // Arrange — no Category row, no Percentage rows
        // Act
        $normalized = $this->service()->normalizePlace($this->placeWithoutCountry('restaurant'), 40.7128, -74.0060);

        // Assert
        $this->assertTrue($normalized['needs_setup']);
    }

    #[TestDox('normalizePlace returns needs_setup=true when no Percentage rows exist anywhere')]
    public function test_normalize_place_needs_setup_when_no_percentages(): void
    {
        // Arrange — Category exists but no Percentages (not even a Default fallback)
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);

        // Act
        $normalized = $this->service()->normalizePlace($this->placeWithoutCountry('restaurant'), 40.7128, -74.0060);

        // Assert
        $this->assertTrue($normalized['needs_setup']);
        $this->assertNull($normalized['recommended_card']);
    }

    // -------------------------------------------------------------------------
    // bestCardForCategory — direct unit-level branches
    // -------------------------------------------------------------------------

    #[TestDox('bestCardForCategory excludes cards with a foreign transaction fee when isOutsideUs=true')]
    public function test_best_card_excludes_ftf_when_outside_us(): void
    {
        // Arrange
        $noFtf = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 5]);
        $hasFtf = Card::factory()->create(['foreign_transaction_fee' => 3, 'preference' => 0]);
        Percentage::factory()->create(['card_id' => $noFtf->id,  'category' => 'Dining', 'percentage' => 1]);
        Percentage::factory()->create(['card_id' => $hasFtf->id, 'category' => 'Dining', 'percentage' => 5]);

        // Act
        $result = $this->service()->bestCardForCategory('Dining', true);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($noFtf->id, $result->card->id);
    }

    #[TestDox('bestCardForCategory does not exclude FTF cards when isOutsideUs=false')]
    public function test_best_card_does_not_exclude_ftf_when_inside_us(): void
    {
        // Arrange
        $noFtf = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 5]);
        $hasFtf = Card::factory()->create(['foreign_transaction_fee' => 3, 'preference' => 0]);
        Percentage::factory()->create(['card_id' => $noFtf->id,  'category' => 'Dining', 'percentage' => 1]);
        Percentage::factory()->create(['card_id' => $hasFtf->id, 'category' => 'Dining', 'percentage' => 5]);

        // Act
        $result = $this->service()->bestCardForCategory('Dining', false);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($hasFtf->id, $result->card->id);
    }

    #[TestDox('bestCardForCategory returns null when no Percentage rows exist for the category')]
    public function test_best_card_returns_null_when_no_rows(): void
    {
        // Arrange — no Percentage rows for 'Dining'
        Card::factory()->create();

        // Act
        $result = $this->service()->bestCardForCategory('Dining');

        // Assert
        $this->assertNull($result);
    }

    #[TestDox('bestCardForCategory orders by percentage descending then preference ascending')]
    public function test_best_card_orders_by_percentage_desc_then_preference_asc(): void
    {
        // Arrange — two cards tied on percentage; lower preference value should win
        $preferred = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 1]);
        $lessPreferred = Card::factory()->create(['foreign_transaction_fee' => 0, 'preference' => 2]);
        Percentage::factory()->create(['card_id' => $preferred->id,     'category' => 'Dining', 'percentage' => 5]);
        Percentage::factory()->create(['card_id' => $lessPreferred->id, 'category' => 'Dining', 'percentage' => 5]);

        // Act
        $result = $this->service()->bestCardForCategory('Dining');

        // Assert
        $this->assertSame($preferred->id, $result->card->id);
    }

    // -------------------------------------------------------------------------
    // fetchNearbyBusinesses — cache miss vs hit and coordinate snapping
    // -------------------------------------------------------------------------

    #[TestDox('fetchNearbyBusinesses reports from_cache=false on the first call and populates the cache')]
    public function test_fetch_nearby_businesses_cache_miss(): void
    {
        // Arrange
        Cache::flush();

        // Act
        $fetched = $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);

        // Assert
        $this->assertFalse($fetched['from_cache']);
        $key = "nearby_businesses_{$fetched['snapped_lat']}_{$fetched['snapped_lon']}";
        $this->assertTrue(Cache::has($key));
    }

    #[TestDox('fetchNearbyBusinesses reports from_cache=true on the second call with the same snapped coords')]
    public function test_fetch_nearby_businesses_cache_hit(): void
    {
        // Arrange
        Cache::flush();
        $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);

        // Act
        $second = $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);

        // Assert
        $this->assertTrue($second['from_cache']);
    }

    #[TestDox('fetchNearbyBusinesses snaps coordinates to the ~55 m latitude grid')]
    public function test_fetch_nearby_businesses_snaps_to_grid(): void
    {
        // Arrange — pick a coord just off a grid line; expect the snapped value
        Cache::flush();
        $latGrid = 0.0005;
        $expectedSnappedLat = round(40.71281 / $latGrid) * $latGrid;
        $lonGrid = $latGrid / cos(deg2rad($expectedSnappedLat));
        $expectedSnappedLon = round(-74.00601 / $lonGrid) * $lonGrid;

        // Act
        $fetched = $this->service()->fetchNearbyBusinesses(40.71281, -74.00601);

        // Assert
        $this->assertEqualsWithDelta($expectedSnappedLat, $fetched['snapped_lat'], 1e-9);
        $this->assertEqualsWithDelta($expectedSnappedLon, $fetched['snapped_lon'], 1e-9);
    }

    #[TestDox('Two jittered coordinates inside one grid cell share a cache key and the second hit reports from_cache=true')]
    public function test_fetch_nearby_businesses_jitter_shares_cache_key(): void
    {
        // Arrange
        Cache::flush();
        $first = $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);

        // Act — tiny jitter (≪ 55 m) should snap to the same cell
        $second = $this->service()->fetchNearbyBusinesses(40.71281, -74.00601);

        // Assert
        $this->assertEqualsWithDelta($first['snapped_lat'], $second['snapped_lat'], 1e-9);
        $this->assertEqualsWithDelta($first['snapped_lon'], $second['snapped_lon'], 1e-9);
        $this->assertTrue($second['from_cache']);
    }

    // -------------------------------------------------------------------------
    // getCachedPlace — cache hit/miss
    // -------------------------------------------------------------------------

    #[TestDox('getCachedPlace returns null when the place is not in the cache')]
    public function test_get_cached_place_returns_null_on_miss(): void
    {
        // Arrange
        Cache::forget('place_detail_missing');

        // Act
        $result = $this->service()->getCachedPlace('missing');

        // Assert
        $this->assertNull($result);
    }

    #[TestDox('getCachedPlace returns the stored normalized place when present')]
    public function test_get_cached_place_returns_value_on_hit(): void
    {
        // Arrange
        $stored = ['place_id' => 'abc', 'name' => 'Cached Café'];
        Cache::put('place_detail_abc', $stored, 60);

        // Act
        $result = $this->service()->getCachedPlace('abc');

        // Assert
        $this->assertSame($stored, $result);
    }

    // -------------------------------------------------------------------------
    // callGooglePlacesAPI — production branch (Http::fake)
    // -------------------------------------------------------------------------

    #[TestDox('In production a successful Google Places response is normalized and cached')]
    public function test_production_api_success_path(): void
    {
        // Arrange
        Cache::flush();
        Config::set('services.google_maps.key', 'fake-key');
        Config::set('app.url', 'https://cardnav.example.com');
        $this->app['env'] = 'production';
        Category::factory()->create(['name' => 'restaurant', 'friendly_name' => 'Dining']);
        Http::fake([
            'places.googleapis.com/*' => Http::response([
                'places' => [
                    [
                        'id' => 'prod_place_1',
                        'displayName' => ['text' => 'Prod Diner'],
                        'types' => ['restaurant'],
                        'formattedAddress' => '1 Main St, NYC, NY, USA',
                        'addressComponents' => [
                            ['longName' => 'United States', 'shortName' => 'US', 'types' => ['country', 'political']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $fetched = $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);

        // Assert
        $this->assertCount(1, $fetched['results']);
        $this->assertSame('Prod Diner', $fetched['results'][0]['name']);
        $this->assertSame('Dining', $fetched['results'][0]['friendly_category']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'places.googleapis.com')
            && $request->header('X-Goog-Api-Key') === ['fake-key']
            && $request->header('Referer') === ['https://cardnav.example.com']);
    }

    #[TestDox('In production a failed Google Places response throws with the API error message')]
    public function test_production_api_failure_uses_api_error_message(): void
    {
        // Arrange
        Cache::flush();
        Config::set('services.google_maps.key', 'fake-key');
        $this->app['env'] = 'production';
        Http::fake([
            'places.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Quota exceeded for the day.'],
            ], 429),
        ]);

        // Act + Assert
        try {
            $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);
            $this->fail('Expected an exception when the API responds with a failure.');
        } catch (Exception $e) {
            $this->assertSame(
                __('Unable to fetch nearby businesses. API Error: :message', ['message' => 'Quota exceeded for the day.']),
                $e->getMessage(),
            );
        }
    }

    #[TestDox('In production a failed Google Places response with no message falls back to the generic error')]
    public function test_production_api_failure_falls_back_to_generic_message(): void
    {
        // Arrange
        Cache::flush();
        Config::set('services.google_maps.key', 'fake-key');
        $this->app['env'] = 'production';
        Http::fake([
            'places.googleapis.com/*' => Http::response([], 500),
        ]);

        // Act + Assert
        try {
            $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);
            $this->fail('Expected an exception when the API responds with a failure.');
        } catch (Exception $e) {
            $this->assertSame(__('Unable to fetch nearby businesses.'), $e->getMessage());
        }
    }

    #[TestDox('In production a missing Google Maps API key throws a configuration error')]
    public function test_production_missing_api_key_throws(): void
    {
        // Arrange
        Cache::flush();
        Config::set('services.google_maps.key', '');
        $this->app['env'] = 'production';

        // Act + Assert
        try {
            $this->service()->fetchNearbyBusinesses(40.7128, -74.0060);
            $this->fail('Expected an exception when the API key is missing.');
        } catch (Exception $e) {
            $this->assertSame(__('Google Maps API key is not configured.'), $e->getMessage());
        }
    }
}
