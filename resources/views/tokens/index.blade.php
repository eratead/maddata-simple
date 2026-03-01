<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto flex flex-col h-full">

            <!-- Split Header -->
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <!-- Breadcrumbs -->
                    <nav class="flex items-center text-[0.8rem] text-gray-400 mb-2 mt-4 md:mt-0 font-medium tracking-wide">
                        <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <a href="{{ route('profile.edit') }}" class="hover:text-primary transition-colors">Settings</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <span class="text-gray-600">Tokens</span>
                    </nav>

                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">API Tokens</h1>
                    <p class="text-sm text-gray-500 mt-2">Manage your active authentication keys for integrating external applications.</p>
                </div>
            </div>

            <!-- Alerts -->
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50/80 border border-red-200 text-red-700 rounded-xl flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            @if (session('token'))
                <div class="mb-6 p-5 bg-green-50/80 border border-green-200 text-green-800 rounded-xl shadow-sm">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mr-3 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <strong class="font-semibold block">Your new authorization token:</strong>
                            <p class="mt-1 text-green-700/80 text-sm">
                                This token will only be shown once. Please copy and store it securely immediately.
                            </p>
                            <div class="mt-3 bg-white border border-green-200 rounded text-sm p-3 font-mono text-gray-800 break-all select-all shadow-inner">
                                {{ session('token') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-6">

                <!-- Generate Token Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100  p-4 sm:p-6  md:p-8">
                    <h3 class="text-lg font-semibold tracking-tight text-gray-900 mb-4">Generate New Token</h3>
                    
                    <form action="{{ route('tokens.create') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                        @csrf
                        <div class="flex-1 relative">
                            <label for="token_name" class="sr-only">Token Name</label>
                            <input type="text" name="token_name" id="token_name" required placeholder="e.g. Production Data Sync"
                                class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                        </div>
                        <button type="submit" class="inline-flex justify-center items-center px-6 py-2.5 bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-primary/50 whitespace-nowrap">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Create Token
                        </button>
                    </form>
                </div>

                <!-- API Usage Notice -->
                 <div class="bg-gray-50 border border-gray-200 rounded-xl  p-4 sm:p-6  text-sm text-gray-700">
                    <h4 class="font-semibold text-gray-900 flex items-center mb-2">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        How to use your token
                    </h4>
                    <p class="mb-3 text-gray-600">Use this token as a Bearer token in the <code>Authorization</code> header when making external requests to the API.</p>
                    <div class="bg-[#1e293b] text-gray-300 p-4 rounded-lg font-mono text-xs overflow-x-auto shadow-inner border border-gray-800">
<span class="text-pink-400">Authorization:</span> Bearer YOUR_APPLICATION_TOKEN
                    </div>
                </div>

                <!-- Active Tokens List Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 group overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-[0.9rem] font-semibold text-gray-800 tracking-tight">Active Tokens</h3>
                    </div>
                    
                    @if($tokens->isEmpty())
                        <div class="p-8 text-center text-gray-500 bg-gray-50/30">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            <p class="text-sm">You haven't generated any integration API tokens yet.</p>
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
                                <li class="p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-gray-50/50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 border border-blue-100">
                                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                        </div>
                                        <div>
                                            <span class="block font-medium text-gray-900 text-sm">{{ $token->name }}</span>
                                            <div class="flex items-center gap-2 mt-1">
                                                @if ($expiresIn !== null)
                                                    @if($isExpired)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[0.65rem] font-medium bg-red-100 text-red-700 tracking-wide border border-red-200">
                                                            EXPIRED
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[0.65rem] font-medium bg-gray-100 text-gray-600 tracking-wide border border-gray-200">
                                                            Expires in {{ $expiresIn }} days
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[0.65rem] font-medium bg-gray-100 text-gray-600 tracking-wide border border-gray-200">
                                                        No expiration
                                                    </span>
                                                @endif
                                                <span class="text-xs text-gray-400">&bull; Created {{ $token->created_at->format('M j, Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 ml-11 sm:ml-0">
                                        <form action="{{ route('tokens.extend', $token->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-md text-xs font-semibold text-gray-600 uppercase tracking-wider hover:bg-gray-50 hover:text-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30">
                                                Extend 30d
                                            </button>
                                        </form>

                                        <form action="{{ route('tokens.destroy', $token->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this token? Applications using it will immediately lose access!');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-white border border-gray-200 rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500/30" title="Revoke Token">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

            </div>
        </div>
    </main>
</x-app-layout>
