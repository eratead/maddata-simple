<aside class="hidden md:flex inset-y-0 left-0 w-32 bg-white shadow-xl z-40 flex-col pt-10 ">

        <div class="flex justify-center mb-6 mx-2">
                <a href="{{ route('campaigns.index') }}"><img src="{{ asset('images/logo.png') }}" alt="Logo"
                                class="">
                </a>
        </div>

        <nav class="flex flex-col  px-4 text-gray-700">
                <a href="{{ route('campaigns.index') }}"
                        class="flex flex-col items-center px-3 pb-1 rounded hover:bg-gray-100 text-gray-800">
                        <img src="{{ asset('images/icons/campaigns.png') }}" alt="Campaigns" class="h-16 mb-1">
                        <span class="text-sm font-medium">Campaigns</span>
                </a>

                <a href="{{ route('dashboard') }}"
                        class="flex flex-col items-center px-3 pb-1 rounded hover:bg-gray-100 text-gray-800">
                        <img src="{{ asset('images/icons/report.png') }}" alt="Report" class="h-10 mb-1">
                        <span class="text-sm font-medium">Report</span>
                </a>

                <!-- Manage Clients/Users Dropdown -->
                @if (Auth::user() && Auth::user()->is_admin)
                        <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                        <button
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 transition">
                                                <div class="flex flex-col items-center">
                                                        <img src="{{ asset('images/icons/manage.png') }}" alt="Manage"
                                                                class="h-10 mb-1">
                                                        {{ __('Manage') }}
                                                </div>
                                                <svg class="ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                        fill="none" viewBox="0 0 20 20" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M7 7l3-3 3 3m0 6l-3 3-3-3" />
                                                </svg>
                                        </button>
                                </x-slot>
                                <x-slot name="content">
                                        <x-dropdown-link :href="route('clients.index')">{{ __('Clients') }}</x-dropdown-link>
                                        @auth
                                                @if (auth()->user()->is_admin)
                                                        <x-dropdown-link :href="route('users.index')">
                                                                {{ __('Users') }}
                                                        </x-dropdown-link>
                                                @endif
                                        @endauth
                                </x-slot>
                        </x-dropdown>
                @endif

                <!-- My Account / Logout Dropdown -->
                <x-dropdown align="left" width="48">
                        <x-slot name="trigger">
                                <button
                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 transition">
                                        <div class="flex flex-col items-center">
                                                <img src="{{ asset('images/icons/user.png') }}" alt="User"
                                                        class="h-10 mb-1">
                                                <div>{{ Auth::user()->name }}</div>
                                        </div>
                                        <div class="ms-1">
                                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                                clip-rule="evenodd" />
                                                </svg>
                                        </div>
                                </button>
                        </x-slot>
                        <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">{{ __('My Account') }}</x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')"
                                                onclick="event.preventDefault(); this.closest('form').submit();">
                                                {{ __('Log Out') }}
                                        </x-dropdown-link>
                                </form>
                        </x-slot>
                </x-dropdown>
        </nav>
</aside>
