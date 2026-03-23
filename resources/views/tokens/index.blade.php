<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">API Tokens</h1>
@endpush

    <x-flash-messages />

    @if (session('token'))
        <div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-emerald-800">Your new token has been created.</p>
                    <p class="text-xs text-emerald-700 mt-0.5">This token will only be shown once. Copy and store it securely now.</p>
                    <pre class="mt-3 bg-white border border-emerald-200 rounded-lg p-3 text-xs font-mono text-gray-800 break-all whitespace-pre-wrap select-all shadow-inner"><code>{{ session('token') }}</code></pre>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-5">

        {{-- Generate Token --}}
        <x-page-box class="p-6">
            <div class="flex items-center gap-2 mb-4 pb-4 border-b border-gray-100">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                <h2 class="text-sm font-semibold text-gray-700">Generate New Token</h2>
            </div>

            <form action="{{ route('tokens.create') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <div class="flex-1">
                    <x-input-label for="token_name" value="Token Name" class="sr-only" />
                    <x-text-input id="token_name" name="token_name" type="text" required placeholder="e.g. Production Data Sync" />
                </div>
                <x-primary-button type="submit">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Token
                </x-primary-button>
            </form>
        </x-page-box>

        {{-- How to use --}}
        <x-page-box class="p-6 bg-gray-50">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h2 class="text-sm font-semibold text-gray-700">How to use your token</h2>
            </div>
            <p class="text-sm text-gray-500 mb-3">Include the token as a Bearer value in the <code class="px-1 py-0.5 bg-gray-200 rounded text-xs font-mono text-gray-700">Authorization</code> header of your API requests.</p>
            <pre class="bg-[#1e293b] text-gray-300 p-4 rounded-lg text-xs overflow-x-auto border border-gray-800"><code><span class="text-pink-400">Authorization:</span> Bearer YOUR_APPLICATION_TOKEN</code></pre>
        </x-page-box>

        {{-- Active Tokens --}}
        <x-page-box class="overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">Active Tokens</h2>
            </div>

            @if($tokens->isEmpty())
                <div class="px-4 py-12 text-center text-sm text-gray-400">
                    No API tokens yet. Create one above.
                </div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($tokens as $token)
                        @php
                            $expiresIn = $token->expires_at
                                ? floor(now()->diffInDays($token->expires_at, false))
                                : null;
                            $isExpired = $expiresIn !== null && $expiresIn <= 0;
                        @endphp
                        <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-[#F97316]/5 border border-[#F97316]/20 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                    </svg>
                                </div>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-800">{{ $token->name }}</span>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        @if ($expiresIn !== null)
                                            @if($isExpired)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-100">Expired</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">Expires in {{ $expiresIn }}d</span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">No expiry</span>
                                        @endif
                                        <span class="text-xs text-gray-400">Created {{ $token->created_at->format('M j, Y') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 ml-11 sm:ml-0">
                                <form action="{{ route('tokens.extend', $token->id) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center px-2.5 py-1 border border-gray-200 rounded-md text-xs font-semibold text-gray-500 hover:text-[#F97316] hover:border-[#F97316]/30 hover:bg-[#F97316]/5 transition-colors">
                                        Extend 30d
                                    </button>
                                </form>

                                <form id="delete-token-{{ $token->id }}" action="{{ route('tokens.destroy', $token->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            @click="$dispatch('confirm-action', {
                                                title:        'Revoke token?',
                                                message:      @js($token->name) + ' will be permanently revoked. Applications using it will immediately lose access.',
                                                confirmLabel: 'Revoke',
                                                form:         document.getElementById('delete-token-{{ $token->id }}')
                                            })"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                                            title="Revoke Token">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-page-box>

    </div>

</x-app-layout>
