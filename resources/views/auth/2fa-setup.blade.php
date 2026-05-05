<x-auth-split-layout title="Set up Two-Factor Authentication">

  <div x-data="{ method: {{ config('auth.google_sso_enabled') ? 'null' : "'totp'" }} }">

    {{-- Header --}}
    <div class="mb-6">
      <h2 class="text-xl font-black text-gray-900 leading-tight">Two-factor authentication</h2>
      <p class="text-xs text-gray-400 mt-1">Two-factor authentication is required. Choose your second factor:</p>
    </div>

    {{-- Card 1: Google account --}}
    @if (config('auth.google_sso_enabled'))
      <div class="border border-gray-200 rounded-xl bg-white p-5 mb-4">
        <div class="flex items-start gap-3 mb-3">
          <div class="w-9 h-9 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center shrink-0">
            <svg class="h-4 w-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-800">Use your Google account</p>
            <p class="text-xs text-gray-500 mt-0.5">Sign in with one click on future logins.</p>
            <p class="text-[11px] text-gray-400 mt-0.5">Recommended for most users.</p>
          </div>
        </div>
        <form method="POST" action="{{ route('2fa.google.start-setup') }}">
          @csrf
          <button type="submit"
                  class="flex w-full items-center justify-center gap-2.5 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/30">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Continue with Google
          </button>
        </form>
      </div>
    @endif

    {{-- Card 2: Authenticator app (TOTP) --}}
    <div class="border border-gray-200 rounded-xl bg-white overflow-hidden">

      {{-- Card header / toggle row --}}
      <div class="flex items-center gap-3 p-5" :class="method === 'totp' ? 'border-b border-gray-100' : ''">
        <div class="w-9 h-9 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 3H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8l-4-5Zm0 0v5h8M8 13h8M8 17h4"/>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold text-gray-800">Use an authenticator app</p>
          <p class="text-xs text-gray-500 mt-0.5">Google Authenticator, Authy, or similar.</p>
        </div>
        <button type="button"
                @click="method = method === 'totp' ? null : 'totp'"
                class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 cursor-pointer"
                x-text="method === 'totp' ? 'Cancel' : 'Use authenticator app'">
        </button>
      </div>

      {{-- Collapsible TOTP body --}}
      <div x-show="method === 'totp'" x-collapse class="px-5 pb-5 pt-4 space-y-5">

        {{-- Step 1: Install app --}}
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-2">Step 1 — Install an authenticator app</p>
          <p class="text-xs text-gray-400 mb-2">
            Use <strong class="text-gray-600">Google Authenticator</strong>, <strong class="text-gray-600">Authy</strong>, or any TOTP-compatible app on your phone.
          </p>
          <div class="flex gap-2">
            <a href="https://apps.apple.com/app/google-authenticator/id388497605"
               target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-1.5 px-2.5 py-1.5 bg-gray-900 hover:bg-gray-700 text-white rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
              </svg>
              <span class="text-[11px] font-medium">App Store</span>
            </a>
            <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
               target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-1.5 px-2.5 py-1.5 bg-gray-900 hover:bg-gray-700 text-white rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 20.5v-17c0-.83 1-.83 1.5-.5l15 8.5c.5.28.5 1 0 1.28l-15 8.5c-.5.33-1.5.33-1.5-.78z" opacity=".3"/><path d="M5 3.64 17.05 12 5 20.36V3.64zM3 2v20c0 1.1 1.2 1.65 2.03 1l15-8.5c.72-.4.72-1.6 0-2L5.03 1C4.2.35 3 .9 3 2z"/>
              </svg>
              <span class="text-[11px] font-medium">Google Play</span>
            </a>
          </div>
        </div>

        {{-- Step 2: Scan QR --}}
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-2">Step 2 — Scan this QR code</p>
          <div class="flex justify-center mb-3">
            <div class="p-3 bg-white border border-gray-200 rounded-xl shadow-sm inline-block">
              {!! $qrCodeSvg !!}
            </div>
          </div>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1 text-center">
            Or enter manually
          </p>
          <div class="flex items-center justify-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
            <code class="text-xs font-mono text-gray-700 tracking-widest select-all">{{ $secret }}</code>
            <button type="button"
                    onclick="navigator.clipboard.writeText('{{ $secret }}').then(() => { this.textContent = '✓'; setTimeout(() => this.textContent = 'Copy', 1500) })"
                    class="text-[10px] font-semibold text-[#F97316] hover:text-[#EA580C] whitespace-nowrap cursor-pointer">
              Copy
            </button>
          </div>
        </div>

        {{-- Step 3: Verify --}}
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-2">Step 3 — Enter the 6-digit code to confirm</p>
          <form method="POST" action="{{ route('2fa.confirm') }}">
            @csrf
            <div class="mb-3">
              <input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}"
                     maxlength="6" required autocomplete="one-time-code"
                     placeholder="000 000"
                     class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-center text-gray-800 tracking-widest placeholder-gray-300 font-mono focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors @error('code') border-red-300 @enderror">
              @error('code')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
              @enderror
            </div>
            <button type="submit"
                    class="w-full py-2.5 px-4 bg-[#F97316] hover:bg-[#EA580C] text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/50 cursor-pointer">
              Activate Two-Factor Auth
            </button>
          </form>
        </div>

      </div>
    </div>

  </div>

</x-auth-split-layout>
