@props(['campaign'])
@php
    $isAdmin = auth()->user()->hasPermission('is_admin');
    $canEdit = $isAdmin || auth()->user()->hasPermission('can_edit_campaigns');
    $selectedSizes = array_filter(explode(',', $campaign->required_sizes ?? ''), fn($s) => $s !== '');
    $selectedSizes = old('required_sizes') ? explode(',', old('required_sizes')) : $selectedSizes;
@endphp

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden"
    x-data="{
        open: false,
        isAdmin: @json($isAdmin),
        canEdit: @json($canEdit),
        selectedSizes: {{ Js::from(array_values($selectedSizes)) }},
        videoSizes: ['1920x1080', '1080x1920'],
        staticSizes: ['640x820', '640x960', '640x1175', '640x1280', '640x1370', '640x360', '300x250', '1080x1920'],
        toggleSize(size) {
            if (!this.isAdmin) return;
            const idx = this.selectedSizes.indexOf(size);
            if (idx > -1) this.selectedSizes.splice(idx, 1);
            else this.selectedSizes.push(size);
        },
        toggleGroup(groupSizes) {
            if (!this.canEdit) return;
            const allSelected = groupSizes.every(s => this.selectedSizes.includes(s));
            if (allSelected) this.selectedSizes = this.selectedSizes.filter(s => !groupSizes.includes(s));
            else groupSizes.forEach(s => { if (!this.selectedSizes.includes(s)) this.selectedSizes.push(s); });
        }
    }">

    <input type="hidden" name="required_sizes" x-ref="sizesInput" x-effect="$refs.sizesInput.value = selectedSizes.join(',')">

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
                        d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                </svg>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-800">Required Creative Sizes</span>
                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                    :class="open ? 'bg-[#F97316] text-white' : 'bg-gray-100 text-gray-500'"
                    x-text="selectedSizes.length + ' selected'"></span>
            </div>
        </div>
        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>

    {{-- Accordion Body --}}
    <div x-show="open" x-collapse>
        <div class="p-6 border-t border-gray-100 flex flex-col gap-5">

            {{-- Video Sizes --}}
            <div class="flex flex-col gap-2">
                <button type="button" @click="toggleGroup(videoSizes)"
                    class="flex items-center gap-2 text-left group">
                    <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 group-hover:text-[#F97316] transition-colors">Video Sizes</span>
                    <span class="text-[10px] text-gray-300 group-hover:text-[#F97316]/60 transition-colors">(click to toggle all)</span>
                </button>
                <div class="flex flex-wrap gap-2">
                    <template x-for="size in videoSizes" :key="size">
                        <x-ui.size-pill />
                    </template>
                </div>
            </div>

            {{-- Static Sizes --}}
            <div class="flex flex-col gap-2">
                <button type="button" @click="toggleGroup(staticSizes)"
                    class="flex items-center gap-2 text-left group">
                    <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 group-hover:text-[#F97316] transition-colors">Static Sizes</span>
                    <span class="text-[10px] text-gray-300 group-hover:text-[#F97316]/60 transition-colors">(click to toggle all)</span>
                </button>
                <div class="flex flex-wrap gap-2">
                    <template x-for="size in staticSizes" :key="size">
                        <x-ui.size-pill />
                    </template>
                </div>
            </div>

            {{-- Custom Sizes --}}
            <div class="flex flex-col gap-1.5 pt-3 border-t border-gray-100">
                <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">
                    Additional Sizes <span class="normal-case tracking-normal font-normal text-gray-300">(comma separated)</span>
                </label>
                <input type="text"
                    placeholder="e.g. 728x90, 160x600"
                    @change="selectedSizes = $event.target.value.split(',').map(s => s.trim()).filter(s => s !== '')"
                    :value="selectedSizes.join(', ')"
                    :disabled="!isAdmin"
                    :class="isAdmin ? '' : 'cursor-not-allowed opacity-60'"
                    class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all">
            </div>

        </div>
    </div>
</div>
