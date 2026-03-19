{{--
    Renders session flash banners: success, error, warning.
    Drop <x-flash-messages /> at the top of any page content area.
--}}

@if (session('success'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200">
        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-lg bg-red-50 border border-red-200">
        <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
    </div>
@endif

@if (session('warning'))
    <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200">
        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
        </svg>
        <p class="text-sm text-amber-700 font-medium">{{ session('warning') }}</p>
    </div>
@endif
