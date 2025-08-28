<x-app-layout>
        <x-title>API Tokens</x-title>
        <x-page-box>
                @if (session('token'))
                        <div class="bg-green-100 text-green-800 p-4 rounded mb-4 text-sm">
                                <strong>Your new token:</strong>
                                <p class="mt-1 text-gray-700 text-xs">
                                        This token will only be shown once. Please copy and store it securely.
                                </p>
                                <code class="block break-all mt-1">{{ session('token') }}</code>
                        </div>
                @endif

                <form action="{{ route('tokens.create') }}" method="POST" class="mb-6">
                        @csrf
                        <label for="token_name" class="block font-medium mb-1 text-sm">Token Name:</label>
                        <input type="text" name="token_name" id="token_name" required
                                class="w-full border rounded px-3 py-2 mb-2 text-sm">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Create
                                Token</button>
                </form>

                <h3 class="text-md font-semibold mb-2">Your Tokens</h3>
                <ul class="list-disc pl-6 text-sm">
                        @forelse ($tokens as $token)
                                <li class="flex items-center justify-between">
                                        <span>
                                                {{ $token->name }}
                                                @php
                                                        $expiresIn = $token->expires_at
                                                            ? floor(now()->diffInDays($token->expires_at, false))
                                                            : null;
                                                @endphp
                                                <span class="text-gray-500 text-xs">
                                                        @if ($expiresIn !== null)
                                                                (Expires in {{ $expiresIn }} days)
                                                        @else
                                                                (No expiration)
                                                        @endif
                                                </span>
                                                <form action="{{ route('tokens.extend', $token->id) }}" method="POST"
                                                        class="inline ml-2">
                                                        @csrf
                                                        <button type="submit" class="text-blue-600 text-xs">Extend 30
                                                                Days</button>
                                                </form>
                                        </span>
                                        <form action="{{ route('tokens.destroy', $token->id) }}" method="POST"
                                                onsubmit="return confirm('Are you sure?');" class="ml-4">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 text-xs">Delete</button>
                                        </form>
                                </li>
                        @empty
                                <li class="text-gray-500">You have no tokens yet.</li>
                        @endforelse
                </ul>
                <div class="mt-6 text-sm text-gray-700">
                        <h4 class="font-semibold mb-1">How to use your token:</h4>
                        <p>Use this token as a Bearer token in the Authorization header when making API requests.</p>
                        <pre class="bg-gray-100 p-2 mt-2 rounded text-xs">
Authorization: Bearer YOUR_TOKEN_HERE
                    </pre>
                </div>
        </x-page-box>
</x-app-layout>
