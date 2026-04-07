<?php

namespace App\Services;

/**
 * Determines whether a place is outside the United States using structured
 * Google Places signals (country shortName, state shortName) with a GPS
 * bounding-box fallback for the rare case when the country component is
 * missing.
 */
class UsLocationDetector
{
    /**
     * ~300 metres expressed as degrees of latitude/longitude.
     * 1° ≈ 111 km, so 0.3 km / 111 km ≈ 0.0027°.
     */
    private const BUFFER_DEG = 0.0027;

    /**
     * Approximate bounding boxes [lat_min, lat_max, lon_min, lon_max] for the
     * three major US regions covered by the GPS fallback. Values are not
     * padded; the buffer is applied at query time.
     */
    private const REGIONS = [
        'continental' => [24.5,  49.4, -125.0,  -66.9],
        'alaska' => [54.5,  71.5, -168.5, -130.0],
        'hawaii' => [18.9,  22.2, -160.3, -154.8],
    ];

    /**
     * ISO 3166-1 alpha-2 codes for the US and the US territories where
     * US-issued cards carry no foreign-transaction fee.
     */
    private const US_COUNTRY_CODES = ['US', 'PR', 'GU', 'VI', 'AS', 'MP'];

    /**
     * US states and territories adjacent to a bounding-box edge — only
     * consulted in the GPS border zone, when the country component is
     * unavailable.
     */
    private const US_BORDER_STATES = [
        'AK', 'AZ', 'CA', 'FL', 'ID', 'ME', 'MN', 'MT',
        'ND', 'NM', 'NY', 'OH', 'PR', 'TX', 'WA',
    ];

    /**
     * Return true when the place is outside the United States.
     *
     * Resolution order:
     *   1. Country code present  → conclusive: inside US iff the code is in
     *      {@see self::US_COUNTRY_CODES}.
     *   2. GPS clearly inside or clearly outside a US bounding box
     *      → conclusive.
     *   3. GPS in the border zone of a US bounding box → tiebreaker on the
     *      state component: a positive match against
     *      {@see self::US_BORDER_STATES} means inside US, anything else
     *      (including a null state code) means outside US.
     *
     * @param  float  $lat  Latitude of the place in decimal degrees.
     * @param  float  $lon  Longitude of the place in decimal degrees.
     * @param  string|null  $countryCode  ISO 3166-1 alpha-2 country code from addressComponents; when present it takes precedence over GPS.
     * @param  string|null  $stateCode  ISO 3166-2 sub-region code (administrative_area_level_1.shortName); only consulted in the GPS border zone.
     * @return bool True when the place is outside the United States.
     */
    public function isOutsideUs(float $lat, float $lon, ?string $countryCode = null, ?string $stateCode = null): bool
    {
        return $countryCode !== null
            ? ! in_array(strtoupper($countryCode), self::US_COUNTRY_CODES, true)
            : $this->resolveByGps($lat, $lon, $stateCode);
    }

    /**
     * Walk the US bounding boxes and return the first non-null verdict.
     *
     * @param  float  $lat  Latitude in decimal degrees.
     * @param  float  $lon  Longitude in decimal degrees.
     * @param  string|null  $stateCode  Sub-region code consulted when the coordinates land in a border zone.
     */
    private function resolveByGps(float $lat, float $lon, ?string $stateCode): bool
    {
        foreach (self::REGIONS as $region) {
            $verdict = $this->verdictForRegion($lat, $lon, $region, $stateCode);

            if ($verdict !== null) {
                return $verdict;
            }
        }

        return true;
    }

    /**
     * Verdict for a single region.
     *
     * @param  array  $region  Bounding box as [lat_min, lat_max, lon_min, lon_max].
     * @return bool|null False = inside US, true = outside US, null = region inconclusive (try the next region).
     */
    private function verdictForRegion(float $lat, float $lon, array $region, ?string $stateCode): ?bool
    {
        $buf = self::BUFFER_DEG;

        if ($this->isClearlyInsideRegion($lat, $lon, $region, $buf)) {
            return false;
        }

        if ($this->isInBorderZoneOfRegion($lat, $lon, $region, $buf)) {
            return ! $this->isUsBorderState($stateCode);
        }

        return null;
    }

    /**
     * Return true when the coordinates are more than BUFFER_DEG inside a US bounding box.
     *
     * @param  array  $region  Bounding box as [lat_min, lat_max, lon_min, lon_max].
     */
    private function isClearlyInsideRegion(float $lat, float $lon, array $region, float $buf): bool
    {
        [$latMin, $latMax, $lonMin, $lonMax] = $region;

        return $lat > $latMin + $buf && $lat < $latMax - $buf
            && $lon > $lonMin + $buf && $lon < $lonMax - $buf;
    }

    /**
     * Return true when the coordinates are within BUFFER_DEG of a US bounding-box border.
     *
     * @param  array  $region  Bounding box as [lat_min, lat_max, lon_min, lon_max].
     */
    private function isInBorderZoneOfRegion(float $lat, float $lon, array $region, float $buf): bool
    {
        [$latMin, $latMax, $lonMin, $lonMax] = $region;

        return $lat >= $latMin - $buf && $lat <= $latMax + $buf
            && $lon >= $lonMin - $buf && $lon <= $lonMax + $buf;
    }

    /**
     * Return true when the given state code matches a US border state/territory.
     */
    private function isUsBorderState(?string $stateCode): bool
    {
        return $stateCode !== null
            && in_array(strtoupper($stateCode), self::US_BORDER_STATES, true);
    }
}
