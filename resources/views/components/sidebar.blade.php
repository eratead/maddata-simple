<aside class="hidden md:flex inset-y-0 left-0 w-32 bg-white shadow-xl z-40 flex-col pt-10 ">

        <div class="flex justify-center mb-6 mx-2">
                <a href="{{ route('campaigns.index') }}"><img src="{{ asset('images/logo.png') }}" alt="Logo"
                                class="">
                </a>
        </div>

        <nav class="flex flex-col  px-4 text-gray-700">
                <a href="{{ route('campaigns.index') }}"
                        class="flex flex-col items-center px-3 pb-1 rounded text-gray-800 {{ request()->routeIs('campaigns.index') ? 'bg-gray-100' : 'hover:bg-gray-100' }}">
                        <img src="{{ asset('images/icons/campaigns.png') }}" alt="Campaigns" class="h-16 mb-1">
                        <span class="text-sm font-medium">Campaigns</span>
                </a>

                <a href="{{ route('dashboard') }}"
                        class="flex flex-col items-center px-3 pb-1 rounded text-gray-800 {{ request()->is('dashboard*') ? 'bg-gray-100' : 'hover:bg-gray-100' }}">
                        <img src="{{ asset('images/icons/report.png') }}" alt="Report" class="h-10 mb-1">
                        <span class="text-sm font-medium">Report</span>
                </a>

                <!-- Manage Clients/Users Dropdown -->
                @if (Auth::user() && Auth::user()->is_admin)
                        <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                                <x-dropdown align="left" width="48" x-bind:open="open">
                                        <x-slot name="trigger">
                                                <button type="button"
                                                        class="inline-flex items-center pl-5 py-2 border border-transparent text-sm font-medium rounded-md text-gray-500 w-full text-center {{ request()->is('clients*') || request()->is('users*') ? 'bg-gray-100' : 'hover:text-gray-700' }} transition">
                                                        <div class="flex flex-col items-center">
                                                                <img src="{{ asset('images/icons/manage.png') }}"
                                                                        alt="Manage" class="h-10 mb-1">
                                                                {{ __('Manage') }}
                                                        </div>
                                                </button>
                                        </x-slot>
                                        <x-slot name="content">
                                                <x-dropdown-link :href="route('clients.index')" :class="request()->is('clients*') ? 'bg-gray-100' : ''">
                                                        {{ __('Clients') }}
                                                </x-dropdown-link>
                                                @auth
                                                        @if (auth()->user()->is_admin)
                                                                <x-dropdown-link :href="route('users.index')" :class="request()->is('users*') ? 'bg-gray-100' : ''">
                                                                        {{ __('Users') }}
                                                                </x-dropdown-link>
                                                        @endif
                                                @endauth
                                        </x-slot>
                                </x-dropdown>
                        </div>
                @endif

                <!-- My Account / Logout Dropdown -->
                <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                        <x-dropdown align="left" width="48" x-bind:open="open">
                                <x-slot name="trigger">
                                        <button type="button"
                                                class="inline-flex items-center pl-5 py-2 border border-transparent text-sm font-medium rounded-md text-gray-500 w-full text-center {{ request()->is('profile*') ? 'bg-gray-100' : 'hover:text-gray-700' }} transition">
                                                <div class="flex flex-col items-center">
                                                        <img src="{{ asset('images/icons/user.png') }}" alt="User"
                                                                class="h-10 mb-1">
                                                        <div style="white-space: nowrap;overflow: hidden;width: 3.5rem;"
                                                                title=" {{ Auth::user()->name }}">
                                                                {{ Auth::user()->name }}
                                                        </div>
                                                </div>
                                        </button>
                                </x-slot>
                                <x-slot name="content">
                                        <x-dropdown-link :href="route('profile.edit')">{{ __('My Account') }}</x-dropdown-link>
                                        @auth
                                                @if (\Illuminate\Support\Facades\Route::has('tokens.index'))
                                                        <x-dropdown-link
                                                                :href="route('tokens.index')">{{ __('API') }}</x-dropdown-link>
                                                @endif
                                        @endauth
                                        <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <x-dropdown-link :href="route('logout')"
                                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                                        {{ __('Log Out') }}
                                                </x-dropdown-link>
                                        </form>
                                </x-slot>
                        </x-dropdown>
                </div>
        </nav>
</aside>
