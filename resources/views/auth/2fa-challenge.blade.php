<x-auth-split-layout title="Two-Factor Verification">

  {{-- Single hidden logout form, shared by both branches --}}
  <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>

  <div>
    {{-- Throttle / rate-limit banner (shown when HTTP 429 hits) --}}
    @if($errors->has('throttle'))
      <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
        <div>
          <p class="text-sm font-semibold text-red-700">Too many attempts</p>
          <p class="text-xs text-red-500 mt-0.5">{{ $errors->first('throttle') }}</p>
        </div>
      </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-8">
      <div class="w-10 h-10 rounded-xl bg-[#F97316]/10 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-[#F97316]" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
        </svg>
      </div>
      <div>
        <h2 class="text-xl font-black text-gray-900 leading-tight">Two-step verification</h2>
        <p class="text-xs text-gray-400 mt-0.5">
          @if (auth()->user()->hasTotpEnrolled())
            Enter the code from your authenticator app
          @else
            Verify your identity with Google
          @endif
        </p>
      </div>
    </div>

    {{-- TOTP form — only shown when TOTP is enrolled --}}
    @if (auth()->user()->hasTotpEnrolled())
      <form method="POST" action="{{ route('2fa.verify') }}" class="space-y-5">
        @csrf

        {{-- Code input --}}
        <div>
          <label for="code" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
            Authentication code
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
              </svg>
            </span>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}"
                   maxlength="6" required autofocus autocomplete="one-time-code"
                   placeholder="000 000"
                   class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 tracking-widest placeholder-gray-300 font-mono focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors @error('code') border-red-300 @enderror">
          </div>
          @error('code')
            <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
          @enderror
          <p class="mt-1.5 text-[11px] text-gray-400">
            Open your authenticator app and enter the current 6-digit code.
          </p>
        </div>

        {{-- Remember this device --}}
        <div class="flex items-start gap-3 p-3.5 bg-gray-50 border border-gray-200 rounded-lg">
          <input id="remember_device" name="remember_device" type="checkbox" value="1"
                 class="w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/30 cursor-pointer mt-0.5 shrink-0">
          <label for="remember_device" class="cursor-pointer select-none">
            <span class="block text-sm font-semibold text-gray-700">Remember this device</span>
            <span class="block text-[11px] text-gray-400 mt-0.5">
              Skip this check on this browser for 30 days. Only check this on a personal, trusted device.
            </span>
          </label>
        </div>

        {{-- Submit --}}
        <button type="submit"
                class="w-full py-2.5 px-4 bg-[#F97316] hover:bg-[#EA580C] text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/50 cursor-pointer">
          Verify & Continue
        </button>

        {{-- Sign in as different user --}}
        <p class="text-center text-xs text-gray-400">
          Not you?
          <a href="{{ route('logout') }}"
             onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
             class="text-[#F97316] hover:text-[#EA580C] font-medium transition-colors">
            Sign out
          </a>
        </p>

      </form>

      {{-- Google as alternative 2FA -- shown only when Google is also linked --}}
      @if (config('auth.google_sso_enabled') && auth()->user()->hasGoogleLinked())
        <div class="mt-5">
          <div class="relative flex items-center">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="mx-3 text-xs text-gray-400 shrink-0">or</span>
            <div class="flex-grow border-t border-gray-200"></div>
          </div>
          <form method="POST" action="{{ route('2fa.google.start-verify') }}" class="mt-4">
            @csrf
            <button type="submit"
                    class="flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/30">
              <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              Verify with Google
            </button>
          </form>
        </div>
      @elseif (config('auth.google_sso_enabled'))
        {{-- Discoverability hint: user has TOTP only and could connect Google for a smoother flow next time. --}}
        <div class="mt-5 flex items-start gap-2.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5">
          <svg class="h-4 w-4 shrink-0 text-gray-400 mt-0.5" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/>
          </svg>
          <p class="text-[11px] leading-relaxed text-gray-500">
            Tired of typing codes? After you sign in, head to <span class="font-semibold text-gray-700">Settings → Sign-in methods</span> to connect a Google account. You'll be able to verify with one click on future logins.
          </p>
        </div>
      @endif

    @elseif (config('auth.google_sso_enabled') && auth()->user()->hasGoogleLinked())
      {{-- Google-only: no TOTP enrolled, just show Google button --}}
      <form method="POST" action="{{ route('2fa.google.start-verify') }}" class="space-y-5">
        @csrf
        <button type="submit"
                class="flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/30">
          <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Verify with Google
        </button>

        {{-- Sign in as different user --}}
        <p class="text-center text-xs text-gray-400">
          Not you?
          <a href="{{ route('logout') }}"
             onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
             class="text-[#F97316] hover:text-[#EA580C] font-medium transition-colors">
            Sign out
          </a>
        </p>

      </form>
    @endif
  </div>

</x-auth-split-layout>
