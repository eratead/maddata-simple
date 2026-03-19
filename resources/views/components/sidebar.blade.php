{{-- ================================================================
     Sidebar — dark bg-[#111827], orange active state
     x-data lives on <body> in app.blade.php (sidebarOpen)
     All @if / @auth permission guards are preserved exactly.
================================================================ --}}

<aside class="fixed inset-y-0 left-0 z-30 w-60 flex flex-col bg-[#111827] transition-transform duration-300 lg:relative lg:translate-x-0 shrink-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

    {{-- ── Logo ──────────────────────────────────────────────────── --}}
    <div class="flex items-center h-14 px-5 border-b border-white/10 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="28" height="28" rx="6" fill="#F97316"/>
                <text x="5" y="20" font-family="Inter,sans-serif" font-weight="700" font-size="16" fill="white">M</text>
            </svg>
            <span class="text-white font-bold text-sm tracking-tight">Mad<span class="text-[#F97316]">Data</span></span>
        </a>

        {{-- Mobile close button --}}
        <button @click="sidebarOpen = false"
                class="lg:hidden ml-auto p-1 rounded text-slate-400 hover:text-white cursor-pointer"
                aria-label="Close menu">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ── Navigation ───────────────────────────────────────────── --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5" aria-label="Main navigation">

        <p class="px-2 pt-1 pb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Main</p>

        {{-- Campaigns --}}
        <a href="{{ route('campaigns.index') }}"
           class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('campaigns.*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
            {{-- Flowbite: general/rectangle-list --}}
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 6h14M5 10h14M5 14h14M5 18h14"/>
            </svg>
            Campaigns
        </a>

        {{-- Report --}}
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('dashboard*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
            {{-- Flowbite: general/chart-mixed --}}
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Report
        </a>

        {{-- ── Manage section (admin + can_see_logs only) ─────────── --}}
        @if (Auth::user() && (Auth::user()->hasPermission('is_admin') || Auth::user()->hasPermission('can_see_logs')))
            @php
                $sidebarIsAdmin = Auth::user()->hasPermission('is_admin');
                $sidebarCanLogs = Auth::user()->hasPermission('can_see_logs');
                $isManageActive = request()->is('clients*')
                    || request()->is('users*')
                    || request()->is('admin/roles*')
                    || request()->is('admin/activity-logs*')
                    || request()->is('admin/campaign-changes*')
                    || request()->is('admin/audiences*');
            @endphp

            <p class="px-2 pt-4 pb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Manage</p>

            @if ($sidebarIsAdmin)
                {{-- Clients --}}
                <a href="{{ route('clients.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('clients*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
                    {{-- Flowbite: general/building --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Clients
                </a>

                {{-- Users --}}
                <a href="{{ route('users.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('users*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
                    {{-- Flowbite: user/users --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Users
                </a>

                {{-- Roles --}}
                <a href="{{ route('admin.roles.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('admin/roles*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
                    {{-- Flowbite: general/shield-check --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                    </svg>
                    Roles
                </a>

                {{-- Audiences --}}
                <a href="{{ route('admin.audiences.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('admin/audiences*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
                    {{-- Flowbite: general/tag --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    Audiences
                </a>
            @endif

            @if ($sidebarIsAdmin || $sidebarCanLogs)
                {{-- Activity Logs --}}
                <a href="{{ route('admin.activity-logs.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('admin/activity-logs*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
                    {{-- Flowbite: general/clipboard-list --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    Activity Logs
                </a>

                {{-- Campaign Changes --}}
                <a href="{{ route('admin.campaign_changes.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->is('admin/campaign-changes*') ? 'nav-active' : 'text-slate-400 hover:bg-white/[0.08] hover:text-white' }}">
                    {{-- Flowbite: general/arrows-repeat --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Campaign Changes
                </a>
            @endif

        @endif

    </nav>

    {{-- ── User footer ──────────────────────────────────────────── --}}
    <div x-data="{ open: false }" class="border-t border-white/10 px-3 py-3 shrink-0">

        {{-- Profile / API Settings links --}}
        <a href="{{ route('profile.edit') }}"
           class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium text-slate-400 hover:bg-white/[0.08] hover:text-white transition-colors">
            {{-- Flowbite: general/cog --}}
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Settings
        </a>

        @auth
            @if (\Illuminate\Support\Facades\Route::has('tokens.index') && (Auth::user()->hasPermission('is_admin') || optional(Auth::user()->userRole)->name === 'Campaign Manager'))
                <a href="{{ route('tokens.index') }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium text-slate-400 hover:bg-white/[0.08] hover:text-white transition-colors">
                    {{-- Flowbite: general/key --}}
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    API Settings
                </a>
            @endif
        @endauth

        {{-- User row + logout --}}
        <div class="flex items-center gap-2.5 px-3 py-2 mt-1">
            <div class="w-7 h-7 rounded-full bg-[#F97316] flex items-center justify-center text-white text-xs font-bold shrink-0">
                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name ?? '' }}</p>
                <p class="text-[10px] text-slate-500 truncate">{{ Auth::user() && Auth::user()->hasPermission('is_admin') ? 'Admin' : 'User' }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0 shrink-0">
                @csrf
                <button type="submit"
                        title="Log out"
                        class="p-1 text-slate-600 hover:text-red-400 transition-colors cursor-pointer">
                    {{-- Flowbite: general/arrow-right-from-bracket --}}
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>
        </div>

    </div>

</aside>
