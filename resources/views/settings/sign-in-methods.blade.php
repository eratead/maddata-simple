<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800 truncate">Sign-in Methods</h1>
@endpush

<div class="p-6 max-w-2xl mx-auto space-y-6">

    <x-page-header title="Sign-in Methods" description="Manage how you log in to MadData."></x-page-header>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Email + Password row ─────────────────────────────────────────── --}}
    <x-page-box>
        <div class="px-6 py-5 flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m22 6-10 7L2 6"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Email + Password</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $user->email }}</p>
                </div>
            </div>
            <div class="shrink-0">
                <a href="{{ route('profile.edit') }}"
                   class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Change password
                </a>
            </div>
        </div>
    </x-page-box>

    {{-- ── Authenticator App (TOTP) row ────────────────────────────────── --}}
    <x-page-box>
        <div class="px-6 py-5 flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-purple-50">
                    <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Authenticator App (TOTP)</p>
                    @if ($user->hasTotpEnrolled())
                        <span class="inline-flex items-center gap-1 mt-0.5 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700">
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Enabled
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 mt-0.5 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">
                            Not set up
                        </span>
                    @endif
                </div>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                @if ($user->hasTotpEnrolled())
                    {{-- Disable TOTP — only allowed when Google is linked --}}
                    @if ($user->hasGoogleLinked())
                        <form x-data="{ open: false }" @submit.prevent>
                            <button type="button" @click="open = true"
                                    class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors">
                                Disable
                            </button>

                            {{-- Confirm password modal --}}
                            <div x-show="open" x-cloak
                                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.stop>
                                    <h3 class="text-base font-semibold text-gray-900 mb-1">Disable Authenticator App</h3>
                                    <p class="text-sm text-gray-500 mb-4">Confirm your password to disable TOTP. You can still sign in with Google.</p>
                                    <form method="POST" action="{{ route('settings.sign-in-methods.disable-totp') }}" class="space-y-4">
                                        @csrf
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
                                                Current password
                                            </label>
                                            <input type="password" name="password" required autofocus
                                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316]">
                                            @error('totp')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="open = false"
                                                    class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                                Disable
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </form>
                    @else
                        {{-- Greyed out with tooltip --}}
                        <div x-data="{ tip: false }" class="relative">
                            <button type="button" @mouseenter="tip = true" @mouseleave="tip = false"
                                    disabled
                                    class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-400 cursor-not-allowed">
                                Disable
                            </button>
                            <div x-show="tip" x-cloak
                                 class="absolute bottom-full right-0 mb-1.5 w-56 rounded-lg bg-gray-800 px-3 py-2 text-xs text-white shadow-lg">
                                Connect Google first so you don't lock yourself out.
                                <div class="absolute right-3 top-full h-0 w-0 border-x-4 border-x-transparent border-t-4 border-t-gray-800"></div>
                            </div>
                        </div>
                    @endif
                @else
                    <a href="{{ route('2fa.setup') }}"
                       class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Set up
                    </a>
                @endif
            </div>
        </div>
    </x-page-box>

    {{-- ── Google Account row ──────────────────────────────────────────── --}}
    @if (config('auth.google_sso_enabled'))
        <x-page-box>
            <div class="px-6 py-5 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50">
                        {{-- Google "G" logo --}}
                        <svg class="h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Google Account</p>
                        @if ($user->hasGoogleLinked())
                            <p class="text-xs text-gray-500 mt-0.5">Connected to: {{ $user->google_email }}</p>
                        @else
                            <span class="inline-flex items-center gap-1 mt-0.5 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">
                                Not connected
                            </span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    @if ($user->hasGoogleLinked())
                        {{-- Disconnect — only allowed when TOTP is enrolled --}}
                        @if ($user->hasTotpEnrolled())
                            <form x-data="{ open: false }" @submit.prevent>
                                <button type="button" @click="open = true"
                                        class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors">
                                    Disconnect
                                </button>

                                {{-- Confirm password modal --}}
                                <div x-show="open" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                    <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.stop>
                                        <h3 class="text-base font-semibold text-gray-900 mb-1">Disconnect Google</h3>
                                        <p class="text-sm text-gray-500 mb-4">Confirm your password to disconnect your Google account.</p>
                                        <form method="POST" action="{{ route('settings.sign-in-methods.disconnect-google') }}" class="space-y-4">
                                            @csrf
                                            <div>
                                                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
                                                    Current password
                                                </label>
                                                <input type="password" name="password" required autofocus
                                                       class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316]">
                                                @error('google')
                                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="open = false"
                                                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                                    Disconnect
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </form>
                        @else
                            {{-- Greyed out with tooltip --}}
                            <div x-data="{ tip: false }" class="relative">
                                <button type="button" @mouseenter="tip = true" @mouseleave="tip = false"
                                        disabled
                                        class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-400 cursor-not-allowed">
                                    Disconnect
                                </button>
                                <div x-show="tip" x-cloak
                                     class="absolute bottom-full right-0 mb-1.5 w-56 rounded-lg bg-gray-800 px-3 py-2 text-xs text-white shadow-lg">
                                    Set up Authenticator first so you don't lock yourself out.
                                    <div class="absolute right-3 top-full h-0 w-0 border-x-4 border-x-transparent border-t-4 border-t-gray-800"></div>
                                </div>
                            </div>
                        @endif
                    @else
                        {{-- Connect Google --}}
                        <form x-data="{ open: false }" @submit.prevent>
                            <button type="button" @click="open = true"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                Connect
                            </button>

                            {{-- Confirm password modal --}}
                            <div x-show="open" x-cloak
                                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.stop>
                                    <h3 class="text-base font-semibold text-gray-900 mb-1">Connect Google Account</h3>
                                    <p class="text-sm text-gray-500 mb-4">Confirm your password, then you'll be redirected to Google to complete the connection.</p>
                                    <form method="POST" action="{{ route('settings.sign-in-methods.start-connect-google') }}" class="space-y-4">
                                        @csrf
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
                                                Current password
                                            </label>
                                            <input type="password" name="password" required autofocus
                                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316]">
                                            @error('password')
                                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="open = false"
                                                    class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                    class="rounded-lg bg-[#F97316] px-4 py-2 text-sm font-medium text-white hover:bg-[#EA580C]">
                                                Continue to Google
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </x-page-box>
    @endif

</div>

</x-app-layout>
