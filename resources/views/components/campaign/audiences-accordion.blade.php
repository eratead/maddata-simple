@props(['campaign', 'connectedAudiences'])

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden"
    x-data="audienceManager({{ $campaign->id }}, {{ Js::from($connectedAudiences) }})">

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
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-800">Audiences</span>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                    :class="open ? 'bg-[#F97316] text-white' : 'bg-gray-100 text-gray-500'"
                    x-text="connected.length"></span>
            </div>
        </div>
        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>

    {{-- Body --}}
    <div x-show="open" x-collapse>
        <div class="p-6 border-t border-gray-100">

            {{-- Add Audiences button --}}
            <div class="flex justify-end mb-4">
                <button type="button" @click.stop="openModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#F97316] hover:bg-orange-600 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Audiences
                </button>
            </div>

            {{-- Connected Pills --}}
            <div x-show="connected.length > 0" class="flex flex-wrap gap-2">
                <template x-for="audience in connected" :key="audience.id">
                    <div class="inline-flex items-center gap-1.5 pl-3 pr-1 py-1 bg-orange-50 border border-orange-200 rounded-full">
                        <span class="text-xs text-orange-400 font-normal" x-text="audience.sub_category + ' ·'"></span>
                        <span class="text-xs font-semibold text-orange-800" x-text="audience.name"></span>
                        <button type="button" @click="removeAudience(audience.id)"
                            class="ml-0.5 w-4 h-4 rounded-full flex items-center justify-center hover:bg-orange-200 text-orange-400 hover:text-orange-700 transition-colors flex-shrink-0">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            {{-- Empty state --}}
            <div x-show="connected.length === 0"
                class="flex flex-col items-center justify-center py-8 text-center">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-500 font-medium">No audiences connected</p>
                <p class="text-xs text-gray-400 mt-0.5">Click "Add Audiences" to connect segments</p>
            </div>
        </div>
    </div>

    {{-- Audiences Modal --}}
    <div x-show="showModal" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm"
            style="z-index:9010"></div>

        <div x-show="showModal" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 flex items-center justify-center p-4"
            style="z-index:9011"
            @click.self="showModal = false">

            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col border border-gray-100 overflow-hidden"
                style="height: min(80vh, 620px)">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Select Audiences</h2>
                            <p class="text-xs text-gray-400"><span class="font-semibold text-[#F97316]" x-text="selectedCount"></span> selected</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        {{-- Search --}}
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                            <input type="text" x-model="search" placeholder="Search..."
                                class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-[#F97316] focus:ring-1 focus:ring-[#F97316]/20 w-44 transition-all">
                        </div>
                        {{-- Toggle connected --}}
                        <button type="button" @click="filterConnected = !filterConnected"
                            class="text-xs px-3 py-1.5 rounded-lg border transition-colors font-medium"
                            :class="filterConnected ? 'bg-[#F97316] text-white border-[#F97316]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
                            Selected only
                        </button>
                        <button type="button" @click="showModal = false"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="flex flex-1 min-h-0">
                    {{-- Categories sidebar --}}
                    <nav class="w-44 border-r border-gray-100 flex flex-col py-2 overflow-y-auto flex-shrink-0 bg-gray-50/50">
                        <button type="button" @click="activeCategory = null"
                            class="flex items-center gap-2 px-4 py-2.5 text-xs font-semibold transition-colors text-left"
                            :class="activeCategory === null ? 'text-[#F97316] bg-orange-50' : 'text-gray-500 hover:bg-gray-100'">
                            All Categories
                        </button>
                        <template x-for="cat in mainCategories" :key="cat.name">
                            <button type="button" @click="activeCategory = cat.name"
                                class="flex items-center gap-2 px-4 py-2.5 text-xs font-medium transition-colors text-left"
                                :class="activeCategory === cat.name ? 'text-[#F97316] bg-orange-50 font-semibold' : 'text-gray-500 hover:bg-gray-100'">
                                <span class="text-sm" x-text="cat.icon || '•'"></span>
                                <span class="flex-1 truncate" x-text="cat.name"></span>
                                <span x-show="categoryHasSelected(cat.name)"
                                    class="w-1.5 h-1.5 rounded-full bg-[#F97316] flex-shrink-0"></span>
                            </button>
                        </template>
                        <div x-show="loading"
                            class="flex items-center justify-center py-8 text-gray-400 text-xs gap-1.5">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Loading...
                        </div>
                    </nav>

                    {{-- Audience List --}}
                    <div class="flex-1 flex flex-col min-w-0">
                        <div class="px-4 py-2.5 border-b border-gray-100 flex justify-between items-center text-[10px] font-semibold text-gray-400 uppercase tracking-wider flex-shrink-0">
                            <span>Audience Segment</span>
                            <span>Est. Size</span>
                        </div>
                        <div class="flex-1 overflow-y-auto p-3">
                            <div x-show="!loading && filteredAudiences.length === 0"
                                class="flex flex-col items-center justify-center h-full text-gray-400 gap-2 py-10">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs">No audiences found</span>
                            </div>
                            <template x-for="(audiences, subCategory) in groupedBySub" :key="subCategory">
                                <div class="mb-4">
                                    <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm px-2 py-1.5 mb-1 border-b border-gray-100 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#F97316] flex-shrink-0"></span>
                                        <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider" x-text="subCategory"></h3>
                                    </div>
                                    <template x-for="audience in audiences" :key="audience.id">
                                        <label class="flex items-center justify-between px-3 py-2.5 rounded-xl transition-colors cursor-pointer group mb-0.5"
                                            :class="isSelected(audience.id) ? 'bg-orange-50/70 hover:bg-orange-50' : 'hover:bg-gray-50'">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <input type="checkbox"
                                                    :checked="isSelected(audience.id)"
                                                    @change="toggle(audience.id)"
                                                    class="w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/20 cursor-pointer flex-shrink-0 accent-[#F97316]">
                                                <p class="text-sm font-medium text-gray-700 group-hover:text-gray-900 transition-colors truncate"
                                                    x-text="audience.name"></p>
                                            </div>
                                            <div class="text-xs font-mono px-2 py-0.5 rounded-md border flex-shrink-0 ml-3"
                                                :class="isSelected(audience.id) ? 'bg-white border-gray-200 text-gray-500' : 'bg-gray-50 border-gray-200 text-gray-400'"
                                                x-text="formatUsers(audience.estimated_users)"></div>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="border-t border-gray-100 bg-gray-50/60 px-6 py-4 flex items-center justify-between flex-shrink-0">
                    <p class="text-sm text-gray-500">
                        <span class="font-bold text-[#F97316] bg-orange-100 px-2 py-0.5 rounded-full mr-1.5" x-text="selectedCount"></span>
                        audiences selected
                    </p>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl transition-all">
                            Cancel
                        </button>
                        <button type="button" @click="applySync()"
                            :disabled="syncing"
                            :class="{ 'opacity-60 cursor-wait': syncing }"
                            class="px-4 py-2 text-sm font-bold text-white bg-[#F97316] hover:bg-orange-600 rounded-xl transition-all shadow-sm">
                            <span x-show="!syncing" x-text="`Apply (${selectedCount} selected)`"></span>
                            <span x-show="syncing">Saving...</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
</div>
