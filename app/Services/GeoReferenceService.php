<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class GeoReferenceService
{
    /**
     * Returns a flat array of country name strings.
     */
    public function countries(): array
    {
        return Cache::remember('geo:countries', now()->addDays(7), function () {
            return $this->resolveCountries();
        });
    }

    /**
     * Returns a flat array of region/state name strings for the given country.
     */
    public function regions(string $country): array
    {
        $slug = Str::slug($country);

        return Cache::remember("geo:regions:{$slug}", now()->addDays(7), function () use ($country, $slug) {
            return $this->resolveRegions($country, $slug);
        });
    }

    /**
     * Returns a flat array of city name strings for the given country.
     * For Israel, the list contains each city in both English and Hebrew.
     */
    public function cities(string $country): array
    {
        $slug = Str::slug($country);

        return Cache::remember("geo:cities:{$slug}", now()->addDays(7), function () use ($country, $slug) {
            return $this->resolveCities($country, $slug);
        });
    }

    // -------------------------------------------------------------------------
    // Private resolution methods
    // -------------------------------------------------------------------------

    private function resolveCountries(): array
    {
        try {
            $response = Http::timeout(5)->get('https://countriesnow.space/api/v0.1/countries/iso');

            if ($response->successful()) {
                $data = $response->json('data', []);
                if (! empty($data)) {
                    return collect($data)->pluck('name')->sort()->values()->all();
                }
            }
        } catch (\Throwable) {
            // Fall through to static fallback
        }

        return $this->staticFallback('countries', null);
    }

    private function resolveRegions(string $country, string $slug): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://countriesnow.space/api/v0.1/countries/states', [
                    'country' => $country,
                ]);

            if ($response->successful()) {
                $states = $response->json('data.states', []);
                if (! empty($states)) {
                    return collect($states)->pluck('name')->values()->all();
                }
            }
        } catch (\Throwable) {
            // Fall through to static fallback
        }

        return $this->staticFallback('regions', $slug, ['endpoint' => 'regions', 'country' => $country]);
    }

    private function resolveCities(string $country, string $slug): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://countriesnow.space/api/v0.1/countries/cities', [
                    'country' => $country,
                ]);

            if ($response->successful()) {
                $cities = $response->json('data', []);
                if (! empty($cities) && is_array($cities)) {
                    return array_values($cities);
                }
            }
        } catch (\Throwable) {
            // Fall through to static fallback
        }

        return $this->staticFallback('cities', $slug, ['endpoint' => 'cities', 'country' => $country]);
    }

    /**
     * Read a bundled JSON file from storage/app/geo/.
     *
     * @param  string  $type  'countries', 'regions', or 'cities'
     * @param  string|null  $slug  Country slug (null for countries list itself)
     * @param  array  $logContext  Extra context for the fallback warning log
     */
    private function staticFallback(string $type, ?string $slug, array $logContext = []): array
    {
        $path = $type === 'countries'
            ? storage_path('app/geo/countries.json')
            : storage_path("app/geo/{$type}/{$slug}.json");

        Log::channel('ai')->warning('geo.fallback_used', array_merge(
            ['type' => $type, 'file' => $path],
            $logContext,
        ));

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }
}
