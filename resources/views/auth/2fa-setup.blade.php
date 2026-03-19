<x-auth-split-layout title="Set up Two-Factor Authentication">

  <div>
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
      <div class="w-10 h-10 rounded-xl bg-[#F97316]/10 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-[#F97316]" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
        </svg>
      </div>
      <div>
        <h2 class="text-xl font-black text-gray-900 leading-tight">Secure your account</h2>
        <p class="text-xs text-gray-400 mt-0.5">Two-factor authentication is required</p>
      </div>
    </div>

    {{-- Steps --}}
    <ol class="space-y-5 mb-6">

      {{-- Step 1: Install app --}}
      <li class="flex gap-3">
        <span class="w-5 h-5 rounded-full bg-[#F97316] text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">1</span>
        <div>
          <p class="text-sm font-semibold text-gray-800 mb-0.5">Install an authenticator app</p>
          <p class="text-xs text-gray-400 mb-2">
            Use <strong class="text-gray-600">Google Authenticator</strong>, <strong class="text-gray-600">Authy</strong>, or any TOTP-compatible app on your phone.
          </p>
          <div class="flex gap-2 flex-wrap">
            {{-- Google Authenticator --}}
            <div class="flex flex-col gap-1">
              <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Google Authenticator</p>
              <div class="flex gap-2">
                <a href="https://apps.apple.com/app/google-authenticator/id388497605"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-1.5 px-2.5 py-1.5 bg-gray-900 hover:bg-gray-700 text-white rounded-lg transition-colors">
                  {{-- Apple logo --}}
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                  </svg>
                  <span class="text-[11px] font-medium">App Store</span>
                </a>
                <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-1.5 px-2.5 py-1.5 bg-gray-900 hover:bg-gray-700 text-white rounded-lg transition-colors">
                  {{-- Play Store logo --}}
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 20.5v-17c0-.83 1-.83 1.5-.5l15 8.5c.5.28.5 1 0 1.28l-15 8.5c-.5.33-1.5.33-1.5-.78z" opacity=".3"/><path d="M5 3.64 17.05 12 5 20.36V3.64zM3 2v20c0 1.1 1.2 1.65 2.03 1l15-8.5c.72-.4.72-1.6 0-2L5.03 1C4.2.35 3 .9 3 2z"/>
                  </svg>
                  <span class="text-[11px] font-medium">Google Play</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </li>

      {{-- Step 2: Scan QR --}}
      <li class="flex gap-3">
        <span class="w-5 h-5 rounded-full bg-[#F97316] text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">2</span>
        <div class="flex-1">
          <p class="text-sm font-semibold text-gray-800 mb-3">Scan this QR code</p>

          {{-- QR Code --}}
          <div class="flex justify-center mb-3">
            <div class="p-3 bg-white border border-gray-200 rounded-xl shadow-sm inline-block">
              {!! $qrCodeSvg !!}
            </div>
          </div>

          {{-- Manual key --}}
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
      </li>

      {{-- Step 3: Verify --}}
      <li class="flex gap-3">
        <span class="w-5 h-5 rounded-full bg-[#F97316] text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">3</span>
        <div class="flex-1">
          <p class="text-sm font-semibold text-gray-800 mb-2">Enter the 6-digit code to confirm</p>

          <form method="POST" action="{{ route('2fa.confirm') }}">
            @csrf

            <div class="mb-3">
              <input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}"
                     maxlength="6" required autofocus autocomplete="one-time-code"
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
      </li>
    </ol>

  </div>

</x-auth-split-layout>
