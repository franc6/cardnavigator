<?php

namespace App\Http\Controllers;

use App\Exceptions\UnsupportedImageFormatException;
use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use App\Services\CardImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manages credit and rewards card records.
 */
class CardController
{
    public function __construct(private readonly CardImageService $images)
    {
    }

    /**
     * Display the cards list ordered by preference.
     *
     * @return View The cards index Blade view.
     */
    public function index(): View
    {
        return view('cards.index', [
            'cards' => Card::orderBy('preference', 'ASC')->get(),
        ]);
    }

    /**
     * Create a new card and seed default percentages for all existing categories.
     *
     * @param  Request  $request  The HTTP request containing name, foreign_transaction_fee, preference, and optional image/color.
     * @return RedirectResponse Redirect to the percentages index with a prompt to configure the new card.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());
        $imageAttrs = $this->extractImageAttributes($request);

        $card = Card::create(array_merge(
            [
                'name' => $validated['name'],
                'foreign_transaction_fee' => $validated['foreign_transaction_fee'],
                'preference' => $validated['preference'],
                'color' => $validated['color'] ?? null,
            ],
            $imageAttrs,
        ));

        $friendlyNames = Category::distinct()->orderBy('friendly_name')->pluck('friendly_name');

        foreach ($friendlyNames as $name) {
            Percentage::create(['card_id' => $card->id, 'category' => $name, 'percentage' => 1]);
        }

        return redirect()->route('percentages.index')
            ->with('status', __('Card added. Set percentages below.'));
    }

    /**
     * Update an existing card's attributes.
     *
     * @param  Request  $request  The HTTP request containing updated name, foreign_transaction_fee, preference, and optional image/color.
     * @param  Card  $card  The card record to update.
     * @return RedirectResponse Redirect to the cards index with a success status.
     */
    public function update(Request $request, Card $card): RedirectResponse
    {
        $validated = $request->validate($this->validationRules($card));
        $imageAttrs = $this->extractImageAttributes($request);

        $card->update(array_merge(
            [
                'name' => $validated['name'],
                'foreign_transaction_fee' => $validated['foreign_transaction_fee'],
                'preference' => $validated['preference'],
                'color' => $validated['color'] ?? null,
            ],
            $imageAttrs,
        ));

        return redirect()->route('cards.index')->with('status', __('Card updated.'));
    }

    /**
     * Delete a card.
     *
     * @param  Card  $card  The card to delete.
     * @return RedirectResponse Redirect to the cards index with a success status.
     */
    public function destroy(Card $card): RedirectResponse
    {
        $card->delete();

        return redirect()->route('cards.index')->with('status', __('Card deleted.'));
    }

    /**
     * Serve the card's stored image with the correct content-type header.
     *
     * @param  Card  $card  The card whose stored base64 image should be decoded and served.
     * @return Response The raw image bytes with an appropriate Content-Type and cache headers.
     */
    public function image(Card $card): Response
    {
        abort_unless($card->image_data, 404);

        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
        $mime = in_array($card->image_mime, $allowed, true) ? $card->image_mime : 'image/png';

        return response(base64_decode($card->image_data))
            ->header('Content-Type', $mime)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Build the validation rule set for store/update.
     *
     * @param  Card|null  $card  When provided, the name-uniqueness rule excludes this id.
     * @return array<string, mixed> Validation rules keyed by field name.
     */
    private function validationRules(?Card $card = null): array
    {
        $nameUnique = $card === null
            ? 'unique:cards'
            : 'unique:cards,name,' . $card->id;

        return [
            'name' => 'required|string|max:255|' . $nameUnique,
            'foreign_transaction_fee' => 'required|integer|min:0|max:100',
            'preference' => 'required|integer|min:0|max:255',
            'color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'image_file' => [
                'nullable',
                'file',
                'mimetypes:image/png,image/jpeg,image/webp,image/gif,image/heic,image/heif',
            ],
        ];
    }

    /**
     * Convert an optional image upload into storable card attributes.
     *
     * @param  Request  $request  The HTTP request being processed.
     * @return array{image_data?: string, image_mime?: string} The attributes to merge into Card::create/update.
     *
     * @throws ValidationException If the image cannot be processed on this server.
     */
    private function extractImageAttributes(Request $request): array
    {
        if (! $request->hasFile('image_file')) {
            return [];
        }

        try {
            $payload = $this->images->fromUpload($request->file('image_file'));
        } catch (UnsupportedImageFormatException $e) {
            throw ValidationException::withMessages([
                'image_file' => [$e->getMessage()],
            ]);
        }

        return ['image_data' => $payload['data'], 'image_mime' => $payload['mime']];
    }
}
