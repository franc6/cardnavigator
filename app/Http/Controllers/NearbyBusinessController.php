<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Category;
use App\Services\NearbyPlacesService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * Handles nearby business search requests and delegates business logic to NearbyPlacesService.
 */
class NearbyBusinessController
{
    /**
     * @param  NearbyPlacesService  $placesService  Service that talks to the Google Places API and caches results.
     */
    public function __construct(private NearbyPlacesService $placesService)
    {
    }

    /**
     * Display the dashboard, running a nearby business search when coordinates are present.
     *
     * @param  Request  $request  The HTTP request, optionally containing latitude and longitude query parameters.
     * @return View The dashboard view with business results, card list, and search context.
     */
    public function index(Request $request): View
    {
        $results = [];
        $search = null;
        $cachedLocation = null;

        if ($request->filled(['latitude', 'longitude'])) {
            $validated = $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            try {
                $fetched = $this->placesService->fetchNearbyBusinesses($validated['latitude'], $validated['longitude']);
                $results = $fetched['results'];
                $cachedLocation = $fetched['from_cache']
                    ? ['latitude' => $fetched['snapped_lat'], 'longitude' => $fetched['snapped_lon']]
                    : null;
                $search = $validated;
            } catch (Exception $e) {
                $errors = (new ViewErrorBag())->put('default', new MessageBag(['google_places' => [$e->getMessage()]]));

                return view('dashboard', [
                    'results' => [],
                    'cards' => Card::orderBy('name', 'ASC')->get(),
                    'search' => $validated,
                    'cachedLocation' => null,
                    'errors' => $errors,
                ]);
            }
        }

        return view('dashboard', [
            'results' => $results,
            'cards' => Card::orderBy('name', 'ASC')->get(),
            'search' => $search,
            'cachedLocation' => $cachedLocation,
        ]);
    }

    /**
     * Accept a POST with coordinates and redirect to the dashboard GET route.
     *
     * @param  Request  $request  The POST request containing latitude and longitude.
     * @return RedirectResponse Redirect to the dashboard with coordinates as query parameters.
     */
    public function search(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        return redirect()->route('dashboard', $validated);
    }

    /**
     * Display the place detail page for a cached place.
     *
     * @param  string  $placeId  The Google Places API place ID.
     * @return View The place detail view with card recommendations sorted by percentage and foreign-transaction-fee status.
     */
    public function show(string $placeId): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9_\-]{1,255}$/', $placeId), 404);

        $place = $this->placesService->getCachedPlace($placeId);
        abort_unless($place, 404);

        $cards = $this->loadSortedCardsForPlace($place);
        $friendlyNames = Category::distinct()->orderBy('friendly_name')->pluck('friendly_name');
        $currentFriendlyName = Category::where('name', $place['api_category'])->value('friendly_name');

        return view('places.show', compact('place', 'cards', 'placeId', 'friendlyNames', 'currentFriendlyName'));
    }

    /**
     * Load every card with its percentage row for this place's category, then sort for display.
     *
     * @param  array<string, mixed>  $place  Normalized place array containing friendly_category and is_outside_us.
     * @return Collection<int, Card> Cards in display order, each annotated with a category_percentage attribute.
     */
    private function loadSortedCardsForPlace(array $place): Collection
    {
        $lookupCategory = $place['friendly_category'] ?? 'Default';
        $isOutsideUs = $place['is_outside_us'];

        return Card::with(['percentages' => fn ($q) => $q->where('category', $lookupCategory)])
            ->orderBy('preference')->orderBy('name')->get()
            ->map(fn ($card) => $this->withCategoryPercentage($card))
            ->sortBy($this->cardSortOrder($isOutsideUs))
            ->values();
    }

    /**
     * Annotate a card with its percentage for the looked-up category, defaulting to 0 when absent.
     *
     * @param  Card  $card  The card whose eager-loaded percentages collection holds 0 or 1 rows.
     * @return Card The same card with a category_percentage attribute attached.
     */
    private function withCategoryPercentage(Card $card): Card
    {
        $card->category_percentage = $card->percentages->first()?->percentage ?? 0;

        return $card;
    }

    /**
     * Build the comparator stack used by Collection::sortBy() to order cards on the place page.
     *
     * Outside the US, cards with a foreign transaction fee are pushed below cards without one;
     * inside the US the first comparator is a no-op. Ties are broken by category_percentage descending.
     *
     * @param  bool  $isOutsideUs  True when the place is outside the US, enabling the FTF tiebreaker.
     * @return array<int, callable> Comparators consumed by Collection::sortBy() in order.
     */
    private function cardSortOrder(bool $isOutsideUs): array
    {
        return [
            fn ($cmpA, $cmpB) => $isOutsideUs ? ($cmpA->foreign_transaction_fee > 0) <=> ($cmpB->foreign_transaction_fee > 0) : 0,
            fn ($cmpA, $cmpB) => $cmpB->category_percentage <=> $cmpA->category_percentage,
        ];
    }
}
