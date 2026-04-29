<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CampaignAssistantController extends Controller
{
    public function chat(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('can_edit_campaigns'), 403);

        $validated = $request->validate([
            'chatHistory' => ['required', 'array', 'max:50'],
            'chatHistory.*.role' => ['required', 'string', 'in:user,assistant'],
            'chatHistory.*.content' => ['required', 'string', 'max:5000'],
            'currentFormData' => 'required|array',
        ]);

        $lastUserMessage = collect($validated['chatHistory'])
            ->where('role', 'user')
            ->last();

        Log::channel('ai')->info('assistant.request', [
            'user_id' => auth()->id(),
            'message_count' => count($validated['chatHistory']),
            'last_user_message_length' => $lastUserMessage ? strlen($lastUserMessage['content']) : 0,
        ]);

        $systemPrompt = $this->buildSystemPrompt($validated['currentFormData']);

        $messages = collect($validated['chatHistory'])
            ->map(fn ($m) => [
                'role' => ($m['role'] ?? '') === 'user' ? 'user' : 'assistant',
                'content' => (string) ($m['content'] ?? ''),
            ])
            ->values()
            ->toArray();

        // Release the session lock before the long-running Anthropic HTTP call (P10 fix).
        // Session data is already written; this unblocks other tabs/requests from the same user.
        session()->save();

        try {
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
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            Log::channel('ai')->error('assistant.upstream_error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'AI request failed.'], 502);
        }

        if ($response->failed()) {
            Log::channel('ai')->error('assistant.upstream_error', [
                'user_id' => auth()->id(),
                'http_status' => $response->status(),
                'body_excerpt' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return response()->json(['error' => 'AI request failed.'], 502);
        }

        $rawText = $response->json('content.0.text', '{}');

        // Strip markdown code fences if the model included them
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode(trim($raw), true);

        if (! is_array($data) || ! isset($data['reply'])) {
            Log::channel('ai')->warning('assistant.parse_failure', [
                'user_id' => auth()->id(),
                'raw_text_length' => strlen($rawText),
            ]);

            return response()->json(['error' => 'AI returned an unexpected response format.'], 502);
        }

        $updates = $data['updates'] ?? null;

        // Defensive flatten: some LLM responses wrap fields under "targeting"
        // (or other groupers) despite the prompt forbidding it. Lift any
        // recognised wrapper's keys to the top level so applyUpdates() sees them.
        if (is_array($updates)) {
            foreach (['targeting', 'geo', 'demographics', 'location', 'audience'] as $wrapper) {
                if (isset($updates[$wrapper]) && is_array($updates[$wrapper])) {
                    $wrapped = $updates[$wrapper];
                    unset($updates[$wrapper]);
                    $updates = array_merge($updates, $wrapped);
                }
            }
        }

        Log::channel('ai')->info('assistant.response', [
            'user_id' => auth()->id(),
            'reply_length' => strlen($data['reply']),
            'updates_keys' => is_array($updates) ? array_keys($updates) : [],
            'raw_updates_keys' => is_array($data['updates'] ?? null) ? array_keys($data['updates']) : [],
            'raw_text_length' => strlen($rawText),
        ]);

        return response()->json([
            'reply' => $data['reply'],
            'updates' => $updates,
        ]);
    }

    private function buildSystemPrompt(array $formData): string
    {
        $current = json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $today = now()->toDateString();

        $availableAudiences = Cache::remember('active_audiences', 3600, fn () => Audience::where('is_active', true)
            ->orderBy('main_category')
            ->orderBy('sub_category')
            ->orderBy('name')
            ->get(['id', 'main_category', 'sub_category', 'name']))
            ->toJson(JSON_UNESCAPED_UNICODE);

        return 'You are an AI Campaign Assistant for MadData, a digital advertising platform in Israel.'."\n\n"
            .'CURRENT FORM STATE:'."\n".$current."\n\n"
            .'You help campaign managers configure campaigns by interpreting briefs and natural-language instructions (usually in Hebrew).'."\n\n"
            .'AVAILABLE AUDIENCES (pick IDs from this list for audience_ids):'."\n".$availableAudiences."\n\n"
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
            .'- cities: array of city names. Preserve the script the user used: Hebrew names stay Hebrew (e.g. "חולון"), English names stay English ("Holon"). Do NOT translate. Both are valid and may coexist.'."\n"
            .'- audience_ids: array of integer IDs chosen from AVAILABLE AUDIENCES above. Pick the best matches for the brief\'s target demographic. Be selective — pick only the most relevant audiences.'."\n"
            .'NOTE: deviceTypes, os, and connectionTypes cannot be changed by the assistant.'."\n\n"
            .'Today\'s date is '.$today.'.'."\n\n"
            .'You MUST respond with a valid JSON object only — no markdown fences, no extra text:'."\n"
            .'{"reply":"A friendly 1-2 sentence confirmation in Hebrew explaining what you updated. Do not use emojis.","updates":{...} or null}'."\n\n"
            .'CRITICAL JSON STRUCTURE RULES:'."\n"
            .'- Place updatable fields DIRECTLY inside "updates" at the top level. Example: {"updates":{"cities":["חולון","בת ים"],"budget":5000}}'."\n"
            .'- DO NOT wrap fields in any sub-object. NEVER use "targeting", "geo", "demographics", "location", "audience", or any other wrapper key. Wrong: {"updates":{"targeting":{"cities":[...]}}}. Right: {"updates":{"cities":[...]}}.'."\n\n"
            .'Rules:'."\n"
            .'- Only include fields you are actually changing in "updates"'."\n"
            .'- For array fields, always return the complete desired array (not a diff)'."\n"
            .'- If nothing needs changing or the request is ambiguous, set "updates" to null and ask for clarification in "reply"'."\n"
            .'- Translate Hebrew briefs accurately to the allowed English values'."\n"
            .'- IMPORTANT: Always include ALL requested changes. If the user asks to update budget, dates, targeting AND audiences in one message, include ALL of them in updates. Never skip a field.';
    }
}
