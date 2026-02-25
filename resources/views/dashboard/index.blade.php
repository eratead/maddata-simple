<x-app-layout>
        <main class="w-full">
            <div class="max-w-7xl mx-auto space-y-4 md:space-y-6">

                <!-- Page Header -->
                <header class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                            Dashboard – {{ $campaign->name }} ({{ $campaign->client->name }})
                        </h1>
                    </div>
                </header>

                <!-- Top Layout Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Left Column: Summary -->
                    <div class="lg:col-span-4 lg:col-start-1 bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 flex flex-col h-fit self-start hover:border-gray-200 hover:shadow-md transition-all group">
                        <h2 class="text-lg font-bold text-gray-900 mb-3 md:mb-5">Summary</h2>

                        <div class="grid grid-cols-2 gap-3 flex-grow">
                            <!-- Stat Card: Impressions -->
                            <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-blue-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Impressions</div>
                                <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['impressions'] ?? 0) }}</div>
                            </div>

                            <!-- Stat Card: Clicks -->
                            <div class="bg-purple-50/50 border border-purple-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-purple-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-purple-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-purple-600 uppercase tracking-wider mb-1">Clicks</div>
                                <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['clicks'] ?? 0) }}</div>
                            </div>

                            <!-- Stat Card: CTR -->
                            <div class="bg-rose-50/50 border border-rose-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-rose-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-rose-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-rose-600 uppercase tracking-wider mb-1">CTR</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">
                                    @if (!empty($summary['impressions']))
                                        {{ number_format(($summary['clicks'] / $summary['impressions']) * 100, 2) }}%
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>

                            <!-- Stat Card: Reach -->
                            <div class="bg-orange-50/50 border border-orange-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-orange-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-orange-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V10H2v10h5m10 0v-5a3 3 0 00-3-3h-4a3 3 0 00-3 3v5m10 0H7m5-11a3 3 0 110-6 3 3 0 010 6z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-orange-600 uppercase tracking-wider mb-1">Reach</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['uniques'] ?? 0) }}</div>
                            </div>

                            <!-- Stat Card: Frequency -->
                            <div class="bg-teal-50/50 border border-teal-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-teal-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-teal-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-teal-600 uppercase tracking-wider mb-1">Frequency</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">
                                    @if (!empty($summary['uniques']))
                                        {{ number_format($summary['impressions'] / $summary['uniques'], 2) }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>

                            <!-- Stat Card: Viewability -->
                            <div class="bg-cyan-50/50 border border-cyan-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-cyan-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-cyan-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-cyan-600 uppercase tracking-wider mb-1">Viewability</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">
                                    @if (!empty($summary['impressions']))
                                        {{ number_format(($summary['visible'] / $summary['impressions']) * 100, 2) }}%
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>

                            @if (auth()->user()?->hasPermission('can_view_budget'))
                            <!-- Stat Card: Budget -->
                            <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-emerald-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-emerald-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v1m0 0v1m0-1h1m-1 0h-1m-4 8h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Budget</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">₪{{ number_format($budget ?? 0) }}</div>
                            </div>

                            <!-- Stat Card: Spent -->
                            <div class="bg-green-50/50 border border-green-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-green-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-green-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-green-600 uppercase tracking-wider mb-1">Spent</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">₪{{ number_format($spent ?? 0) }}</div>
                            </div>

                            <!-- Stat Card: CPM -->
                            <div class="bg-amber-50/50 border border-amber-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-amber-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-amber-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">CPM</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">₪{{ number_format($cpm ?? 0, 2) }}</div>
                            </div>

                            <!-- Stat Card: CPC -->
                            <div class="bg-yellow-50/50 border border-yellow-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-yellow-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-yellow-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-yellow-600 uppercase tracking-wider mb-1">CPC</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">₪{{ number_format($cpc ?? 0, 2) }}</div>
                            </div>
                            @endif

                            @if ($campaign->is_video)
                            <!-- Stat Card: Video Complete -->
                            <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-blue-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Video Complete</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['video_complete'] ?? 0) }}</div>
                            </div>

                            <!-- Stat Card: VCR -->
                            <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-indigo-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path>
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider mb-1">VCR</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">
                                    @if (!empty($summary['vcr']))
                                        {{ number_format($summary['vcr'], 2) }}%
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>

                            @if (auth()->user()?->hasPermission('can_view_budget'))
                            <!-- Stat Card: Avr CPV -->
                            <div class="bg-yellow-50/50 border border-yellow-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-yellow-200 transition-all hover:-translate-y-0.5">
                                <div class="absolute -right-3 -bottom-3 opacity-10 text-yellow-600 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="text-[10px] font-bold text-yellow-600 uppercase tracking-wider mb-1">Avr. CPV</div>
                                <div class="text-lg font-black text-gray-900 leading-none relative z-10">₪{{ number_format($summary['cpv'] ?? 0, 2) }}</div>
                            </div>
                            @endif
                            @endif

                            @if (!empty($summary['expected_impressions']))
                            <!-- Pacing Bar spanning full width -->
                            <div class="col-span-2 bg-gray-50 border border-gray-100 rounded-xl p-4 flex flex-col justify-center relative overflow-hidden shadow-sm">
                                <div class="flex justify-between items-end mb-2">
                                    <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Pacing</div>
                                    @php
                                        $pacingPercent = $summary['expected_impressions'] > 0
                                            ? ($summary['impressions'] / $summary['expected_impressions']) * 100
                                            : 0;
                                    @endphp
                                    <div class="text-sm font-bold text-gray-800">{{ number_format($pacingPercent, 0) }}%</div>
                                </div>
                                <div class="w-full h-4 bg-gray-200 rounded-full overflow-hidden flex items-center relative shadow-inner">
                                    <div class="h-full bg-gradient-to-r {{ $pacingPercent >= 100 ? 'from-green-400 to-green-500' : 'from-blue-400 to-blue-500' }} w-full transition-all duration-1000" style="width: {{ min($pacingPercent, 100) }}%"></div>
                                    @if ($pacingPercent > 100)
                                    <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white drop-shadow-md tracking-wider">OVERDELIVERING</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>

                        <form action="{{ route('dashboard.export.excel', $campaign->id) }}" method="GET" class="w-full mt-6">
                            <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                            <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                            <button type="submit" class="w-full py-2.5 bg-[#4F46E5] hover:bg-indigo-700 text-white rounded-md text-sm font-medium transition-colors">
                                Download Excel
                            </button>
                        </form>
                    </div>

                    <!-- Right Column: Tabs & Table -->
                    <div class="lg:col-span-8 bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6 flex flex-col h-full w-full overflow-hidden hover:border-gray-200 hover:shadow-md transition-all group"
                         x-data="{
                             activeTab: localStorage.getItem('dashboardActiveTab') || 'date'
                         }" x-init="$watch('activeTab', value => localStorage.setItem('dashboardActiveTab', value))">
                        
                        <x-dates-filter action="{{ route('dashboard.campaign', $campaign->id) }}"
                                        :first-report-date="$firstReportDate" />

                        <div class="border-b border-gray-200 mb-4 md:mb-5 flex gap-4 md:gap-6 overflow-x-auto whitespace-nowrap [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                                        <nav class="-mb-px flex space-x-4">
                                                <button @click.prevent="activeTab = 'date'"
                                                        :class="activeTab === 'date' ? 'text-primary border-primary' : 'text-gray-500 hover:text-gray-700'"
                                                        class="text-xs md:text-sm font-medium pb-2 relative transition-colors focus:outline-none shrink-0 border-b-2"
                                                        style="border-bottom-color: transparent;"
                                                        x-bind:style="activeTab === 'date' ? 'border-bottom-color: currentColor;' : ''"
                                                >
                                                        By Date
                                                </button>
                                                <button @click.prevent="activeTab = 'placement'"
                                                        :class="activeTab === 'placement' ? 'text-primary border-primary' : 'text-gray-500 hover:text-gray-700'"
                                                        class="text-xs md:text-sm font-medium pb-2 relative transition-colors focus:outline-none shrink-0 border-b-2"
                                                        style="border-bottom-color: transparent;"
                                                        x-bind:style="activeTab === 'placement' ? 'border-bottom-color: currentColor;' : ''"
                                                >
                                                        By Placement
                                                </button>
                                        </nav>
                                </div>

                                <!-- Date Table -->
                                <div x-show="activeTab === 'date'" class="overflow-x-auto">
                                        <table class="w-full text-xs md:text-sm text-left border-collapse">
                                                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] md:text-[11px] font-bold border-y border-gray-200 tracking-wide">
                                                        <tr>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Date</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Impressions</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Clicks</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">CTR</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Viewability</th>
                                                                @if ($campaign->is_video)
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">25%</th>
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">50%</th>
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">75%</th>
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">100%</th>
                                                                @endif
                                                        </tr>
                                                </thead>
                                                <tbody class="text-gray-700 divide-y divide-gray-100 bg-white">
                                                        @foreach ($campaignData as $row)
                                                                <tr class="hover:bg-gray-50/50">
                                                                        <td class="px-2 py-2 md:px-4 md:py-3 border-r border-gray-50 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->report_date)->format('Y-m-d') }}</td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->impressions) }}</td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->clicks) }}</td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">
                                                                                @if ($row->impressions > 0)
                                                                                        {{ number_format(($row->clicks / $row->impressions) * 100, 2) }}%
                                                                                @else
                                                                                        —
                                                                                @endif
                                                                        </td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">
                                                                                @if ($row->impressions > 0)
                                                                                        {{ number_format(($row->visible / $row->impressions) * 100, 2) }}%
                                                                                @else
                                                                                        —
                                                                                @endif
                                                                        </td>
                                                                        @if ($campaign->is_video)
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_25) }}</td>
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_50) }}</td>
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_75) }}</td>
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_100) }}</td>
                                                                        @endif
                                                                </tr>
                                                        @endforeach
                                                </tbody>
                                        </table>
                                </div>

                                <!-- Placement Table -->
                                <div x-show="activeTab === 'placement'" class="overflow-x-auto" style="display: none;">
                                        <table id="placementTable" class="w-full text-xs md:text-sm text-left border-collapse">
                                                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] md:text-[11px] font-bold border-y border-gray-200 tracking-wide">
                                                        <tr>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Placement</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Impressions</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Clicks</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">CTR</th>
                                                                <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">Viewability</th>
                                                                @if ($campaign->is_video)
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">25%</th>
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">50%</th>
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">75%</th>
                                                                        <th class="px-2 py-2 md:px-4 md:py-3 font-semibold text-gray-700">100%</th>
                                                                @endif
                                                        </tr>
                                                </thead>
                                                <tbody class="text-gray-700 divide-y divide-gray-100 bg-white">
                                                        @foreach ($placementData as $row)
                                                                <tr class="hover:bg-gray-50/50">
                                                                        <td class="px-2 py-2 md:px-4 md:py-3 border-r border-gray-50 truncate max-w-[120px] md:max-w-none">{{ $row->name }}</td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->impressions) }}</td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->clicks) }}</td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">
                                                                                @if ($row->impressions > 0)
                                                                                        {{ number_format(($row->clicks / $row->impressions) * 100, 2) }}%
                                                                                @else
                                                                                        —
                                                                                @endif
                                                                        </td>
                                                                        <td class="px-2 py-2 md:px-4 md:py-3">
                                                                                @if ($row->impressions > 0)
                                                                                        {{ number_format(($row->visible / $row->impressions) * 100, 2) }}%
                                                                                @else
                                                                                        —
                                                                                @endif
                                                                        </td>
                                                                        @if ($campaign->is_video)
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_25) }}</td>
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_50) }}</td>
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_75) }}</td>
                                                                                <td class="px-2 py-2 md:px-4 md:py-3">{{ number_format($row->video_100) }}</td>
                                                                        @endif
                                                                </tr>
                                                        @endforeach
                                                </tbody>
                                        </table>
                                </div>
                        </div>

                        <!-- Bottom Section: Chart -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 w-full lg:col-span-12">
                                <div class="flex flex-col items-center mb-6">
                                        <span class="text-xs font-bold text-gray-500 mb-2 uppercase">Chart</span>
                                        <div class="flex items-center gap-6">
                                                <div class="flex items-center gap-2">
                                                        <div class="w-8 h-3 border-2 border-blue-500 bg-white rounded-sm"></div>
                                                        <span class="text-xs text-gray-600">Impressions</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                        <div class="w-8 h-3 border-2 border-yellow-400 bg-white rounded-sm"></div>
                                                        <span class="text-xs text-gray-600">CTR (%)</span>
                                                </div>
                                        </div>
                                </div>

                                <div class="w-full h-[400px]">
                                        <canvas id="campaignChart"></canvas>
                                </div>
                        </div>
                </div>
            </div>
        </main>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                        if (localStorage.getItem('campaign_id') != {{ $campaign->id }}) {
                                localStorage.removeItem('dateRange');
                                localStorage.removeItem('dashboardActiveTab');
                                localStorage.setItem('campaign_id', {{ $campaign->id }});
                        }

                        // After switching (or on any load), clean up expired keys (2h TTL expected)
                        (function() {
                                function removeIfExpired(key) {
                                        const raw = localStorage.getItem(key);
                                        if (!raw) return;
                                        // Prefer shared helper if available
                                        if (window.getWithExpiry) {
                                                const val = window.getWithExpiry(key);
                                                if (val === null) {
                                                        localStorage.removeItem(key);
                                                }
                                                return;
                                        }
                                        // Fallback: parse JSON and check `expiry`
                                        try {
                                                const obj = JSON.parse(raw);
                                                if (obj && typeof obj === 'object' && obj.expiry && Date.now() > obj.expiry) {
                                                        localStorage.removeItem(key);
                                                }
                                        } catch (e) {
                                                // Not a JSON with expiry – leave as-is
                                        }
                                }
                                removeIfExpired('dateRange');
                                removeIfExpired('dashboardActiveTab');
                        })();
                        document.addEventListener('DOMContentLoaded', () => {
                                const ctx = document.getElementById('campaignChart');
                                if (!ctx) return;

                                // Gradient for Impressions
                                const gradientBlue = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
                                gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.2)'); // Blue 500 light
                                gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0)');

                                // Gradient for CTR
                                const gradientYellow = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
                                gradientYellow.addColorStop(0, 'rgba(250, 204, 21, 0.15)'); // Yellow 400 light
                                gradientYellow.addColorStop(1, 'rgba(250, 204, 21, 0)');

                                new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                                labels: {!! json_encode($chartLabels) !!},
                                                datasets: [{
                                                                label: 'Impressions',
                                                                data: {!! json_encode($chartImpressions) !!},
                                                                borderColor: '#3B82F6',
                                                                backgroundColor: gradientBlue,
                                                                borderWidth: 2,
                                                                fill: true,
                                                                tension: 0.4,
                                                                yAxisID: 'y',
                                                                pointBackgroundColor: '#3B82F6',
                                                                pointRadius: 4,
                                                                pointHoverRadius: 6
                                                        },
                                                        {
                                                                label: 'CTR (%)',
                                                                data: {!! json_encode(array_map(fn($i, $c) => $i ? round(($c / $i) * 100, 2) : 0, $chartImpressions, $chartClicks)) !!},
                                                                borderColor: '#FBBF24',
                                                                backgroundColor: gradientYellow,
                                                                borderWidth: 2,
                                                                fill: true,
                                                                tension: 0.4,
                                                                yAxisID: 'y1',
                                                                pointBackgroundColor: '#FBBF24',
                                                                pointRadius: 4,
                                                                pointHoverRadius: 6
                                                        }
                                                ]
                                        },
                                        options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                interaction: {
                                                        mode: 'index',
                                                        intersect: false,
                                                },
                                                plugins: {
                                                        legend: {
                                                                display: false // We use custom HTML legend instead
                                                        },
                                                        tooltip: {
                                                                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                                                titleColor: '#1F2937',
                                                                bodyColor: '#4B5563',
                                                                borderColor: '#E5E7EB',
                                                                borderWidth: 1,
                                                                padding: 12,
                                                                boxPadding: 6,
                                                                usePointStyle: true,
                                                        }
                                                },
                                                scales: {
                                                        x: {
                                                                grid: {
                                                                        display: false,
                                                                        drawBorder: false
                                                                },
                                                                ticks: {
                                                                        color: '#6B7280',
                                                                        font: { size: 11 }
                                                                }
                                                        },
                                                        y: {
                                                                type: 'linear',
                                                                position: 'left',
                                                                grid: {
                                                                        color: '#F3F4F6',
                                                                        drawBorder: false
                                                                },
                                                                ticks: {
                                                                        color: '#6B7280',
                                                                        font: { size: 11 },
                                                                        callback: function(value) {
                                                                                if (value >= 1000) return value / 1000 + 'k';
                                                                                return value;
                                                                        }
                                                                }
                                                        },
                                                        y1: {
                                                                type: 'linear',
                                                                position: 'right',
                                                                grid: {
                                                                        drawOnChartArea: false,
                                                                },
                                                                ticks: {
                                                                        color: '#6B7280',
                                                                        font: { size: 11 },
                                                                        callback: function(value) {
                                                                                return value + '%';
                                                                        }
                                                                }
                                                        }
                                                }
                                        }
                                });
                        });
                </script>

</x-app-layout>
