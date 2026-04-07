<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Root seeder invoked by `php artisan db:seed`. Intentionally a no-op: the application loads no
 * default data, leaving the admin Database Tools page to run example seeders on demand.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * No default seed data — example seeders are run on demand from the admin database tools page,
     * and end users configure their own data via the /cards and /percentages screens.
     */
    public function run(): void
    {
    }
}
