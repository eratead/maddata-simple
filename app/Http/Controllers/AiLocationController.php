<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiLocationController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate(['prompt' => 'required|string|max:500']);

        $response = Http::withHeaders([
            'x-api-key'         => env('ANTHROPIC_API_KEY'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'system'     => 'You are a geocoding API for Israel. Return ONLY a raw JSON array of objects. No markdown formatting, no backticks, no conversational text. Each object must have: "name" (string, concise), "lat" (string, latitude), "lng" (string, longitude).',
            'messages'   => [['role' => 'user', 'content' => $request->prompt]],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'AI request failed.'], 502);
        }

        $raw = $response->json('content.0.text', '[]');

        // Strip markdown code fences if the model included them
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);

        $locations = json_decode(trim($raw), true);

        if (!is_array($locations)) {
            return response()->json(['error' => 'Could not parse AI response.'], 422);
        }

        return response()->json($locations);
    }
}
