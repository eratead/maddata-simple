@props(['campaign'])
@php
    $inputClass = 'w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all';
    $startDate = old('start_date', $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('Y-m-d') : '');
    $endDate   = old('end_date',   $campaign->end_date   ? \Carbon\Carbon::parse($campaign->end_date)->format('Y-m-d')   : '');
@endphp

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <h2 class="text-sm font-semibold text-gray-800">Schedule</h2>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col gap-1.5">
                <label for="start_date" class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Start Date</label>
                <input type="date" id="start_date" name="start_date"
                    value="{{ $startDate }}"
                    class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('start_date')" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="end_date" class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">End Date</label>
                <input type="date" id="end_date" name="end_date"
                    value="{{ $endDate }}"
                    class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('end_date')" />
            </div>
        </div>
    </div>
</div>
