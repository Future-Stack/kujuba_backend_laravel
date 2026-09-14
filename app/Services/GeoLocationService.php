<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeoLocationService
{
    /**
     * Calculate distance between two coordinates in miles using Haversine Formula
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in miles
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 3959; // Earth radius in miles

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo   = deg2rad($lat2);
        $lonTo   = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return round($angle * $earthRadius, 2);
    }

    /**
     * Resolve Latitude & Longitude from a US Zip Code (Auto-Geocoding with Cache)
     *
     * @param string $zipCode
     * @return array|null ['latitude' => float, 'longitude' => float]
     */
    public static function getCoordinatesByZipCode(string $zipCode): ?array
    {
        $cleanZip = trim(substr($zipCode, 0, 5));
        if (empty($cleanZip)) {
            return null;
        }

        $cacheKey = "zip_coords_{$cleanZip}";
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($cleanZip) {
            try {
                // Free, fast US zip code coordinate lookup (zippopotam.us)
                $response = Http::timeout(4)->get("https://api.zippopotam.us/us/{$cleanZip}");
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['places'][0])) {
                        return [
                            'latitude'  => (float) $data['places'][0]['latitude'],
                            'longitude' => (float) $data['places'][0]['longitude'],
                            'city'      => $data['places'][0]['place name'] ?? null,
                            'state'     => $data['places'][0]['state abbreviation'] ?? null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ZipCode geocoding failed for {$cleanZip}: " . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Get all active inspectors within their service radius (default 50 miles)
     *
     * @param float|null $latitude
     * @param float|null $longitude
     * @param string|null $zipCode
     * @param float $maxRadius Miles
     * @return Collection
     */
    public static function getNearbyInspectors(?float $latitude, ?float $longitude, ?string $zipCode = null, float $maxRadius = 50.0): Collection
    {
        // If lat/lng are missing but zip is given, resolve coordinates from zip
        if ((is_null($latitude) || is_null($longitude)) && !empty($zipCode)) {
            $coords = self::getCoordinatesByZipCode($zipCode);
            if ($coords) {
                $latitude = $coords['latitude'];
                $longitude = $coords['longitude'];
            }
        }

        $query = User::where('user_type', 'inspector')
            ->where('status', 'active')
            ->whereHas('profile');

        // 1. If coordinates are available, find inspectors within their 50 miles radius
        if (!is_null($latitude) && !is_null($longitude)) {
            $inspectorsWithCoords = (clone $query)->whereHas('profile', function ($q) use ($latitude, $longitude, $maxRadius) {
                $q->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->whereRaw(
                      "(3959 * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) <= COALESCE(service_radius, ?)",
                      [$latitude, $longitude, $latitude, $maxRadius]
                  );
            })->with('profile')->get();

            // Also match inspectors sharing the same zip code even if coordinates are not yet set
            if (!empty($zipCode)) {
                $inspectorsWithZip = (clone $query)->whereHas('profile', function ($q) use ($zipCode) {
                    $q->where('zip_code', $zipCode)
                      ->whereNull('latitude');
                })->with('profile')->get();

                return $inspectorsWithCoords->merge($inspectorsWithZip)->unique('id');
            }

            return $inspectorsWithCoords;
        }

        // 2. If only zip code is provided and coords resolution failed, match by zip code directly
        if (!empty($zipCode)) {
            return (clone $query)->whereHas('profile', function ($q) use ($zipCode) {
                $q->where('zip_code', $zipCode);
            })->with('profile')->get();
        }

        return new Collection();
    }
}
