<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Percentage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Example seeder that loads fictional cashback percentages for demonstration.
 *
 * Percentages are intentionally outrageous (many exceed 100%) so it is obvious
 * these are not real rates. Delete this data and configure your own percentages
 * via the percentages interface at /percentages.
 */
class PercentageSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Human-readable label shown in the admin Database Tools page.
     */
    public static function label(): string
    {
        return 'Example Percentages';
    }

    /**
     * Insert fictional example percentages keyed to the example card names.
     */
    public function run(): void
    {
        $cards = Card::all()->keyBy('name');

        // Acme Platinum Rewards — outrageous rates to make it obvious these are examples
        if ($card = $cards->get('Acme Platinum Rewards')) {
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Automotive'], ['percentage' => 85]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Dining'], ['percentage' => 120]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Doctor'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Entertainment'], ['percentage' => 90]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Gas'], ['percentage' => 95]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Grocery'], ['percentage' => 110]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Home Improvement'], ['percentage' => 85]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Other'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Pharmacy'], ['percentage' => 88]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Shopping'], ['percentage' => 92]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Transportation'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Travel'], ['percentage' => 115]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Wholesale'], ['percentage' => 87]);
        }

        // Widget Bank Cashback
        if ($card = $cards->get('Widget Bank Cashback')) {
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Automotive'], ['percentage' => 90]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Dining'], ['percentage' => 85]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Doctor'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Entertainment'], ['percentage' => 82]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Gas'], ['percentage' => 130]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Grocery'], ['percentage' => 95]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Home Improvement'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Other'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Pharmacy'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Shopping'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Transportation'], ['percentage' => 88]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Travel'], ['percentage' => 100]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Wholesale'], ['percentage' => 80]);
        }

        // Fictional Credit Union Visa
        if ($card = $cards->get('Fictional Credit Union Visa')) {
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Automotive'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Dining'], ['percentage' => 105]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Doctor'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Entertainment'], ['percentage' => 98]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Gas'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Grocery'], ['percentage' => 97]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Home Improvement'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Other'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Pharmacy'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Shopping'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Transportation'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Travel'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Wholesale'], ['percentage' => 80]);
        }

        // Example Savings Plus
        if ($card = $cards->get('Example Savings Plus')) {
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Automotive'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Dining'], ['percentage' => 90]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Doctor'], ['percentage' => 140]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Entertainment'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Gas'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Grocery'], ['percentage' => 90]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Home Improvement'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Other'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Pharmacy'], ['percentage' => 125]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Shopping'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Transportation'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Travel'], ['percentage' => 83]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Wholesale'], ['percentage' => 83]);
        }

        // Demo Travel Card
        if ($card = $cards->get('Demo Travel Card')) {
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Automotive'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Dining'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Doctor'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Entertainment'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Gas'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Grocery'], ['percentage' => 82]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Home Improvement'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Other'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Pharmacy'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Shopping'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Transportation'], ['percentage' => 80]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Travel'], ['percentage' => 160]);
            Percentage::firstOrCreate(['card_id' => $card->id, 'category' => 'Wholesale'], ['percentage' => 82]);
        }
    }
}
