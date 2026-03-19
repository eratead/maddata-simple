<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Campaigns</h1>
@endpush

@push('page-actions')
    {{-- Searchable client filter --}}
    @php
        $selectedClient = request('client_id') ? $clients->find(request('client_id')) : null;
    @endphp
    <div x-data="{
            open:     false,
            search:   '',
            selected: @js($selectedClient ? ['id' => $selectedClient->id, 'name' => $selectedClient->name] : ['id' => '', 'name' => 'All clients']),
            clients:  @js($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()),
            get filtered() {
                const q = this.search.toLowerCase();
                return q ? this.clients.filter(c => c.name.toLowerCase().includes(q)) : this.clients;
            },
            select(client) {
                this.selected = client;
                this.open     = false;
                this.search   = '';
                window.location.href = client.id ? '/campaigns/client/' + client.id : '/campaigns';
            }
        }"
        @click.away="open = false"
        class="relative">

        {{-- Trigger button --}}
        <button type="button" @click="open = !open"
                class="flex items-center gap-2 pl-3 pr-2.5 py-1.5 text-xs border rounded-md bg-white transition-colors cursor-pointer w-48"
                :class="open ? 'border-[#F97316] ring-1 ring-[#F97316] text-gray-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span x-text="selected.name" class="flex-1 text-left truncate"></span>
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Dropdown --}}
        <div x-show="open" x-cloak
             class="absolute top-full right-0 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-xl z-50 overflow-hidden"
             style="display:none">

            {{-- Search input --}}
            <div class="p-2 border-b border-gray-100">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input x-model="search" @click.stop
                           type="text" placeholder="Search clients..."
                           class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-[#F97316] focus:border-[#F97316]"
                           x-ref="searchInput" @keydown.escape="open = false">
                </div>
            </div>

            {{-- Options list --}}
            <ul class="overflow-y-auto max-h-60 py-1">
                {{-- All clients --}}
                <li>
                    <button type="button" @click="select({ id: '', name: 'All clients' })"
                            class="w-full text-left px-3 py-2 text-xs transition-colors cursor-pointer"
                            :class="selected.id === '' ? 'bg-orange-50 text-[#F97316] font-semibold' : 'text-gray-700 hover:bg-gray-50'">
                        All clients
                    </button>
                </li>
                <template x-for="client in filtered" :key="client.id">
                    <li>
                        <button type="button" @click="select(client)"
                                class="w-full text-left px-3 py-2 text-xs transition-colors cursor-pointer"
                                :class="selected.id == client.id ? 'bg-orange-50 text-[#F97316] font-semibold' : 'text-gray-700 hover:bg-gray-50'"
                                x-text="client.name">
                        </button>
                    </li>
                </template>
                <li x-show="filtered.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">No clients found</li>
            </ul>
        </div>
    </div>

    @if(auth()->user()?->hasPermission('is_admin') || auth()->user()?->hasPermission('can_edit_campaigns'))
        <a href="{{ route('campaigns.create') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#F97316] hover:bg-[#EA580C] text-white text-xs font-semibold rounded-md transition-colors whitespace-nowrap">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Campaign
        </a>
    @endif
@endpush

    <x-flash-messages />

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">

        <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-indigo-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider mb-1">Total Clients</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($totalClients) }}</div>
        </div>

        <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-emerald-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-emerald-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Active Campaigns</div>
            <div class="text-xl font-black text-emerald-600 leading-none relative z-10">{{ number_format($activeCampaignsCount) }}</div>
        </div>

        <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-blue-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Last Day Impressions</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($lastDayImpressions) }}</div>
        </div>

        <div class="bg-rose-50/50 border border-rose-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-rose-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-rose-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4.5V19a1 1 0 001 1h15M7 14l4-4 4 4 5-5m0 0h-3.207M20 9v3.207"/></svg>
            </div>
            <div class="text-[10px] font-bold text-rose-600 uppercase tracking-wider mb-1">Last Day CTR</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($lastDayCtr, 2) }}%</div>
        </div>

    </div>

    {{-- Campaigns table --}}
    <x-page-box class="overflow-hidden">
        <x-ui.datatable table-id="campaigns-table">
            <table id="campaigns-table" class="min-w-full w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70">
                        <th class="text-left pl-5 pr-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap sortable">
                            <div class="flex items-center gap-2">Campaign
                                <span class="flex flex-col gap-px" aria-hidden="true">
                                    <svg class="w-2 h-2 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2 h-2 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap sortable">
                            <div class="flex items-center gap-2">Status
                                <span class="flex flex-col gap-px" aria-hidden="true">
                                    <svg class="w-2 h-2 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2 h-2 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap sortable">
                            <div class="flex items-center gap-2">Client
                                <span class="flex flex-col gap-px" aria-hidden="true">
                                    <svg class="w-2 h-2 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2 h-2 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="hidden px-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide" data-col="agency">Agency</th>
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap sortable">
                            <div class="flex items-center gap-2">Duration
                                <span class="flex flex-col gap-px" aria-hidden="true">
                                    <svg class="w-2 h-2 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2 h-2 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="text-left px-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap sortable">
                            <div class="flex items-center gap-2">Pacing
                                <span class="flex flex-col gap-px" aria-hidden="true">
                                    <svg class="w-2 h-2 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2 h-2 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="text-right pr-5 pl-3 py-2.5 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($campaigns as $campaign)
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="pl-5 pr-3 py-3">
                                <a href="{{ route('dashboard.campaign', $campaign->id) }}"
                                   class="text-sm font-semibold text-[#F97316] hover:text-[#EA580C] transition-colors"
                                   title="{{ $campaign->name }}">
                                    {{ $campaign->name }}
                                </a>
                            </td>
                            <td class="px-3 py-3">
                                @if($campaign->status === 'paused')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500 border border-gray-200 uppercase tracking-wide">Paused</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-wide">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Active
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="text-xs font-medium text-gray-700">{{ $campaign->client->name ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-3 hidden">
                                <span class="text-xs text-gray-500">{{ $campaign->client->agency ?? '' }}</span>
                            </td>
                            <td class="px-3 py-3" data-order="{{ \Carbon\Carbon::parse($campaign->start_date ?: $campaign->created_at)->format('Y-m-d') }}">
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold text-gray-800 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($campaign->start_date ?: $campaign->created_at)->format('d M Y') }}
                                    </span>
                                    @if($campaign->end_date)
                                        <span class="text-[10px] text-gray-400 mt-0.5 whitespace-nowrap">
                                            until {{ \Carbon\Carbon::parse($campaign->end_date)->format('d M Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                @php $p = $pacingData[$campaign->id] ?? null; @endphp
                                @if($p && !is_null($p['percent_raw']))
                                    <div class="flex flex-col gap-1 w-28">
                                        <span class="text-[10px] font-bold text-gray-700 tabular-nums">{{ number_format($p['percent_raw'], 0) }}%</span>
                                        <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-r from-[#F97316] to-[#FB923C]"
                                                 style="width: {{ min(100, $p['percent_raw']) }}%"></div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="pr-5 pl-3 py-3">
                                <div class="flex justify-end items-center gap-1">

                                    @auth
                                        @if(auth()->user()->hasPermission('is_admin') || (auth()->user()->hasPermission('can_upload_reports') && auth()->user()->clients->contains($campaign->client_id)))
                                            <form id="upload-form-{{ $campaign->id }}" action="{{ route('campaigns.upload', $campaign->id) }}" method="POST" enctype="multipart/form-data" class="m-0">
                                                @csrf
                                                <label class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-md transition-colors cursor-pointer inline-block" title="Upload Report">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                    <input type="file" name="report" required onchange="this.form.submit();" class="hidden"/>
                                                </label>
                                            </form>
                                        @endif
                                    @endauth

                                    @can('update', $campaign)
                                        <a href="{{ route('campaigns.edit', $campaign->id) }}"
                                           class="p-1.5 text-gray-400 hover:text-[#F97316] hover:bg-[#F97316]/5 rounded-md transition-colors"
                                           title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </a>
                                    @endcan

                                    @can('delete', $campaign)
                                        <form id="delete-campaign-{{ $campaign->id }}" action="{{ route('campaigns.destroy', $campaign->id) }}" method="POST" class="m-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    @click="$dispatch('confirm-action', {
                                                        title:        'Delete campaign?',
                                                        message:      '{{ addslashes($campaign->name) }} will be permanently deleted.',
                                                        confirmLabel: 'Delete',
                                                        form:         document.getElementById('delete-campaign-{{ $campaign->id }}')
                                                    })"
                                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors cursor-pointer"
                                                    title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endcan

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.datatable>
    </x-page-box>

</x-app-layout>
