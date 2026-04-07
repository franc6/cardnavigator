<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Percentage;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches, normalizes, and caches nearby business data from the Google Places API.
 */
class NearbyPlacesService
{
    /**
     * @param  UsLocationDetector  $locationDetector  Helper that classifies coordinates as inside or outside the US.
     */
    public function __construct(private UsLocationDetector $locationDetector)
    {
    }

    /**
     * Fetch and cache nearby businesses, snapping coordinates to a ~55 m grid to maximise cache hits.
     *
     * @param  float  $latitude  Search latitude in decimal degrees.
     * @param  float  $longitude  Search longitude in decimal degrees.
     * @return array{results: array, from_cache: bool, snapped_lat: float, snapped_lon: float} Normalized places plus cache metadata.
     */
    public function fetchNearbyBusinesses(float $latitude, float $longitude): array
    {
        [$snappedLat, $snappedLon] = $this->snapToGrid($latitude, $longitude);
        $cacheKey = "nearby_businesses_{$snappedLat}_{$snappedLon}";

        $fromCache = Cache::has($cacheKey);

        $results = Cache::remember($cacheKey, config('services.google_maps.cache_ttl'), function () use ($latitude, $longitude) {
            return $this->callGooglePlacesAPI($latitude, $longitude);
        });

        return [
            'results' => $results,
            'from_cache' => $fromCache,
            'snapped_lat' => $snappedLat,
            'snapped_lon' => $snappedLon,
        ];
    }

    /**
     * Retrieve a cached place by its place ID.
     *
     * @param  string  $placeId  The place ID returned by the Google Places API.
     * @return array<string, mixed>|null The cached normalized place, or null if not found.
     */
    public function getCachedPlace(string $placeId): ?array
    {
        return Cache::get($this->placeDetailKey($placeId));
    }

    /**
     * Build the single cache-key format used for storing normalized place details.
     *
     * @param  string  $placeId  The place ID returned by the Google Places API.
     * @return string The cache key under which the normalized place is stored.
     */
    private function placeDetailKey(string $placeId): string
    {
        return "place_detail_{$placeId}";
    }

    /**
     * Snap coordinates to a ~55 m grid so nearby inputs share a cache entry.
     *
     * Latitude uses a fixed 0.0005° step (≈55 m). Longitude degrees shrink with
     * latitude (meridian convergence), so the longitude step is scaled by
     * 1/cos(snappedLat) to keep both axes the same physical size at any
     * latitude. The longitude grid is derived from the *snapped* latitude so
     * jittered inputs in the same latitude cell always produce the same
     * longitude grid — and therefore the same cache key.
     *
     * @param  float  $latitude  Latitude in decimal degrees.
     * @param  float  $longitude  Longitude in decimal degrees.
     * @return array{0: float, 1: float} Snapped latitude and longitude.
     */
    private function snapToGrid(float $latitude, float $longitude): array
    {
        $latGrid = 0.0005;
        $snappedLat = round($latitude / $latGrid) * $latGrid;
        $lonGrid = $latGrid / cos(deg2rad($snappedLat));
        $snappedLon = round($longitude / $lonGrid) * $lonGrid;

        return [$snappedLat, $snappedLon];
    }

    /**
     * Map a raw API place to a normalized array with category, card recommendation, and US-location flag.
     *
     * All places returned by the API are within 300 m of the search point, so
     * the search coordinates are used as the GPS fallback inside the
     * US-location check (only consulted when the country addressComponent is
     * missing).
     *
     * @param  array  $place  Raw place data from the Google Places API (id, displayName, types, formattedAddress, addressComponents).
     * @param  float  $searchLat  Latitude of the original search point, used for the US-location GPS fallback.
     * @param  float  $searchLon  Longitude of the original search point, used for the US-location GPS fallback.
     * @return array<string, mixed> Normalized place with name, address, api_category, friendly_category, is_outside_us, recommended_card, recommended_percentage, is_default, and needs_setup.
     */
    public function normalizePlace(array $place, float $searchLat = 0.0, float $searchLon = 0.0): array
    {
        [$apiCategory, $category] = $this->resolvePlaceCategory($place);
        $isOutsideUs = $this->resolveLocationContext($place, $searchLat, $searchLon);
        [$percentageRecord, $isDefault] = $this->resolveBestCard($category?->friendly_name, $isOutsideUs);

        $normalized = $this->buildNormalizedPlace($place, $apiCategory, $category, $isOutsideUs, $percentageRecord, $isDefault);
        $this->cachePlace($normalized);

        return $normalized;
    }

    /**
     * Pick the first API type and load the matching Category row, falling back to "unknown" when types is empty.
     *
     * @param  array<string, mixed>  $place  Raw place data from the Google Places API.
     * @return array{0: string, 1: Category|null} The API category string and the matching Category row, if any.
     */
    private function resolvePlaceCategory(array $place): array
    {
        $apiCategory = collect($place['types'] ?? [])->first() ?? 'unknown';

        return [$apiCategory, Category::where('name', $apiCategory)->first()];
    }

    /**
     * Run the US-location detector against the place's addressComponents, using the search coordinates as the GPS fallback.
     *
     * @param  array<string, mixed>  $place  Raw place data from the Google Places API.
     * @param  float  $lat  Search latitude used only for the GPS bounding-box fallback.
     * @param  float  $lon  Search longitude used only for the GPS bounding-box fallback.
     * @return bool True when the place is classified as outside the United States.
     */
    private function resolveLocationContext(array $place, float $lat, float $lon): bool
    {
        $components = $place['addressComponents'] ?? [];

        return $this->locationDetector->isOutsideUs(
            $lat,
            $lon,
            $this->extractComponentShortName($components, 'country'),
            $this->extractComponentShortName($components, 'administrative_area_level_1'),
        );
    }

    /**
     * Pull the shortName of the first addressComponent whose types list contains $type.
     *
     * @param  array  $components  The addressComponents array from a Google Places response.
     * @param  string  $type  The component type to search for (e.g. 'country', 'administrative_area_level_1').
     */
    private function extractComponentShortName(array $components, string $type): ?string
    {
        return collect($components)
            ->first(fn (array $component) => in_array($type, $component['types'] ?? [], true))['shortName'] ?? null;
    }

    /**
     * Pick the highest-percentage card for the place's category, falling back to the Default category when needed.
     *
     * @param  string|null  $friendlyName  Friendly category name from the matched Category row, or null.
     * @param  bool  $isOutsideUs  True when the place is outside the US (excludes FTF cards).
     * @return array{0: Percentage|null, 1: bool} The chosen Percentage record (or null) and a flag indicating
     *                                            whether the Default-category fallback was used.
     */
    private function resolveBestCard(?string $friendlyName, bool $isOutsideUs): array
    {
        $record = $friendlyName ? $this->bestCardForCategory($friendlyName, $isOutsideUs) : null;

        if ($record !== null) {
            return [$record, false];
        }

        return [$this->bestCardForCategory('Default', $isOutsideUs), true];
    }

    /**
     * Assemble the final normalized place array from the already-resolved parts.
     *
     * @param  array<string, mixed>  $place  Raw place data from the Google Places API.
     * @param  string  $apiCategory  Resolved API category string.
     * @param  Category|null  $category  Matched Category row, or null when no row exists for the API type.
     * @param  bool  $isOutsideUs  Result of the US-location check.
     * @param  Percentage|null  $percentageRecord  Best matching Percentage row, or null.
     * @param  bool  $isDefault  True when the Default-category fallback was used.
     * @return array<string, mixed> Normalized place ready for caching and the dashboard view.
     */
    private function buildNormalizedPlace(array $place, string $apiCategory, ?Category $category, bool $isOutsideUs, ?Percentage $percentageRecord, bool $isDefault): array
    {
        return [
            ...$this->placeIdentityFields($place),
            'api_category' => $apiCategory,
            'friendly_category' => $category?->friendly_name ?: null,
            'is_outside_us' => $isOutsideUs,
            ...$this->cardRecommendationFields($percentageRecord, $isDefault),
            'needs_setup' => $this->needsSetup($category, $percentageRecord, $isDefault),
        ];
    }

    /**
     * Extract id, display name, and formatted address from a raw place, supplying defaults when fields are missing.
     *
     * @param  array<string, mixed>  $place  Raw place data from the Google Places API.
     * @return array{place_id: string|null, name: string, address: string} Identity fields with translated default name.
     */
    private function placeIdentityFields(array $place): array
    {
        return [
            'place_id' => $place['id'] ?? null,
            'name' => $place['displayName']['text'] ?? __('Unknown business'),
            'address' => $place['formattedAddress'] ?? '',
        ];
    }

    /**
     * Project a chosen Percentage record into the recommended-card display fields.
     *
     * @param  Percentage|null  $percentageRecord  The best matching Percentage row, or null.
     * @param  bool  $isDefault  True when the Default-category fallback was used.
     * @return array{recommended_card: string|null, recommended_percentage: float|null, is_default: bool}
     */
    private function cardRecommendationFields(?Percentage $percentageRecord, bool $isDefault): array
    {
        return [
            'recommended_card' => $percentageRecord?->card?->name,
            'recommended_percentage' => $percentageRecord?->percentage,
            'is_default' => $isDefault,
        ];
    }

    /**
     * Decide whether the dashboard should flag this place as needing user setup.
     *
     * @param  Category|null  $category  The matched Category row, or null.
     * @param  Percentage|null  $percentageRecord  The best matching Percentage row, or null.
     * @param  bool  $isDefault  True when the Default-category fallback was used.
     * @return bool True when no category mapping, no percentage row, or only the Default fallback was found.
     */
    private function needsSetup(?Category $category, ?Percentage $percentageRecord, bool $isDefault): bool
    {
        return $category === null || $percentageRecord === null || $isDefault;
    }

    /**
     * Store the normalized place in the cache under place_detail_{$place_id} for the place-detail page to read.
     *
     * @param  array<string, mixed>  $normalized  Normalized place; must contain place_id to be cached.
     */
    private function cachePlace(array $normalized): void
    {
        if ($normalized['place_id']) {
            Cache::put($this->placeDetailKey($normalized['place_id']), $normalized, 86400);
        }
    }

    /**
     * Return the highest-percentage card for a category, excluding cards with a foreign transaction fee when outside the US.
     *
     * @param  string  $category  The friendly category name to query (e.g. "Grocery", "Default").
     * @param  bool  $isOutsideUs  When true, cards with a non-zero foreign_transaction_fee are excluded.
     * @return Percentage|null The best-matching Percentage record with its card relation loaded, or null if none exists.
     *
     * In this case, the boolean flag actually helps maintain a single responsibility; this method needs to know
     * if the location is outside the US to pick the best card. Attempting to determine that inside this method
     * would give it two responsibilities instead of just one.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function bestCardForCategory(string $category, bool $isOutsideUs = false): ?Percentage
    {
        return Percentage::with('card')
            ->join('cards', 'cards.id', '=', 'percentages.card_id')
            ->where('percentages.category', $category)
            ->when($isOutsideUs, fn ($q) => $q->where('cards.foreign_transaction_fee', 0))
            ->orderByDesc('percentages.percentage')
            ->orderBy('cards.preference')
            ->select('percentages.*')
            ->first();
    }

    /**
     * Call the Google Places Nearby Search API, or return fake data outside production.
     *
     * @param  float  $latitude  Search latitude in decimal degrees.
     * @param  float  $longitude  Search longitude in decimal degrees.
     * @return array Normalized place records from the API or the fake-data generator.
     */
    private function callGooglePlacesAPI(float $latitude, float $longitude): array
    {
        if (app()->environment() !== 'production') {
            return $this->generateFakePlaces($latitude, $longitude);
        }

        $response = $this->requestNearbyPlaces($this->requireApiKey(), $latitude, $longitude);

        if ($response->failed()) {
            throw new Exception($this->extractApiErrorMessage($response));
        }

        return collect($response->json('places', []))
            ->map(fn ($place) => $this->normalizePlace($place, $latitude, $longitude))
            ->all();
    }

    /**
     * Read the Google Maps API key from configuration, throwing when it is missing.
     *
     * @return string The configured API key.
     *
     * @throws Exception When services.google_maps.key is unset or empty.
     */
    private function requireApiKey(): string
    {
        $key = config('services.google_maps.key');

        if (empty($key)) {
            throw new Exception(__('Google Maps API key is not configured.'));
        }

        return $key;
    }

    /**
     * Issue the searchNearby request to the Google Places API (New).
     *
     * @param  string  $apiKey  The API key sent in the X-Goog-Api-Key header.
     * @param  float  $latitude  Search circle center latitude in decimal degrees.
     * @param  float  $longitude  Search circle center longitude in decimal degrees.
     * @return \Illuminate\Http\Client\Response The HTTP client response from the API.
     */
    private function requestNearbyPlaces(string $apiKey, float $latitude, float $longitude): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'places.id,places.displayName,places.types,places.formattedAddress,places.addressComponents',
            'Referer' => config('app.url'),
        ])->post('https://places.googleapis.com/v1/places:searchNearby', [
            'maxResultCount' => 20,
            'locationRestriction' => [
                'circle' => [
                    'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'radius' => 360.0,
                ],
            ],
            'rankPreference' => 'distance',
        ]);
    }

    /**
     * Log the failed API response and convert it to a user-facing translated error string.
     *
     * @param  \Illuminate\Http\Client\Response  $response  The failed HTTP client response from the API.
     * @return string A translated error message, using the API's error.message field when present.
     */
    private function extractApiErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        Log::error('Google Places API request failed.', ['response' => $response->body()]);
        $message = $response->json('error.message');

        return $message
            ? __('Unable to fetch nearby businesses. API Error: :message', ['message' => $message])
            : __('Unable to fetch nearby businesses.');
    }

    /**
     * Return a representative set of fake normalized places for non-production environments.
     *
     * @param  float  $latitude  Latitude passed through to normalizePlace for the US-location check.
     * @param  float  $longitude  Longitude passed through to normalizePlace for the US-location check.
     * @return array Normalized fake place records covering one entry per supported category.
     */
    private function generateFakePlaces(float $latitude, float $longitude): array
    {
        $faker = Faker::create();

        $places = [
            ['name' => $faker->company . ' Auto Repair',    'type' => 'car_repair'],
            ['name' => $faker->company . ' Restaurant',     'type' => 'restaurant'],
            ['name' => $faker->company . ' Medical',        'type' => 'doctor'],
            ['name' => $faker->company . ' Cinema',         'type' => 'movie_theater'],
            ['name' => $faker->company . ' Gas Station',    'type' => 'gas_station'],
            ['name' => $faker->company . ' Grocery',        'type' => 'grocery_store'],
            ['name' => $faker->company . ' Hardware',       'type' => 'hardware_store'],
            ['name' => $faker->company . ' Bank',           'type' => 'bank'],
            ['name' => $faker->company . ' Pharmacy',       'type' => 'pharmacy'],
            ['name' => $faker->company . ' Clothing',       'type' => 'clothing_store'],
            ['name' => $faker->city . ' Airport',           'type' => 'airport'],
            ['name' => $faker->company . ' Hotel',          'type' => 'hotel'],
            ['name' => $faker->company . ' Wholesale Club', 'type' => 'wholesaler'],
            ['name' => $faker->company . ' Spa',            'type' => 'ZZZZZ'],
        ];

        $results = [];

        foreach ($places as $business) {
            $stateAbbr = $faker->stateAbbr;
            $results[] = $this->normalizePlace([
                'id' => $faker->uuid,
                'displayName' => ['text' => $business['name']],
                'types' => [$business['type']],
                'formattedAddress' => $faker->streetAddress . ', ' . $faker->city . ', ' . $stateAbbr . ' ' . $faker->postcode . ', USA',
                'addressComponents' => [
                    ['longName' => 'United States', 'shortName' => 'US', 'types' => ['country', 'political']],
                    ['longName' => $stateAbbr, 'shortName' => $stateAbbr, 'types' => ['administrative_area_level_1', 'political']],
                ],
            ], $latitude, $longitude);
        }

        foreach ($places as $business) {
            $results[] = $this->normalizePlace([
                'id' => $faker->uuid,
                'displayName' => ['text' => $business['name'] . ' International'],
                'types' => [$business['type']],
                'formattedAddress' => $faker->streetAddress . ', ' . $faker->city . ', ON K1A 0B1, Canada',
                'addressComponents' => [
                    ['longName' => 'Canada', 'shortName' => 'CA', 'types' => ['country', 'political']],
                    ['longName' => 'Ontario', 'shortName' => 'ON', 'types' => ['administrative_area_level_1', 'political']],
                ],
            ], $latitude, $longitude);
        }

        return $results;
    }
}
