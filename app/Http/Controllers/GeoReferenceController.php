<?php

namespace App\Http\Controllers;

use App\Services\GeoReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GeoReferenceController extends Controller
{
    public function __construct(private readonly GeoReferenceService $geo) {}

    /**
     * GET /api/geo/countries
     * Returns a flat list of country name strings.
     */
    public function countries(): JsonResponse
    {
        return response()->json(['data' => $this->geo->countries()]);
    }

    /**
     * GET /api/geo/regions?country={name}
     * Returns a flat list of region/state name strings for the given country.
     */
    public function regions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $this->geo->regions($validated['country'])]);
    }

    /**
     * GET /api/geo/cities?country={name}
     * Returns a flat list of city name strings for the given country.
     * For Israel, both Hebrew and English entries are present.
     */
    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $this->geo->cities($validated['country'])]);
    }
}
