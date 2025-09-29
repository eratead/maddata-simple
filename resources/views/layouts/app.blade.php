<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full w-full">

<head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>MadData dashboard</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600&display=swap" rel="stylesheet" />
        {{-- <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" /> --}}
        <link rel="stylesheet" href="{{ asset('css/iconsmind.css') }}">
        <!-- Scripts -->
</head>

<body class="h-full w-full font-sans antialiased">
        <div id="app" class="min-h-screen flex flex-row bg-gray-100 ">
                @include('components.sidebar')

                <div class="flex-1 flex flex-col ">
                        @include('layouts.navigation')

                        <main class="flex-1 overflow-y-auto">
                                <div class="p-1 pt-4 min-h-screen">
                                        <div class="p-holder  p-2 md:p-6">
                                                {{ $slot }}
                                        </div>
                                </div>
                        </main>
                </div>
        </div> {{-- end #app --}}
        @stack('styles')
        @stack('scripts')
</body>

</html>
