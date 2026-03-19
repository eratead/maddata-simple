<x-auth-split-layout title="Set a new password">

  <div>
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-8">
      <div class="w-10 h-10 rounded-xl bg-[#F97316]/10 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-[#F97316]" fill="none" viewBox="0 0 24 24">
          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
        </svg>
      </div>
      <div>
        <h2 class="text-xl font-black text-gray-900 leading-tight">Set a new password</h2>
        <p class="text-xs text-gray-400 mt-0.5">Choose something strong and memorable</p>
      </div>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
      @csrf
      <input type="hidden" name="token" value="{{ $request->route('token') }}">

      {{-- Email --}}
      <div>
        <label for="email" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
          Email address
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8l7.45 5.42a1 1 0 0 0 1.1 0L20 8M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/>
            </svg>
          </span>
          <input id="email" name="email" type="email"
                 value="{{ old('email', $request->email) }}"
                 required autofocus autocomplete="username"
                 placeholder="you@company.com"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border rounded-lg text-sm text-gray-800 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors {{ $errors->has('email') ? 'border-red-300' : 'border-gray-200' }}">
        </div>
        @error('email')
          <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
        @enderror
      </div>

      {{-- New password --}}
      <div>
        <label for="password" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
          New password
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
            </svg>
          </span>
          <input id="password" name="password" type="password"
                 required autocomplete="new-password"
                 placeholder="••••••••"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border rounded-lg text-sm text-gray-800 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors {{ $errors->has('password') ? 'border-red-300' : 'border-gray-200' }}">
        </div>
        @error('password')
          <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
        @enderror
      </div>

      {{-- Confirm password --}}
      <div>
        <label for="password_confirmation" class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1.5">
          Confirm password
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
          </span>
          <input id="password_confirmation" name="password_confirmation" type="password"
                 required autocomplete="new-password"
                 placeholder="••••••••"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border rounded-lg text-sm text-gray-800 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors {{ $errors->has('password_confirmation') ? 'border-red-300' : 'border-gray-200' }}">
        </div>
        @error('password_confirmation')
          <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
        @enderror
      </div>

      {{-- Submit --}}
      <button type="submit"
              class="w-full py-2.5 px-4 bg-[#F97316] hover:bg-[#EA580C] text-white text-sm font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#F97316]/50 cursor-pointer">
        Reset Password
      </button>

      {{-- Back to login --}}
      <p class="text-center text-xs text-gray-400">
        Back to
        <a href="{{ route('login') }}" class="text-[#F97316] hover:text-[#EA580C] font-medium transition-colors">
          Sign in
        </a>
      </p>

    </form>
  </div>

</x-auth-split-layout>
