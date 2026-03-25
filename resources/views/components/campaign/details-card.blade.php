@props(['campaign', 'clients'])
@php
    $isAdmin = auth()->user()->hasPermission('is_admin');
    $canViewBudget = auth()->user()->hasPermission('can_view_budget');
    $canEditBudget = auth()->user()->can('editBudget', App\Models\Campaign::class);
    $inputClass = 'w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all';
    $currentClientId = old('client_id', $campaign->client_id);
    $currentClient = $clients->firstWhere('id', $currentClientId);
    $clientsJson = $clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values();
@endphp

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <h2 class="text-sm font-semibold text-gray-800">Campaign Details</h2>
    </div>

    <div class="p-6 flex flex-col gap-5">
        {{-- Campaign Name --}}
        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Campaign Name</label>
            <input type="text" id="name" name="name"
                value="{{ old('name', $campaign->name) }}"
                class="{{ $inputClass }}" required>
            <x-input-error :messages="$errors->get('name')" />
        </div>

        {{-- Client Searchable Combobox --}}
        <div class="flex flex-col gap-1.5"
            x-data="{
                open: false,
                query: '{{ old('client_name', $currentClient?->name ?? '') }}',
                selectedId: {{ $currentClientId ?? 'null' }},
                clients: {{ Js::from($clientsJson) }},
                get filtered() {
                    const q = this.query.toLowerCase();
                    return q ? this.clients.filter(c => c.name.toLowerCase().includes(q)) : this.clients;
                },
                select(client) {
                    this.selectedId = client.id;
                    this.query = client.name;
                    this.open = false;
                },
                clear() {
                    this.selectedId = null;
                    this.query = '';
                }
            }"
            @click.outside="open = false">
            <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Client</label>
            <input type="hidden" name="client_id" :value="selectedId">
            <div class="relative">
                <input type="text"
                    x-model="query"
                    @focus="open = true"
                    @input="open = true"
                    placeholder="Search client..."
                    class="{{ $inputClass }} pr-8"
                    autocomplete="off">
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                <div x-show="open && filtered.length > 0"
                    x-cloak
                    class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-56 overflow-y-auto">
                    <template x-for="client in filtered" :key="client.id">
                        <button type="button"
                            @click="select(client)"
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-[#F97316] transition-colors flex items-center gap-2"
                            :class="selectedId === client.id ? 'bg-orange-50 text-[#F97316] font-medium' : ''">
                            <span x-text="client.name"></span>
                            <svg x-show="selectedId === client.id" class="ml-auto w-4 h-4 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </template>
                </div>
            </div>
            <x-input-error :messages="$errors->get('client_id')" />
        </div>

        {{-- Budget + Impressions Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            {{-- Expected Impressions --}}
            <div class="flex flex-col gap-1.5">
                <label for="impressions" class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Expected Impressions</label>
                <input type="number" id="impressions" name="expected_impressions" min="0"
                    value="{{ old('expected_impressions', $campaign->expected_impressions) }}"
                    {{ $isAdmin ? '' : 'disabled' }}
                    class="{{ $inputClass }} {{ $isAdmin ? '' : 'cursor-not-allowed opacity-60' }}">
            </div>

            @if($canViewBudget)
            <div class="flex flex-col gap-1.5">
                <label for="budget" class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Total Budget (NIS)</label>
                <input type="number" id="budget" name="budget" min="0"
                    value="{{ old('budget', $campaign->budget) }}"
                    {{ $canEditBudget ? '' : 'disabled' }}
                    class="{{ $inputClass }} {{ $canEditBudget ? '' : 'cursor-not-allowed opacity-60' }}">
            </div>
            @endif
        </div>

        {{-- Status Toggle --}}
        <div class="pt-4 border-t border-gray-100 flex items-center justify-between"
            x-data="{ state: '{{ old('status', $campaign->status ?? 'active') }}' }">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Campaign Status</p>
                <p class="text-sm font-semibold text-gray-800 mt-0.5" x-text="state === 'active' ? 'Active' : 'Paused'"></p>
            </div>
            <input type="hidden" name="status" :value="state">
            <div class="relative bg-gray-100 p-1 rounded-xl flex items-center cursor-pointer w-[184px] shadow-inner"
                @click="state = state === 'active' ? 'paused' : 'active'">
                <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-lg transition-all duration-300 ease-in-out"
                    :class="state === 'active'
                        ? 'left-1 bg-emerald-500 shadow-sm'
                        : 'left-[calc(50%+3px)] bg-gray-400 shadow-sm'">
                </div>
                <div class="flex-1 text-center py-1 text-xs font-bold relative z-10 transition-colors duration-200"
                    :class="state === 'active' ? 'text-white' : 'text-gray-500'">Active</div>
                <div class="flex-1 text-center py-1 text-xs font-bold relative z-10 transition-colors duration-200"
                    :class="state === 'paused' ? 'text-white' : 'text-gray-500'">Paused</div>
            </div>
        </div>

        {{-- Video indicator (read-only, set automatically on report upload) --}}
        @if($campaign->is_video)
        <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">Video Campaign</span>
            <span class="text-xs text-gray-400">Set automatically from uploaded report data</span>
        </div>
        @endif

    </div>
</div>
