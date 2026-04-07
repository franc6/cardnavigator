<?php

namespace App\Providers;

use App\Services\CardImageService;
use App\Services\Images\GdImageResizer;
use App\Services\Images\ImagickImageResizer;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps application-level services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings.
     *
     * `CardImageService` receives its image resizers in preference order:
     * Imagick first (when the extension is loaded it covers all six supported
     * formats including HEIC/HEIF), falling back to GD. Each resizer
     * self-reports availability, so no conditional wiring is needed here.
     */
    public function register(): void
    {
        $this->app->singleton(CardImageService::class, fn ($app) => new CardImageService([
            $app->make(ImagickImageResizer::class),
            $app->make(GdImageResizer::class),
        ]));
    }

    /**
     * Run boot-time configuration. Forces all generated URLs to use the HTTPS scheme when running
     * in the production or staging environment so reverse-proxy-terminated TLS is preserved.
     */
    public function boot(): void
    {
        if ($this->app->environment('production', 'staging')) {
            URL::forceScheme('https');
        }
    }
}
