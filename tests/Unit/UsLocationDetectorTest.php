<?php

namespace Tests\Unit;

use App\Services\UsLocationDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Extends PHPUnit\Framework\TestCase (not Tests\TestCase) deliberately — UsLocationDetector
 * has no Laravel dependencies, so booting the framework on every test method is wasted work.
 * Every other test in this project extends Tests\TestCase; deviate only when the class under
 * test is genuinely framework-free.
 */
class UsLocationDetectorTest extends TestCase
{
    private UsLocationDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange (shared)
        $this->detector = new UsLocationDetector();
    }

    // -------------------------------------------------------------------------
    // Tier 1 — country code from addressComponents
    // -------------------------------------------------------------------------

    public static function countryCodeInsideUsProvider(): array
    {
        return [
            'uppercase US' => ['US'],
            'lowercase us' => ['us'],
            'mixed case Us' => ['Us'],
            'Puerto Rico' => ['PR'],
            'Guam' => ['GU'],
            'US Virgin Islands' => ['VI'],
            'American Samoa' => ['AS'],
            'Northern Mariana Islands' => ['MP'],
        ];
    }

    public static function countryCodeOutsideUsProvider(): array
    {
        return [
            'Canada' => ['CA'],
            'Mexico' => ['MX'],
            'France' => ['FR'],
            'United Kingdom' => ['GB'],
        ];
    }

    #[DataProvider('countryCodeInsideUsProvider')]
    #[TestDox('A US-equivalent country code (any case) is conclusively inside the US regardless of coordinates or state code')]
    public function test_us_equivalent_country_code_is_conclusively_inside_us(string $countryCode): void
    {
        // Arrange — Paris coordinates and a non-US state code would fail every other tier
        // Act
        $result = $this->detector->isOutsideUs(48.8566, 2.3522, $countryCode, 'BC');

        // Assert
        $this->assertFalse($result);
    }

    #[DataProvider('countryCodeOutsideUsProvider')]
    #[TestDox('A non-US country code is conclusively outside the US regardless of coordinates or state code')]
    public function test_non_us_country_code_is_conclusively_outside_us(string $countryCode): void
    {
        // Arrange — NYC coordinates and a US state code would normally say inside US
        // Act
        $result = $this->detector->isOutsideUs(40.7128, -74.0060, $countryCode, 'NY');

        // Assert
        $this->assertTrue($result);
    }

    #[TestDox('A null country code falls through to the GPS tier')]
    public function test_null_country_code_falls_through_to_gps(): void
    {
        // Arrange — null country code; NYC coords clearly inside continental US
        // Act
        $result = $this->detector->isOutsideUs(40.7128, -74.0060, null, null);

        // Assert
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Tier 2 — GPS clearly inside a US region
    // -------------------------------------------------------------------------

    #[TestDox('Coordinates clearly inside a US bounding box are inside the US')]
    #[DataProvider('clearlyInsideUsProvider')]
    public function test_clearly_inside_us_coordinates(float $lat, float $lon): void
    {
        // Arrange — no country or state code, force the GPS tier
        // Act
        $result = $this->detector->isOutsideUs($lat, $lon, null, null);

        // Assert
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Tier 2 — GPS clearly outside all US regions
    // -------------------------------------------------------------------------

    #[TestDox('Coordinates clearly outside all US bounding boxes are outside the US')]
    #[DataProvider('clearlyOutsideUsProvider')]
    public function test_clearly_outside_us_coordinates(float $lat, float $lon): void
    {
        // Arrange — no country or state code, force the GPS tier
        // Act
        $result = $this->detector->isOutsideUs($lat, $lon, null, null);

        // Assert
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Tier 3 — border-zone state-code tiebreaker
    // -------------------------------------------------------------------------

    #[TestDox('A US border state in the GPS border zone resolves to inside the US')]
    #[DataProvider('borderZoneUsStateProvider')]
    public function test_border_zone_us_state_is_inside_us(string $stateCode): void
    {
        // Arrange — coordinates straddling the 49.4°N northern bounding-box edge
        $lat = 49.399;
        $lon = -97.0;

        // Act
        $result = $this->detector->isOutsideUs($lat, $lon, null, $stateCode);

        // Assert
        $this->assertFalse($result);
    }

    #[TestDox('A non-US state code in the GPS border zone resolves to outside the US')]
    #[DataProvider('borderZoneNonUsStateProvider')]
    public function test_border_zone_non_us_state_is_outside_us(string $stateCode): void
    {
        // Arrange — coordinates straddling the 49.4°N northern bounding-box edge
        $lat = 49.399;
        $lon = -97.0;

        // Act
        $result = $this->detector->isOutsideUs($lat, $lon, null, $stateCode);

        // Assert
        $this->assertTrue($result);
    }

    #[TestDox('A null state code in the GPS border zone resolves to outside the US')]
    public function test_border_zone_null_state_is_outside_us(): void
    {
        // Arrange — coordinates in the GPS border zone, no state code
        $lat = 49.399;
        $lon = -97.0;

        // Act
        $result = $this->detector->isOutsideUs($lat, $lon, null, null);

        // Assert
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Data providers
    // -------------------------------------------------------------------------

    public static function clearlyInsideUsProvider(): array
    {
        return [
            'New York City' => [40.7128,  -74.0060],
            'Los Angeles' => [34.0522, -118.2437],
            'Chicago' => [41.8781,  -87.6298],
            'Anchorage, Alaska' => [61.2181, -149.9003],
            'Honolulu, Hawaii' => [21.3069, -157.8583],
        ];
    }

    public static function clearlyOutsideUsProvider(): array
    {
        return [
            'Paris, France' => [48.8566,    2.3522],
            'London, UK' => [51.5074,   -0.1278],
            'Sydney, Australia' => [-33.8688, 151.2093],
            // Winnipeg GPS is above 49.4°N bounding-box edge — caught by GPS tier
            'Winnipeg, Canada' => [49.895,   -97.14],
            // Mexico City GPS is below 24.5°N bounding-box edge — caught by GPS tier
            'Mexico City' => [19.4326,  -99.1332],
        ];
    }

    public static function borderZoneUsStateProvider(): array
    {
        return [
            'Washington' => ['WA'],
            'North Dakota' => ['ND'],
            'Minnesota' => ['MN'],
            'Maine' => ['ME'],
            'New York' => ['NY'],
            'Ohio' => ['OH'],
            'lowercase wa' => ['wa'],
            'Puerto Rico' => ['PR'],
        ];
    }

    public static function borderZoneNonUsStateProvider(): array
    {
        return [
            'British Columbia' => ['BC'],
            'Manitoba' => ['MB'],
            'Ontario' => ['ON'],
            'Quebec' => ['QC'],
            'Baja California' => ['BCN'],
        ];
    }
}
