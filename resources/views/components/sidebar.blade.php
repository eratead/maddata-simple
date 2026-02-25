<!-- Mobile Header -->
<div class="md:hidden flex items-center p-4 bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
    <button x-data @click="$dispatch('open-sidebar')"
        class="text-gray-500 focus:outline-none flex items-center justify-center p-1 rounded-md hover:bg-gray-100">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>
    <a href="{{ route('dashboard') }}" class="ml-4 flex items-center">
        <div class="bg-white rounded p-1 flex items-center justify-center h-8">
            <img src="{{ asset('images/logo.png') }}" alt="MadData Logo" class="h-full object-contain">
        </div>
    </a>
</div>

<!-- Sidebar wrapper managed by Alpine for mobile toggle -->
<div x-data="{ sidebarOpen: false }" @open-sidebar.window="sidebarOpen = true" @close-sidebar.window="sidebarOpen = false" class="contents">
    
    <!-- Sidebar Overlay (Mobile) -->
    <div x-show="sidebarOpen" 
         x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 md:hidden backdrop-blur-sm"
         style="display: none;"></div>

    <!-- Sidebar / Aside -->
    <aside id="sidebar"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed lg:sticky top-0 left-0 h-screen w-72 lg:w-[220px] xl:w-64 bg-surface border-r border-border flex flex-col z-40 transform lg:translate-x-0 transition-transform duration-300 ease-in-out shadow-sidebar lg:shadow-none shrink-0 overflow-y-auto hide-scrollbar">

        <!-- Mobile Close -->
        <button @click="sidebarOpen = false"
            class="lg:hidden absolute top-4 right-4 p-2 text-textMuted hover:text-textMain focus:outline-none focus:ring-2 focus:ring-primary/30 rounded-xl transition-colors cursor-pointer"
            aria-label="Close navigation menu">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>

        <!-- Logo -->
        <div class="px-6 py-6 flex items-center border-b border-border/50">
            <a href="{{ route('dashboard') }}">
                <img src="{{ asset('images/logo.png') }}" alt="MadData Logo" class="h-8 w-auto object-contain">
            </a>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-4 px-3 space-y-1 overflow-visible" aria-label="Main navigation">

            <!-- Campaigns -->
            <a href="{{ route('campaigns.index') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 cursor-pointer group {{ request()->routeIs('campaigns.index') ? 'nav-active bg-primaryLight text-primary font-semibold' : 'text-textMuted hover:bg-gray-50 hover:text-textMain' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('campaigns.index') ? '' : 'text-textLight group-hover:text-textMuted transition-colors' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                    </path>
                </svg>
                <span>Campaigns</span>
            </a>

            <!-- Report -->
            <a href="{{ route('dashboard') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 cursor-pointer group {{ request()->is('dashboard*') ? 'nav-active bg-primaryLight text-primary font-semibold' : 'text-textMuted hover:bg-gray-50 hover:text-textMain' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->is('dashboard*') ? '' : 'text-textLight group-hover:text-textMuted transition-colors' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                <span>Report</span>
            </a>

            <!-- Manage Clients/Users Dropdown -->
            @if (Auth::user() && Auth::user()->hasPermission('is_admin'))
                @php
                    $isManageActive = request()->is('clients*') || request()->is('users*') || request()->is('admin/roles*') || request()->is('admin/activity-logs*') || request()->is('admin/campaign-changes*');
                @endphp
                <div x-data="{ open: {{ $isManageActive ? 'true' : 'false' }} }" class="relative">
                    <button @click="open = !open" type="button"
                        class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 cursor-pointer group {{ $isManageActive ? 'nav-active bg-primaryLight text-primary font-semibold' : 'text-textMuted hover:bg-gray-50 hover:text-textMain' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0 {{ $isManageActive ? '' : 'text-textLight group-hover:text-textMuted transition-colors' }}" 
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>Manage</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Accordion Submenu -->
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="bg-gray-50/50 rounded-lg mt-1 ml-2 border-l-2 border-gray-100 pl-2 space-y-1 block">
                        
                        <a href="{{ route('clients.index') }}"
                            class="block px-4 py-2 text-sm rounded-md transition-colors {{ request()->is('clients*') ? 'text-primary font-medium bg-primaryLight/50' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">Clients</a>
                        
                        @auth
                            @if (auth()->user()->hasPermission('is_admin'))
                                <a href="{{ route('users.index') }}"
                                    class="block px-4 py-2 text-sm rounded-md transition-colors {{ request()->is('users*') ? 'text-primary font-medium bg-primaryLight/50' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">Users</a>
                                
                                <a href="{{ route('admin.roles.index') }}"
                                    class="block px-4 py-2 text-sm rounded-md transition-colors {{ request()->is('admin/roles*') ? 'text-primary font-medium bg-primaryLight/50' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">Roles</a>
                                
                                <a href="{{ route('admin.activity-logs.index') }}"
                                    class="block px-4 py-2 text-sm rounded-md transition-colors {{ request()->is('admin/activity-logs*') ? 'text-primary font-medium bg-primaryLight/50' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">Activity Logs</a>
                                
                                <a href="{{ route('admin.campaign_changes.index') }}"
                                    class="block px-4 py-2 text-sm rounded-md transition-colors {{ request()->is('admin/campaign-changes*') ? 'text-primary font-medium bg-primaryLight/50' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">Campaign Changes</a>
                            @endif
                        @endauth
                    </div>
                </div>
            @endif

            <!-- Divider -->
            <div class="my-3 border-t border-border/60"></div>
        </nav>

        <!-- User Profile -->
        <div x-data="{ open: false }" class="border-t border-border/60 px-3 py-3 relative">
            <button @click="open = !open" type="button"
                class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-textMuted hover:bg-gray-50 hover:text-textMain transition-all duration-200 cursor-pointer group">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary/20 to-primary/10 border border-primary/20 flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-bold text-primary">{{ substr(Auth::user()->name ?? 'User', 0, 1) }}</span>
                    </div>
                    <div class="flex flex-col min-w-0 text-left">
                        <span class="text-sm font-semibold text-textMain truncate" title="{{ Auth::user()->name ?? '' }}">{{ Auth::user()->name ?? '' }}</span>
                        <span class="text-xs text-textLight truncate">{{ Auth::user() && Auth::user()->hasPermission('is_admin') ? 'Admin' : 'User' }}</span>
                    </div>
                </div>
                <svg class="w-4 h-4 text-textLight flex-shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Submenu (Opening upwards since it's at the bottom) -->
            <div x-show="open" 
                 x-transition:enter="transition-all ease-out duration-200 origin-bottom"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition-all ease-in duration-150 origin-bottom"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                 @click.away="open = false"
                 class="absolute bottom-full mb-2 left-4 right-4 bg-white rounded-lg shadow-elevated border border-gray-100 py-2 z-50">
                
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">My Account</a>
                
                @auth
                    @if (\Illuminate\Support\Facades\Route::has('tokens.index'))
                        <a href="{{ route('tokens.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">API</a>
                    @endif
                @endauth
                
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <a href="{{ route('logout') }}" 
                       onclick="event.preventDefault(); this.closest('form').submit();"
                       class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700">Log Out</a>
                </form>
            </div>
        </div>
    </aside>
</div>
