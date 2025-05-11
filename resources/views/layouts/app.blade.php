<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full w-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full w-full font-sans antialiased">
    <div id="app" class="min-h-screen flex flex-row bg-gray-100 ">
        @include('components.sidebar')

        <div class="flex-1 flex flex-col ">
            @include('layouts.navigation')

            <main class="flex-1 overflow-y-auto">
                <div class="pl-56 pt-14 min-h-screen">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>

</html>
