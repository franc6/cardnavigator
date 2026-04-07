<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public static function forcedHttpsEnvironmentProvider(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
        ];
    }

    #[DataProvider('forcedHttpsEnvironmentProvider')]
    #[TestDox('HTTPS is forced when the app environment is production or staging')]
    public function test_https_is_forced_for_protected_environments(string $environment): void
    {
        // Arrange
        $this->app['env'] = $environment;
        URL::shouldReceive('forceScheme')->once()->with('https');

        // Act
        (new AppServiceProvider($this->app))->boot();

        // Assert — Mockery expectations verified on tearDown.
        $this->addToAssertionCount(1);
    }

    public static function unforcedEnvironmentProvider(): array
    {
        return [
            'local' => ['local'],
            'testing' => ['testing'],
            'development' => ['development'],
        ];
    }

    #[DataProvider('unforcedEnvironmentProvider')]
    #[TestDox('HTTPS is not forced outside production or staging')]
    public function test_https_is_not_forced_in_other_environments(string $environment): void
    {
        // Arrange
        $this->app['env'] = $environment;
        URL::shouldReceive('forceScheme')->never();

        // Act
        (new AppServiceProvider($this->app))->boot();

        // Assert — Mockery expectations verified on tearDown.
        $this->addToAssertionCount(1);
    }
}
