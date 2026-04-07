<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Category;
use App\Models\Percentage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages cashback and reward percentage assignments per card and category.
 */
class PercentageController
{
    /**
     * Display the cashback percentages matrix (cards × categories).
     *
     * @return View The percentages index Blade view with cards, categories, and current percentages.
     */
    public function index(): View
    {
        $cards = Card::orderBy('preference', 'ASC')->get();
        $categories = Category::distinct()->orderBy('friendly_name')->pluck('friendly_name');
        $percentages = Percentage::all()
            ->groupBy('category')
            ->map(fn ($group) => $group->keyBy('card_id'));

        return view('percentages.index', compact('cards', 'categories', 'percentages'));
    }

    /**
     * Bulk-update percentage values, validating that all submitted categories and card IDs exist.
     *
     * @param  Request  $request  The HTTP request containing a nested percentages[category][card_id] = value array.
     * @return RedirectResponse Redirect to the percentages index with a success status.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'percentages' => 'required|array',
            'percentages.*' => 'array',
            'percentages.*.*' => 'required|integer|min:0|max:255',
        ]);

        $submittedCategories = array_keys($request->percentages);
        $submittedCardIds = collect($request->percentages)
            ->flatMap(fn ($v) => array_keys($v))
            ->unique()->values()->all();

        validator(['categories' => $submittedCategories], [
            'categories.*' => ['required', Rule::exists('categories', 'friendly_name')],
        ])->validate();

        validator(['card_ids' => $submittedCardIds], [
            'card_ids.*' => ['required', Rule::exists('cards', 'id')],
        ])->validate();

        foreach ($request->percentages as $category => $cardValues) {
            foreach ($cardValues as $cardId => $percentage) {
                Percentage::where('card_id', $cardId)
                    ->where('category', $category)
                    ->update(['percentage' => $percentage]);
            }
        }

        return redirect()->route('percentages.index')->with('status', __('Percentages updated.'));
    }
}
