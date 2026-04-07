<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Admin controller for running database migrations and example data seeders.
 */
class DatabaseController
{
    /**
     * Display the admin database management page with a list of available example seeders.
     *
     * @return View The admin database management Blade view.
     */
    public function index(): View
    {
        $seeders = collect(glob(database_path('seeders/*.php')))
            ->map(fn ($path) => basename($path, '.php'))
            ->filter(fn ($class) => $class !== 'DatabaseSeeder')
            ->map(function ($class) {
                $fqcn = 'Database\\Seeders\\' . $class;

                return [
                    'class' => $class,
                    'label' => $fqcn::label(),
                ];
            })
            ->values();

        return view('admin.database', ['seeders' => $seeders]);
    }

    /**
     * Run all pending database migrations.
     *
     * @return RedirectResponse Redirect to the database admin page with migration output in the session.
     */
    public function migrate(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return redirect()->route('admin.database.index')
            ->with('status', __('Migrations run successfully.'))
            ->with('output', trim(Artisan::output()));
    }

    /**
     * Run a named database seeder class.
     *
     * @param  Request  $request  The HTTP request containing the seeder class name (letters only).
     * @return RedirectResponse Redirect to the database admin page with seeder output in the session.
     */
    public function seed(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seeder' => 'required|string|regex:/^[A-Za-z]+$/',
        ]);

        $class = 'Database\\Seeders\\' . $validated['seeder'];

        abort_unless(class_exists($class), 422, __('Unknown seeder.'));

        Artisan::call('db:seed', ['--class' => $class, '--force' => true]);

        return redirect()->route('admin.database.index')
            ->with('status', __(':seeder run successfully.', ['seeder' => $validated['seeder']]))
            ->with('output', trim(Artisan::output()));
    }
}
