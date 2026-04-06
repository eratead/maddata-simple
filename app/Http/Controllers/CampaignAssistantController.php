<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CampaignAssistantController extends Controller
{
    public function chat(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('can_edit_campaigns'), 403);

        $request->validate([
            'chatHistory' => ['required', 'array', 'max:50'],
            'chatHistory.*.role' => ['required', 'string', 'in:user,assistant'],
            'chatHistory.*.content' => ['required', 'string', 'max:5000'],
            'currentFormData' => 'required|array',
        ]);

        $systemPrompt = $this->buildSystemPrompt($request->currentFormData);

        $messages = collect($request->chatHistory)
            ->map(fn ($m) => [
                'role' => ($m['role'] ?? '') === 'user' ? 'user' : 'assistant',
                'content' => (string) ($m['content'] ?? ''),
            ])
            ->values()
            ->toArray();

        // Release the session lock before the long-running Anthropic HTTP call (P10 fix).
        // Session data is already written; this unblocks other tabs/requests from the same user.
        session()->save();

        $response = Http::timeout(15)->withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => $messages,
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
                'reply' => 'I had trouble processing that. Please try again.',
                'updates' => null,
            ]);
        }

        return response()->json([
            'reply' => $data['reply'],
            'updates' => $data['updates'] ?? null,
        ]);
    }

    private function buildSystemPrompt(array $formData): string
    {
        $current = json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $today = now()->toDateString();

        return 'You are an AI Campaign Assistant for MadData, a digital advertising platform in Israel.'."\n\n"
            .'CURRENT FORM STATE:'."\n".$current."\n\n"
            .'You help campaign managers configure campaigns by interpreting briefs and natural-language instructions (usually in Hebrew).'."\n\n"
            .'UPDATABLE FIELDS:'."\n"
            .'- name (string): Campaign name'."\n"
            .'- budget (number): Total budget in NIS'."\n"
            .'- expected_impressions (number): Expected impressions count'."\n"
            .'- start_date (YYYY-MM-DD): Campaign start date'."\n"
            .'- end_date (YYYY-MM-DD): Campaign end date'."\n"
            .'- status: "active" or "paused" ONLY. These are the only valid values.'."\n"
            .'- genders: array subset of ["Male","Female","unknown"]. IMPORTANT: use exact capitalization "Male" and "Female". (Hebrew: "גברים"=Male, "נשים"=Female. Empty array [] means All genders.)'."\n"
            .'- ages: array subset of ["13-17","18-24","25-34","35-44","45-54","55-64","65+","Unknown"]. Map logically, e.g. 21-30 → ["18-24","25-34"]. Empty array [] means All ages.'."\n"
            .'- incomes: array subset of ["0-195K","195-220K","220-245K","245K+"]. Hebrew: "בינונית ומעלה" = ["195-220K","220-245K","245K+"]. Empty array [] means All incomes.'."\n"
            .'- environments: array subset of ["In-App","Mobile Web"] — empty array [] means All environments'."\n"
            .'- days: array subset of ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"]. Hebrew days: א=Sun, ב=Mon, ג=Tue, ד=Wed, ה=Thu, ו=Fri, ש=Sat. Empty array [] means All days.'."\n"
            .'- timeStart: time in HH:MM format (e.g. "09:00"). Empty string means no restriction.'."\n"
            .'- timeEnd: time in HH:MM format (e.g. "20:00"). Empty string means no restriction.'."\n"
            .'- countries: array of country names (default: ["Israel"])'."\n"
            .'- regions: array of region names. Israeli regions: "גוש דן"=["Tel Aviv","Central"], "צפון"="North", "דרום"="South", "ירושלים"="Jerusalem", "חיפה"="Haifa", "השרון"="Sharon", "שפלה"="Shfela"'."\n"
            .'- cities: array of city names in English'."\n"
            .'NOTE: deviceTypes, os, and connectionTypes cannot be changed by the assistant.'."\n"
            .'If the brief mentions target audiences, suggest relevant audience categories in your reply text, but do NOT return audience_ids in updates — the user will pick audiences manually from the UI.'."\n\n"
            .'Today\'s date is '.$today.'.'."\n\n"
            .'You MUST respond with a valid JSON object only — no markdown fences, no extra text:'."\n"
            .'{"reply":"A friendly 1-2 sentence confirmation in Hebrew explaining what you updated. Do not use emojis.","updates":{...} or null}'."\n\n"
            .'Rules:'."\n"
            .'- Only include fields you are actually changing in "updates"'."\n"
            .'- For array fields, always return the complete desired array (not a diff)'."\n"
            .'- If nothing needs changing or the request is ambiguous, set "updates" to null and ask for clarification in "reply"'."\n"
            .'- Translate Hebrew briefs accurately to the allowed English values'."\n"
            .'- IMPORTANT: Always include ALL requested changes. If the user asks to update budget, dates, targeting AND audiences in one message, include ALL of them in updates. Never skip a field.';
    }
}
