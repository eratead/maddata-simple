<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CampaignAssistantController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'chatHistory'     => 'required|array',
            'currentFormData' => 'required|array',
        ]);

        $systemPrompt = $this->buildSystemPrompt($request->currentFormData);

        $messages = collect($request->chatHistory)
            ->map(fn($m) => [
                'role'    => ($m['role'] ?? '') === 'user' ? 'user' : 'assistant',
                'content' => (string) ($m['content'] ?? ''),
            ])
            ->values()
            ->toArray();

        $response = Http::withHeaders([
            'x-api-key'         => env('ANTHROPIC_API_KEY'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => $messages,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'AI request failed.'], 502);
        }

        $raw = $response->json('content.0.text', '{}');

        // Strip markdown code fences if the model included them
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode(trim($raw), true);

        if (! is_array($data) || ! isset($data['reply'])) {
            return response()->json([
                'reply'   => 'I had trouble processing that. Please try again.',
                'updates' => null,
            ]);
        }

        return response()->json([
            'reply'   => $data['reply'],
            'updates' => $data['updates'] ?? null,
        ]);
    }

    private function buildSystemPrompt(array $formData): string
    {
        $current = json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $today   = now()->toDateString();

        $availableAudiences = Audience::where('is_active', true)
            ->orderBy('main_category')
            ->orderBy('name')
            ->get(['id', 'main_category', 'sub_category', 'name'])
            ->toJson(JSON_UNESCAPED_UNICODE);

        return 'You are an AI Campaign Assistant for MadData, a digital advertising platform in Israel.' . "\n\n"
            . 'CURRENT FORM STATE:' . "\n" . $current . "\n\n"
            . 'You help campaign managers configure campaigns by interpreting briefs and natural-language instructions (usually in Hebrew).' . "\n\n"
            . 'AVAILABLE AUDIENCES (pick IDs from this list for audience_ids):' . "\n" . $availableAudiences . "\n\n"
            . 'UPDATABLE FIELDS:' . "\n"
            . '- name (string): Campaign name' . "\n"
            . '- budget (number): Total budget in NIS' . "\n"
            . '- expected_impressions (number): Expected impressions count' . "\n"
            . '- start_date (YYYY-MM-DD): Campaign start date' . "\n"
            . '- end_date (YYYY-MM-DD): Campaign end date' . "\n"
            . '- status: "active" | "inactive" | "draft" | "ended"' . "\n"
            . '- genders: array subset of ["Male","Female"]. (Hebrew: "גברים"=Male, "נשים"=Female. Empty means All genders.)' . "\n"
            . '- ages: array subset of ["13-17","18-24","25-34","35-44","45-54","55-64","65+"]. Map logically, e.g. 21-30 → ["18-24","25-34"]. Empty means All ages.' . "\n"
            . '- incomes: array subset of ["0-195K","195-220K","220-245K","245K+"] — empty means All incomes' . "\n"
            . '- environments: array subset of ["In-App","Mobile Web"] — empty means All environments' . "\n"
            . '- days: array subset of ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"] — empty means All days' . "\n"
            . '- countries: array of country names (default: ["Israel"])' . "\n"
            . '- regions: array of region names (e.g. "Central", "North", "South", "Jerusalem", "Tel Aviv", "Haifa")' . "\n"
            . '- cities: array of city names' . "\n"
            . '- audience_ids: array of integer IDs chosen from AVAILABLE AUDIENCES above. Pick the best matches for the brief\'s target demographic.' . "\n"
            . 'NOTE: deviceTypes, os, and connectionTypes cannot be changed.' . "\n\n"
            . 'Today\'s date is ' . $today . '.' . "\n\n"
            . 'You MUST respond with a valid JSON object only — no markdown fences, no extra text:' . "\n"
            . '{"reply":"A friendly 1-2 sentence confirmation in Hebrew explaining what you updated","updates":{...} or null}' . "\n\n"
            . 'Rules:' . "\n"
            . '- Only include fields you are actually changing in "updates"' . "\n"
            . '- For array fields, always return the complete desired array (not a diff)' . "\n"
            . '- If nothing needs changing or the request is ambiguous, set "updates" to null and ask for clarification in "reply"' . "\n"
            . '- Translate Hebrew briefs accurately to the allowed English values.';
    }
}
