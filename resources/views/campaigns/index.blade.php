<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- BREADCRUMBS BLOCK (Empty Fixed Spacer) -->
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">Campaigns</h1>
                    <p class="text-gray-500 text-sm mt-1">
                        @if(request('client_id'))
                            Showing campaigns for <strong>{{ $clients->find(request('client_id'))->name ?? 'Selected Client' }}</strong>
                        @else
                            Manage and track all active and scheduled campaigns.
                        @endif
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <!-- Client Selector -->
                    <div class="relative w-full sm:w-64">
                        <select id="client_selector" 
                            onchange="window.location.href = this.value ? '/campaigns/client/' + this.value : '/campaigns';"
                            class="w-full pl-4 pr-10 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary hover:border-gray-300 transition-all appearance-none cursor-pointer">
                            <option value="">All clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @if (request('client_id') == $client->id) selected @endif>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    @if (auth()->user()?->hasPermission('is_admin'))
                        <a href="{{ route('campaigns.create') }}" 
                            class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            New Campaign
                        </a>
                    @endif
                </div>
            </header>

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-100 text-green-700 rounded-xl flex items-start gap-3 shadow-sm animate-fade-in">
                    <svg class="w-5 h-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            @endif

            <!-- Overview Summary Grid -->
            <div class="hidden md:grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <!-- Stat Card: Clients -->
                <div class="bg-surface border border-border rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200 group relative overflow-hidden">
                    <div class="absolute -right-3 -bottom-3 opacity-10 text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <p class="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider relative z-10">Total Clients</p>
                    <p class="text-2xl font-black text-gray-900 mt-1 relative z-10">{{ number_format($totalClients) }}</p>
                </div>

                <!-- Stat Card: Active Campaigns -->
                <div class="bg-surface border border-border rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200 group relative overflow-hidden">
                    <div class="absolute -right-3 -bottom-3 opacity-10 text-emerald-600 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <p class="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider relative z-10">Active Campaigns</p>
                    <p class="text-2xl font-black text-emerald-600 mt-1 relative z-10">{{ number_format($activeCampaignsCount) }}</p>
                </div>

                <!-- Stat Card: Last Day Impressions -->
                <div class="bg-surface border border-border rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200 group relative overflow-hidden">
                    <div class="absolute -right-3 -bottom-3 opacity-10 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <p class="text-[11px] font-semibold text-blue-600 uppercase tracking-wider relative z-10">Last Day Impressions</p>
                    <p class="text-2xl font-black text-gray-900 mt-1 relative z-10">{{ number_format($lastDayImpressions) }}</p>
                </div>

                <!-- Stat Card: Last Day CTR -->
                <div class="bg-surface border border-border rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200 group relative overflow-hidden">
                    <div class="absolute -right-3 -bottom-3 opacity-10 text-rose-600 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <p class="text-[11px] font-semibold text-rose-600 uppercase tracking-wider relative z-10">Last Day CTR</p>
                    <p class="text-2xl font-black text-gray-900 mt-1 relative z-10">{{ number_format($lastDayCtr, 2) }}%</p>
                </div>
            </div>

            <!-- Main Listing Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                <div class=" p-4 sm:p-6 ">
                    <x-ui.datatable table-id="campaigns-table">
                        <table id="campaigns-table" class="min-w-full w-full">
                            <thead class="bg-gray-50/80 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Campaign
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Status
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Client
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider hidden text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider sortable" data-col="agency">Agency</th>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Duration
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Pacing
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100 bg-white">
                                @foreach ($campaigns as $campaign)
                                    <tr class="hover:bg-gray-50/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <a href="{{ route('dashboard.campaign', $campaign->id) }}" 
                                                class="text-sm font-bold text-primary hover:text-primary-hover hover:underline transition-all truncate max-w-[200px]" title="{{ $campaign->name }}">
                                                {{ $campaign->name }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($campaign->status === 'paused')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 uppercase tracking-wide shrink-0">Paused</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100/80 text-green-700 uppercase tracking-wide shrink-0">Active</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-xs font-medium text-gray-600">{{ $campaign->client->name ?? '—' }}</span>
                                        </td>
                                        <td class="px-6 py-4 hidden">
                                            <span class="text-xs text-gray-500">{{ $campaign->client->agency ?? '' }}</span>
                                        </td>
                                        <td class="px-6 py-4" data-order="{{ \Carbon\Carbon::parse($campaign->start_date ?: $campaign->created_at)->format('Y-m-d') }}">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                    {{ \Carbon\Carbon::parse($campaign->start_date ?: $campaign->created_at)->format('d M Y') }}
                                                </span>
                                                @if($campaign->end_date)
                                                    <span class="text-[10px] text-gray-400 mt-0.5 whitespace-nowrap">
                                                        until {{ \Carbon\Carbon::parse($campaign->end_date)->format('d M Y') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            @php
                                                $p = $pacingData[$campaign->id] ?? null;
                                            @endphp
                                            @if ($p && !is_null($p['percent_raw']))
                                                <div class="flex flex-col gap-1.5 w-32">
                                                    <div class="flex justify-between items-center px-0.5">
                                                        <span class="text-[10px] font-bold text-gray-700 tracking-tight">{{ number_format($p['percent_raw'], 0) }}%</span>
                                                    </div>
                                                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-primary to-blue-400 rounded-full shadow-[0_0_8px_rgba(79,70,229,0.3)]" 
                                                            style="width: {{ min(100, $p['percent_raw']) }}%"></div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400 italic">No data</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex justify-end items-center gap-2 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity duration-150">
                                                
                                                @auth
                                                    @if (auth()->user()->hasPermission('is_admin') || (auth()->user()->hasPermission('can_upload_reports') && auth()->user()->clients->contains($campaign->client_id)))
                                                        <form id="upload-form-{{ $campaign->id }}" action="{{ route('campaigns.upload', $campaign->id) }}" method="POST" enctype="multipart/form-data" class="m-0">
                                                            @csrf
                                                            <label class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all cursor-pointer inline-block" title="Upload Report">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                                                </svg>
                                                                <input type="file" name="report" required onchange="this.form.submit();" class="hidden" />
                                                            </label>
                                                        </form>
                                                    @endif
                                                @endauth
                                                
                                                @can('update', $campaign)
                                                    <a href="{{ route('campaigns.edit', $campaign->id) }}" 
                                                        class="p-2 text-gray-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all"
                                                        title="Edit Campaign">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                        </svg>
                                                    </a>
                                                @endcan
                                                
                                                @can('delete', $campaign)
                                                    <form action="{{ route('campaigns.destroy', $campaign->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign?');" class="m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                                            title="Delete Campaign">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
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
                </div>
            </div>
        </div>
    </main>
</x-app-layout>
