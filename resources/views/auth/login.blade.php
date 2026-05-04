<x-auth-split-layout title="Sign in">

  <div>
    <h2 class="text-2xl font-black text-gray-900 mb-1">Welcome back</h2>
    <p class="text-sm text-gray-400 mb-8">Sign in to your MadData account</p>

    {{-- Session status (e.g. password reset success) --}}
    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
      @csrf

      {{-- Email --}}
      <div>
        <label for="email" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
          Email address
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2Z"/>
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m22 6-10 7L2 6"/>
            </svg>
          </span>
          <input id="email" name="email" type="email" value="{{ old('email') }}"
                 required autofocus autocomplete="username"
                 placeholder="you@company.com"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors">
        </div>
        @error('email')
          <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
        @enderror
      </div>

      {{-- Password --}}
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label for="password" class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
            Password
          </label>
          @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}"
               class="text-[11px] text-[#F97316] hover:text-[#EA580C] font-medium transition-colors">
              Forgot password?
            </a>
          @endif
        </div>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
            </svg>
          </span>
          <input id="password" name="password" type="password"
                 required autocomplete="current-password"
                 placeholder="••••••••"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors">
        </div>
        @error('password')
          <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
        @enderror
      </div>

      {{-- Remember me --}}
      <div class="flex items-center gap-2">
        <input id="remember_me" name="remember" type="checkbox"
               class="w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/30 cursor-pointer">
        <label for="remember_me" class="text-sm text-gray-500 cursor-pointer select-none">
          Remember me
        </label>
      </div>

      {{-- Submit --}}
      <button type="submit"
              class="w-full py-2.5 px-4 bg-[#F97316] hover:bg-[#EA580C] text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/50 cursor-pointer">
        Sign in to MadData
      </button>

    </form>

    {{-- Google SSO (only rendered when the feature flag is on) --}}
    @if (config('auth.google_sso_enabled'))
      <div class="mt-5">
        <div class="relative flex items-center">
          <div class="flex-grow border-t border-gray-200"></div>
          <span class="mx-3 text-xs text-gray-400 shrink-0">or</span>
          <div class="flex-grow border-t border-gray-200"></div>
        </div>

        <a href="{{ route('auth.google.redirect') }}"
           class="mt-4 flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
          {{-- Google "G" logo --}}
          <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Sign in with Google
        </a>
      </div>
    @endif

    {{-- Flash error from SSO callback --}}
    @if (session('error'))
      <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ session('error') }}
      </div>
    @endif
  </div>

</x-auth-split-layout>
