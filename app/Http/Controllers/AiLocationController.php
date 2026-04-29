<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiLocationController extends Controller
{
    public function generate(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('can_edit_campaigns'), 403);

        $request->validate(['prompt' => 'required|string|max:500']);

        // Release the session lock before the long-running Anthropic HTTP call (P10 fix).
        // Session data is already written; this unblocks other tabs/requests from the same user.
        session()->save();

        // Explicit User-Agent: api.anthropic.com is fronted by Cloudflare, which
        // returns a 403 "Just a moment..." JS challenge to clients with the
        // default GuzzleHttp UA. A real product UA passes the bot check.
        $response = Http::timeout(15)->withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
            'user-agent' => 'MadData-CampaignAssistant/1.0 (+https://ad.maddata.media)',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'system' => 'You are a geocoding API for Israel. Return ONLY a raw JSON array of objects. No markdown formatting, no backticks, no conversational text. Each object must have: "name" (string, concise), "lat" (string, latitude), "lng" (string, longitude).',
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'AI request failed.'], 502);
        }

        $raw = $response->json('content.0.text', '[]');

        // Strip markdown code fences if the model included them
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);

        $locations = json_decode(trim($raw), true);

        if (! is_array($locations)) {
            return response()->json(['error' => 'Could not parse AI response.'], 422);
        }

        return response()->json($locations);
    }
}
