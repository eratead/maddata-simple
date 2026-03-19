@props(['campaign'])

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden"
    x-data="{ open: false }">

    {{-- Accordion Header --}}
    <div class="px-5 py-4 flex items-center justify-between cursor-pointer select-none transition-colors"
        :class="open ? 'bg-orange-50/60 border-l-[3px] border-l-[#F97316]' : 'hover:bg-gray-50'"
        @click="open = !open">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors"
                :class="open ? 'bg-orange-100' : 'bg-gray-100'">
                <svg class="w-4 h-4 transition-colors" :class="open ? 'text-[#F97316]' : 'text-gray-500'"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-800">Creatives</span>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                    :class="open ? 'bg-[#F97316] text-white' : 'bg-gray-100 text-gray-500'">
                    {{ $campaign->creatives->count() }}
                </span>
            </div>
        </div>
        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>

    {{-- Body --}}
    <div x-show="open" x-collapse>
        <div class="border-t border-gray-100">

            {{-- Creative Optimization Row --}}
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4"
                x-data="{ optimization: {{ old('creative_optimization', $campaign->creative_optimization) ? '1' : '0' }} }">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Creative Optimization</p>
                    <p class="text-sm text-gray-700 mt-0.5" x-text="optimization == 1 ? 'CTR – serve best-performing creatives more' : 'Equal Weights – rotate evenly'"></p>
                </div>
                <input type="hidden" name="creative_optimization" :value="optimization">
                <div class="relative bg-gray-100 p-1 rounded-xl flex items-center cursor-pointer w-[196px] shadow-inner flex-shrink-0"
                    @click="optimization = optimization == 1 ? 0 : 1">
                    <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] bg-blue-500 rounded-lg transition-all duration-300 ease-in-out"
                        :class="optimization == 1 ? 'left-1' : 'left-[calc(50%+3px)]'"></div>
                    <div class="flex-1 text-center py-1 text-xs font-bold relative z-10 transition-colors duration-200"
                        :class="optimization == 1 ? 'text-white' : 'text-gray-500'">CTR</div>
                    <div class="flex-1 text-center py-1 text-xs font-bold relative z-10 transition-colors duration-200"
                        :class="optimization == 0 ? 'text-white' : 'text-gray-500'">Equal Weights</div>
                </div>
            </div>

            {{-- Creatives List --}}
            <div class="p-6">
                <div class="flex justify-end mb-4">
                    <a href="{{ route('creatives.create', $campaign) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#F97316] hover:bg-orange-600 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Creative
                    </a>
                </div>

                @if($campaign->creatives->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-500">No creatives yet</p>
                        <p class="text-xs text-gray-400 mt-1">Add your first creative to get started</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($campaign->creatives as $creative)
                            <a href="{{ route('creatives.edit', $creative) }}"
                                class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-[#F97316]/50 hover:bg-orange-50/30 transition-all group">
                                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 group-hover:bg-orange-100 transition-colors">
                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-[#F97316] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $creative->name }}</p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $creative->status ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                        <span class="text-xs text-gray-400">{{ $creative->status ? 'Active' : 'Paused' }}</span>
                                        @if($creative->files->isNotEmpty())
                                            <span class="text-gray-200">·</span>
                                            <span class="text-xs text-gray-400">{{ $creative->files->count() }} {{ Str::plural('file', $creative->files->count()) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-[#F97316] transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
