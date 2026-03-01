<section>
    <header class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-indigo-50/80 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900 tracking-tight">
                {{ __('Update Password') }}
            </h2>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('Ensure your account is using a long, random password to stay secure.') }}
            </p>
        </div>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Current Password</label>
            <input id="update_password_current_password" name="current_password" type="password" class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-xs" />
        </div>

        <div>
            <label for="update_password_password" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">New Password</label>
            <input id="update_password_password" name="password" type="password" class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-xs" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Confirm Password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-xs" />
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-primary/50">
                {{ __('Update Password') }}
            </button>

            @if (session('status') === 'password-updated')
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300 transform"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    x-init="setTimeout(() => show = false, 3000)"
                    class="flex items-center text-sm font-medium text-green-600 bg-green-50 px-3 py-1.5 rounded-lg border border-green-100"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ __('Saved successfully') }}
                </div>
            @endif
        </div>
    </form>
</section>
