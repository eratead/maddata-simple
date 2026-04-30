<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function assistantEditorUser(): User
{
    $role = Role::create([
        'name' => 'Assistant Editor '.uniqid(),
        'permissions' => ['can_edit_campaigns' => true],
        'sort_order' => 99,
    ]);

    return User::factory()->create([
        'is_admin' => false,
        'role_id' => $role->id,
        'is_active' => true,
    ]);
}

function validChatPayload(array $overrides = []): array
{
    return array_merge([
        'chatHistory' => [
            ['role' => 'user', 'content' => 'Add cities Holon and Bat Yam'],
        ],
        'currentFormData' => ['name' => 'Test Campaign'],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Successful assistant call — request + response both logged
// ---------------------------------------------------------------------------

it('logs assistant.request and assistant.response to the ai channel on a successful call', function () {
    $cannedResponse = json_encode([
        'reply' => 'הוספתי את הערים חולון ובת ים.',
        'updates' => ['cities' => ['Holon', 'Bat Yam']],
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => $cannedResponse]],
        ], 200),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'context' => $event->context];
    });

    $user = assistantEditorUser();

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertOk()
        ->assertJsonStructure(['reply', 'updates']);

    $messageNames = array_column($messages, 'message');

    expect($messageNames)->toContain('assistant.request');
    expect($messageNames)->toContain('assistant.response');

    // Verify updates_keys includes 'cities'
    $responseLog = collect($messages)
        ->firstWhere('message', 'assistant.response');

    expect($responseLog['context']['updates_keys'])->toContain('cities');
});

// ---------------------------------------------------------------------------
// Malformed JSON — parse failure → 502 + warning logged
// ---------------------------------------------------------------------------

it('returns 502 and logs assistant.parse_failure when the LLM returns malformed JSON', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'This is not JSON at all!']],
        ], 200),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'level' => $event->level];
    });

    $user = assistantEditorUser();

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertStatus(502);

    $messageNames = array_column($messages, 'message');
    expect($messageNames)->toContain('assistant.parse_failure');

    $parseLog = collect($messages)->firstWhere('message', 'assistant.parse_failure');
    expect($parseLog['level'])->toBe('warning');
});

// ---------------------------------------------------------------------------
// Anthropic HTTP exception — upstream error → 502 + error logged
// ---------------------------------------------------------------------------

it('returns 502 and logs assistant.upstream_error when Anthropic throws a connection exception', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([], 500),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'level' => $event->level];
    });

    $user = assistantEditorUser();

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertStatus(502);

    $messageNames = array_column($messages, 'message');
    expect($messageNames)->toContain('assistant.upstream_error');

    $errorLog = collect($messages)->firstWhere('message', 'assistant.upstream_error');
    expect($errorLog['level'])->toBe('error');
});

// ---------------------------------------------------------------------------
// Request log includes correct metadata
// ---------------------------------------------------------------------------

it('logs user_id and message_count in the assistant.request entry', function () {
    $cannedResponse = json_encode([
        'reply' => 'בוצע.',
        'updates' => null,
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => $cannedResponse]],
        ], 200),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'context' => $event->context];
    });

    $user = assistantEditorUser();

    $history = [
        ['role' => 'user', 'content' => 'hello'],
        ['role' => 'assistant', 'content' => 'hi'],
        ['role' => 'user', 'content' => 'add cities'],
    ];

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), [
            'chatHistory' => $history,
            'currentFormData' => ['name' => 'Test'],
        ])
        ->assertOk();

    $requestLog = collect($messages)->firstWhere('message', 'assistant.request');

    expect($requestLog)->not->toBeNull();
    expect($requestLog['context']['user_id'])->toBe($user->id);
    expect($requestLog['context']['message_count'])->toBe(3);
    expect($requestLog['context']['last_user_message_length'])->toBe(strlen('add cities'));
});

// ---------------------------------------------------------------------------
// Response with null updates — updates_keys is an empty array
// ---------------------------------------------------------------------------

it('flattens updates wrapped under "targeting" so applyUpdates() sees the fields at the top level', function () {
    // Reproduces the production bug found via the new ai log channel:
    // Claude returned {"updates":{"targeting":{"cities":["חולון","בת ים"]}}}
    // The frontend looks for updates.cities at the top level, so the cities
    // were silently dropped. The controller now defensively lifts wrapper keys.
    $cannedResponse = json_encode([
        'reply' => 'הוספתי את הערים.',
        'updates' => [
            'targeting' => [
                'cities' => ['חולון', 'בת ים', 'ראשון לציון'],
                'regions' => ['Tel Aviv'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => $cannedResponse]],
        ], 200),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'context' => $event->context];
    });

    $user = assistantEditorUser();

    $response = $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertOk();

    // Top-level cities and regions appear in the JSON returned to the frontend.
    expect($response->json('updates.cities'))->toBe(['חולון', 'בת ים', 'ראשון לציון']);
    expect($response->json('updates.regions'))->toBe(['Tel Aviv']);
    expect($response->json('updates.targeting'))->toBeNull();

    // Logging captures both the raw nested shape and the flattened keys, so a
    // future operator can see at a glance whether the LLM is still wrapping.
    $responseLog = collect($messages)->firstWhere('message', 'assistant.response');
    expect($responseLog['context']['updates_keys'])->toContain('cities')->toContain('regions');
    expect($responseLog['context']['raw_updates_keys'])->toBe(['targeting']);
});

it('logs an empty updates_keys array when the LLM returns updates: null', function () {
    $cannedResponse = json_encode([
        'reply' => 'אני לא מבין. נסה שוב.',
        'updates' => null,
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => $cannedResponse]],
        ], 200),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'context' => $event->context];
    });

    $user = assistantEditorUser();

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertOk();

    $responseLog = collect($messages)->firstWhere('message', 'assistant.response');

    expect($responseLog)->not->toBeNull();
    expect($responseLog['context']['updates_keys'])->toBe([]);
});

// ---------------------------------------------------------------------------
// Prose-wrapped JSON — model emits chain-of-thought before the JSON object
// ---------------------------------------------------------------------------

it('extracts the JSON object even when the model prepends prose before it', function () {
    // Reproduces a production parse_failure (2026-04-29):
    // For complex Hebrew briefs the model occasionally emits markdown
    // chain-of-thought ("I need to map this brief carefully:\n\n**Demographics:**...")
    // and only THEN the JSON object, despite the system prompt forbidding it.
    // The parser must locate the outermost {...} block and decode it.
    $jsonPayload = json_encode([
        'reply' => 'עדכנתי את הפרטים.',
        'updates' => [
            'ages' => ['18-24', '25-34', '35-44'],
            'cities' => ['חולון', 'בת ים', 'באר שבע'],
            'audience_ids' => [333, 334, 335],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $proseWrapped = "I need to map this brief carefully:\n\n"
        ."**Demographics:** Male + Female, ages 21-40 → [\"18-24\",\"25-34\",\"35-44\"]\n\n"
        ."**Audiences:** Education + Technology + Life Sciences\n\n"
        .$jsonPayload;

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => $proseWrapped]],
        ], 200),
    ]);

    $user = assistantEditorUser();

    $response = $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertOk();

    expect($response->json('reply'))->toBe('עדכנתי את הפרטים.');
    expect($response->json('updates.cities'))->toContain('חולון');
    expect($response->json('updates.audience_ids'))->toBe([333, 334, 335]);
});

// ---------------------------------------------------------------------------
// Prompt caching: cacheable system block sent with 1h TTL cache_control,
// dynamic block (form state + date) sent without it, beta header set.
// ---------------------------------------------------------------------------

it('sends the static system block with 1h cache_control and the dynamic block without it', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => '{"reply":"ok","updates":null}']],
        ], 200),
    ]);

    $user = assistantEditorUser();

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertOk();

    Http::assertSent(function ($request) {
        if ($request->header('anthropic-beta')[0] !== 'extended-cache-ttl-2025-04-11') {
            return false;
        }

        $body = $request->data();

        if (! is_array($body['system'] ?? null) || count($body['system']) !== 2) {
            return false;
        }

        [$cacheable, $dynamic] = $body['system'];

        return ($cacheable['type'] ?? null) === 'text'
            && ($cacheable['cache_control']['type'] ?? null) === 'ephemeral'
            && ($cacheable['cache_control']['ttl'] ?? null) === '1h'
            && str_contains($cacheable['text'] ?? '', 'AVAILABLE AUDIENCES')
            && ($dynamic['type'] ?? null) === 'text'
            && ! isset($dynamic['cache_control'])
            && str_contains($dynamic['text'] ?? '', 'CURRENT FORM STATE');
    });
});

it('logs cache_creation_tokens and cache_read_tokens from the Anthropic usage block', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => '{"reply":"ok","updates":null}']],
            'usage' => [
                'input_tokens' => 250,
                'output_tokens' => 30,
                'cache_creation_input_tokens' => 7800,
                'cache_read_input_tokens' => 0,
            ],
        ], 200),
    ]);

    $messages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$messages) {
        $messages[] = ['message' => $event->message, 'context' => $event->context];
    });

    $user = assistantEditorUser();

    $this->actingAs($user)
        ->postJson(route('ai.campaign-assistant'), validChatPayload())
        ->assertOk();

    $responseLog = collect($messages)->firstWhere('message', 'assistant.response');

    expect($responseLog['context']['cache_creation_tokens'])->toBe(7800);
    expect($responseLog['context']['cache_read_tokens'])->toBe(0);
    expect($responseLog['context']['input_tokens'])->toBe(250);
    expect($responseLog['context']['output_tokens'])->toBe(30);
});
