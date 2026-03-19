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
  </div>

</x-auth-split-layout>
