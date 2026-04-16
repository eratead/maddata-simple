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
