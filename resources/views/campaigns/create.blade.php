<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('campaigns.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Campaigns</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="font-semibold text-gray-800 whitespace-nowrap">New Campaign</span>
    </div>
@endpush

@push('page-actions')
    <a href="{{ route('campaigns.index') }}">
        <x-secondary-button type="button">Cancel</x-secondary-button>
    </a>
    <x-primary-button type="submit" form="campaignForm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create Campaign
    </x-primary-button>
@endpush

    <x-flash-messages />

    <form id="campaignForm" action="{{ route('campaigns.store') }}" method="POST" class="max-w-3xl space-y-4">
        @csrf

        {{-- ── Campaign Details ──────────────────────────────────────── --}}
        <x-page-box class="overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3.5 border-b border-gray-100">
                <div class="w-6 h-6 rounded-md bg-[#F97316]/10 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="text-sm font-semibold text-gray-800">Campaign Details</h2>
            </div>
            <div class="p-5 space-y-4">

                {{-- Name --}}
                <div>
                    <x-input-label for="name" value="Campaign Name" />
                    <x-text-input id="name" name="name" type="text" required
                                  :value="old('name')" class="mt-1" placeholder="e.g. McDonald's – March 2026" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                {{-- Client (searchable combobox) --}}
                @php
                    $oldClientId   = old('client_id');
                    $oldClientName = $oldClientId ? ($clients->find($oldClientId)?->name ?? '') : '';
                @endphp
                <div x-data="{
                        open:         false,
                        search:       '',
                        selectedId:   '{{ $oldClientId }}',
                        selectedName: '{{ addslashes($oldClientName ?: 'Select a client') }}',
                        clients:      @js($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()),
                        get filtered() {
                            const q = this.search.toLowerCase();
                            return q ? this.clients.filter(c => c.name.toLowerCase().includes(q)) : this.clients;
                        },
                        select(c) { this.selectedId = c.id; this.selectedName = c.name; this.open = false; this.search = ''; }
                    }" @click.away="open = false">
                    <x-input-label value="Client" class="mb-1" />
                    <input type="hidden" name="client_id" :value="selectedId">

                    {{-- Trigger --}}
                    <button type="button" @click="open = !open"
                            class="w-full flex items-center gap-2 px-3.5 py-2 text-sm border rounded-lg bg-white text-left transition-colors"
                            :class="open ? 'border-[#F97316] ring-1 ring-[#F97316]' : 'border-gray-300 hover:border-gray-400'">
                        <span x-text="selectedName" :class="selectedId ? 'text-gray-900' : 'text-gray-400'" class="flex-1 truncate"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Dropdown --}}
                    <div x-show="open" x-cloak
                         class="relative z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden"
                         style="display:none">
                        <div class="p-2 border-b border-gray-100">
                            <div class="relative">
                                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input x-model="search" @click.stop type="text" placeholder="Search clients..."
                                       class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-[#F97316] focus:border-[#F97316]"
                                       @keydown.escape="open = false">
                            </div>
                        </div>
                        <ul class="overflow-y-auto max-h-52 py-1">
                            <template x-for="client in filtered" :key="client.id">
                                <li>
                                    <button type="button" @click="select(client)"
                                            class="w-full text-left px-3 py-2 text-sm transition-colors cursor-pointer"
                                            :class="selectedId == client.id ? 'bg-orange-50 text-[#F97316] font-semibold' : 'text-gray-700 hover:bg-gray-50'"
                                            x-text="client.name"></button>
                                </li>
                            </template>
                            <li x-show="filtered.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">No clients found</li>
                        </ul>
                    </div>
                    <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                </div>

                {{-- Impressions + Budget --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="expected_impressions" value="Expected Impressions" />
                        <x-text-input id="expected_impressions" name="expected_impressions" type="number"
                                      min="0" :value="old('expected_impressions')" class="mt-1" placeholder="0" />
                        <x-input-error :messages="$errors->get('expected_impressions')" class="mt-1" />
                    </div>
                    @if(auth()->user()->hasPermission('can_view_budget'))
                    <div>
                        <x-input-label for="budget" value="Total Budget (₪)" />
                        <x-text-input id="budget" name="budget" type="number"
                                      min="0" :value="old('budget')" class="mt-1" placeholder="0" />
                        <x-input-error :messages="$errors->get('budget')" class="mt-1" />
                    </div>
                    @endif
                </div>

                {{-- Status toggle --}}
                <div class="pt-4 border-t border-gray-100"
                     x-data="{ state: '{{ old('status', 'active') }}' }">
                    <input type="hidden" name="status" :value="state">
                    <div class="flex items-center justify-between">
                        <div>
                            <x-input-label value="Campaign Status" />
                            <p class="text-[11px] text-gray-400 mt-0.5">Controls whether this campaign is live.</p>
                        </div>
                        <div class="relative bg-gray-100 p-1 rounded-lg flex items-center w-44 cursor-pointer shrink-0"
                             @click="state = state === 'active' ? 'paused' : 'active'">
                            <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-md transition-all duration-300 ease-in-out shadow-sm"
                                 :class="state === 'active' ? 'left-1 bg-emerald-500' : 'left-[calc(50%+3px)] bg-gray-400'"></div>
                            <div class="flex-1 text-center py-1 text-xs font-semibold relative z-10 transition-colors"
                                 :class="state === 'active' ? 'text-white' : 'text-gray-500'">Active</div>
                            <div class="flex-1 text-center py-1 text-xs font-semibold relative z-10 transition-colors"
                                 :class="state === 'paused' ? 'text-white' : 'text-gray-500'">Paused</div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('status')" class="mt-1" />
                </div>

            </div>
        </x-page-box>

        {{-- ── Schedule ───────────────────────────────────────────────── --}}
        <x-page-box class="overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3.5 border-b border-gray-100">
                <div class="w-6 h-6 rounded-md bg-[#F97316]/10 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="text-sm font-semibold text-gray-800">Schedule</h2>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" name="start_date" type="date"
                                      :value="old('start_date')" class="mt-1" />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End Date" />
                        <x-text-input id="end_date" name="end_date" type="date"
                                      :value="old('end_date')" class="mt-1" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                    </div>
                </div>
            </div>
        </x-page-box>

        {{-- ── Required Creative Sizes ────────────────────────────────── --}}
        <div x-data="{
                isAdmin:       @json(auth()->user()->hasPermission('is_admin')),
                open:          false,
                selectedSizes: [],
                videoSizes:    ['1920x1080', '1080x1920'],
                staticSizes:   ['640x820', '640x960', '640x1175', '640x1280', '640x1370', '640x360', '300x250', '1080x1920'],
                toggleSize(size) {
                    if (!this.isAdmin) return;
                    this.selectedSizes.includes(size)
                        ? this.selectedSizes = this.selectedSizes.filter(s => s !== size)
                        : this.selectedSizes.push(size);
                },
                toggleGroup(group) {
                    const all = group.every(s => this.selectedSizes.includes(s));
                    all ? this.selectedSizes = this.selectedSizes.filter(s => !group.includes(s))
                        : group.forEach(s => { if (!this.selectedSizes.includes(s)) this.selectedSizes.push(s); });
                }
            }">
            <input type="hidden" name="required_sizes" :value="selectedSizes.join(',')">

            <x-page-box class="overflow-hidden">
                {{-- Accordion header --}}
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-3 px-5 py-3.5 text-left hover:bg-gray-50/70 transition-colors cursor-pointer">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-[#F97316]/10 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                        </div>
                        <h2 class="text-sm font-semibold text-gray-800">Required Creative Sizes</h2>
                        <span x-show="selectedSizes.length > 0"
                              class="text-[10px] font-semibold bg-[#F97316]/10 text-[#F97316] px-2 py-0.5 rounded-full"
                              x-text="selectedSizes.length + ' selected'"></span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Accordion body --}}
                <div x-show="open" x-collapse class="border-t border-gray-100">
                    <div class="p-5 space-y-5">

                        {{-- Video sizes --}}
                        <div>
                            <button type="button" @click="toggleGroup(videoSizes)"
                                    class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 hover:text-[#F97316] transition-colors mb-2 cursor-pointer select-none">
                                Video Sizes <span class="normal-case font-normal">(click to toggle all)</span>
                            </button>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="size in videoSizes" :key="size">
                                    <x-ui.size-pill />
                                </template>
                            </div>
                        </div>

                        {{-- Static sizes --}}
                        <div>
                            <button type="button" @click="toggleGroup(staticSizes)"
                                    class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 hover:text-[#F97316] transition-colors mb-2 cursor-pointer select-none">
                                Static Sizes <span class="normal-case font-normal">(click to toggle all)</span>
                            </button>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="size in staticSizes" :key="size">
                                    <x-ui.size-pill />
                                </template>
                            </div>
                        </div>

                        {{-- Custom sizes --}}
                        <div class="pt-4 border-t border-gray-100">
                            <x-input-label for="custom_sizes" value="Additional Sizes (comma separated)" />
                            <input id="custom_sizes" type="text" placeholder="e.g. 728x90, 160x600"
                                   @input="selectedSizes = $event.target.value.split(',').map(s => s.trim()).filter(Boolean)"
                                   x-bind:disabled="!isAdmin"
                                   x-bind:class="!isAdmin ? 'cursor-not-allowed opacity-60 bg-gray-100' : ''"
                                   class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-1 focus:ring-[#F97316] focus:border-[#F97316] transition-colors" />
                        </div>

                    </div>
                </div>
            </x-page-box>
        </div>

        {{-- ── Footer actions ─────────────────────────────────────────── --}}
        <div class="flex items-center justify-end gap-3 py-2">
            <a href="{{ route('campaigns.index') }}">
                <x-secondary-button type="button">Cancel</x-secondary-button>
            </a>
            <x-primary-button type="submit">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Campaign
            </x-primary-button>
        </div>

    </form>

</x-app-layout>
