<section>
    <header class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-blue-50/80 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900 tracking-tight">
                {{ __('Profile Information') }}
            </h2>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __("Update your account's profile information and email address.") }}
            </p>
        </div>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Name</label>
            <input id="name" name="name" type="text" class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            <x-input-error class="mt-2 text-xs" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Email Address</label>
            <input id="email" name="email" type="email" class="w-full px-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]" value="{{ old('email', $user->email) }}" required autocomplete="username" />
            <x-input-error class="mt-2 text-xs" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="flex items-start text-sm">
                        <svg class="w-5 h-5 text-yellow-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div>
                            <p class="text-yellow-800 font-medium">
                                {{ __('Your email address is unverified.') }}
                            </p>
                            <button form="send-verification" class="text-yellow-600 hover:text-yellow-800 underline mt-1 font-medium transition-colors focus:outline-none">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </div>
                    </div>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-xs font-semibold text-green-600 bg-green-50 p-2 rounded border border-green-100">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-primary/50">
                {{ __('Save Changes') }}
            </button>

            @if (session('status') === 'profile-updated')
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
