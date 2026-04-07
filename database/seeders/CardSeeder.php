<?php

namespace Database\Seeders;

use App\Models\Card;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Example seeder that loads fictional credit card data for demonstration.
 *
 * Card names and colors are invented. Use the cards interface at /cards
 * to configure your actual cards after deleting this example data.
 */
class CardSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Human-readable label shown in the admin Database Tools page.
     */
    public static function label(): string
    {
        return 'Example Cards';
    }

    /**
     * Insert fictional example cards.
     */
    public function run(): void
    {
        $cards = [
            [
                'name' => 'Acme Platinum Rewards',
                'foreign_transaction_fee' => 0,
                'preference' => 0,
                'color' => '#708090',
            ],
            [
                'name' => 'Widget Bank Cashback',
                'foreign_transaction_fee' => 0,
                'preference' => 1,
                'color' => '#00308F',
            ],
            [
                'name' => 'Fictional Credit Union Visa',
                'foreign_transaction_fee' => 0,
                'preference' => 2,
                'color' => '#6B1A1A',
            ],
            [
                'name' => 'Example Savings Plus',
                'foreign_transaction_fee' => 3,
                'preference' => 3,
                'color' => '#006B5E',
            ],
            [
                'name' => 'Demo Travel Card',
                'foreign_transaction_fee' => 3,
                'preference' => 4,
                'color' => '#C8102E',
            ],
        ];

        foreach ($cards as $data) {
            Card::updateOrCreate(
                ['name' => $data['name']],
                [
                    'foreign_transaction_fee' => $data['foreign_transaction_fee'],
                    'preference' => $data['preference'],
                    'image_data' => null,
                    'image_mime' => null,
                    'color' => $data['color'],
                ]
            );
        }
    }
}
