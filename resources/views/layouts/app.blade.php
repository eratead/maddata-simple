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

<body class="bg-gray-100 text-gray-900 font-sans antialiased">
        <div id="app" class="flex flex-col lg:flex-row min-h-screen relative">
                @include('components.sidebar')

                <!-- Main Content -->
                <main class="flex-1 w-full min-w-0 p-3 md:p-8 bg-gray-50 min-h-screen relative">
                        {{ $slot }}
                </main>
        </div> {{-- end #app --}}
        @stack('styles')
        @stack('scripts')
</body>

</html>
