<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MadData Dashboard</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Rubik', sans-serif; }
        .nav-active { background: linear-gradient(90deg, rgba(249,115,22,0.18) 0%, rgba(249,115,22,0.05) 100%); color: #F97316; border-left: 2px solid #F97316; }
        .nav-active svg { color: #F97316; }
        aside::-webkit-scrollbar { width: 4px; }
        aside::-webkit-scrollbar-track { background: transparent; }
        aside::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    </style>
    @stack('styles')
</head>

<body class="bg-gray-50 text-gray-900 antialiased h-full" x-data="{ sidebarOpen: false }">

    {{-- Mobile overlay --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-20 bg-black/60 lg:hidden"></div>

    <div class="flex h-screen overflow-hidden">

        {{-- Sidebar --}}
        @include('components.sidebar')

        {{-- Main column --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            {{-- Top header --}}
            <header class="bg-white border-b border-gray-200 shrink-0">
                <div class="h-14 flex items-center px-5 gap-4">

                    {{-- Hamburger (mobile) --}}
                    <button @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden p-1.5 rounded-md hover:bg-gray-100 text-gray-500 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    {{-- Page title — views push here via @push('page-title') --}}
                    <div class="flex-1 min-w-0">
                        @stack('page-title')
                    </div>

                    {{-- Page actions — views push here via @push('page-actions') --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @stack('page-actions')
                    </div>

                </div>
            </header>

            {{-- Scrollable content --}}
            <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-gray-50">
                {{ $slot }}
            </main>

        </div>
    </div>

    {{-- Global confirm dialog — triggered via $dispatch('confirm-action', {...}) --}}
    <x-confirm-dialog />

    @stack('scripts')
</body>

</html>
