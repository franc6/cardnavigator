<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Percentage;
use App\Services\NearbyPlacesService;
use Illuminate\Http\Request;

/**
 * Manages the mapping of Google Places API types to user-friendly category names.
 */
class CategoryController
{
    /**
     * @param  NearbyPlacesService  $placesService  Service that owns the place-detail cache.
     */
    public function __construct(private NearbyPlacesService $placesService)
    {
    }

    /**
     * Remap a place's API type to a different friendly category, migrating existing percentage records.
     *
     * @param  string  $placeId  The Google Places API place ID, used to look up the cached place.
     * @param  Request  $request  The HTTP request containing the new friendly_name.
     * @return \Illuminate\Http\RedirectResponse Redirect to the place detail page with a success status.
     */
    public function update(string $placeId, Request $request)
    {
        abort_unless(preg_match('/^[A-Za-z0-9_\-]{1,255}$/', $placeId), 404);

        $request->validate([
            'friendly_name' => 'required|string|max:255|exists:categories,friendly_name',
        ]);

        $place = $this->placesService->getCachedPlace($placeId);

        abort_unless($place, 404);

        $category = Category::firstOrNew(['name' => $place['api_category']]);
        $previousFriendlyName = $category->friendly_name;
        $category->friendly_name = $request->friendly_name;
        $category->save();

        if ($previousFriendlyName && $previousFriendlyName !== $category->friendly_name) {
            Percentage::where('category', $previousFriendlyName)
                ->update(['category' => $category->friendly_name]);
        }

        return redirect()->route('places.show', $placeId)
            ->with('status', __('Category updated.'));
    }
}
