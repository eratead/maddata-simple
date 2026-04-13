<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('campaigns.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap hidden sm:block">Campaigns</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="font-semibold text-gray-800 truncate max-w-[180px] sm:max-w-xs">{{ $campaign->name }}</span>
        <svg class="w-3 h-3 text-gray-300 shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400 whitespace-nowrap hidden sm:block">Report</span>
    </div>
@endpush

@push('page-actions')
    {{-- Date range picker --}}
    <div x-data="dashDateFilter()" @click.away="openPicker=null" class="relative flex items-center gap-2">
        {{-- Quick preset select --}}
        <select class="hidden sm:block text-xs border border-gray-200 rounded-md pl-2 pr-6 py-1.5 text-gray-600 bg-white focus:outline-none focus:ring-1 focus:ring-[#F97316] cursor-pointer"
                @change="applyPreset($event.target.value); $event.target.value=''">
            <option value="" disabled selected>Quick select</option>
            <option value="all">Lifetime</option>
            <option value="yesterday">Yesterday</option>
            <option value="last_7">Last 7 days</option>
            <option value="mtd">Month to date</option>
            <option value="ytd">Year to date</option>
        </select>

        {{-- From button --}}
        <button type="button" @click="togglePicker('from')"
                class="flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 text-xs border rounded-md bg-white text-gray-600 transition-colors cursor-pointer"
                :class="openPicker==='from' ? 'border-[#F97316] ring-1 ring-[#F97316]' : 'border-gray-200 hover:border-gray-300'">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span x-text="formatDate(dateFrom)" class="whitespace-nowrap"></span>
        </button>
        <span class="text-gray-400 text-xs shrink-0">to</span>
        {{-- To button --}}
        <button type="button" @click="togglePicker('to')"
                class="flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 text-xs border rounded-md bg-white text-gray-600 transition-colors cursor-pointer"
                :class="openPicker==='to' ? 'border-[#F97316] ring-1 ring-[#F97316]' : 'border-gray-200 hover:border-gray-300'">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span x-text="formatDate(dateTo)" class="whitespace-nowrap"></span>
        </button>

        {{-- Calendar dropdown --}}
        <div x-show="openPicker !== null" x-cloak
             class="absolute top-full right-0 mt-1 z-50 bg-white border border-gray-200 rounded-xl shadow-xl p-3 w-64 select-none"
             style="display:none">
            <div class="flex items-center justify-between mb-3">
                <button type="button" @click.stop="prevMonth()" class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span x-text="calMonthYear" class="text-xs font-semibold text-gray-800"></span>
                <button type="button" @click.stop="nextMonth()" class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-7 mb-1">
                <template x-for="h in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="h">
                    <div class="text-center text-[9px] font-semibold text-gray-400 pb-1" x-text="h"></div>
                </template>
            </div>
            <div class="grid grid-cols-7">
                <template x-for="cell in calDays" :key="cell.key">
                    <div class="flex items-center justify-center h-7">
                        <button x-show="cell.date !== null" type="button"
                                @click.stop="!isBeforeMin(cell.full) && selectDate(cell.full)"
                                :disabled="isBeforeMin(cell.full)"
                                :class="{
                                    'bg-[#F97316] text-white font-bold shadow-sm': isSelected(cell.full),
                                    'bg-orange-50 text-[#F97316]': isInRange(cell.full) && !isSelected(cell.full),
                                    'text-gray-300 cursor-not-allowed': isBeforeMin(cell.full),
                                    'text-gray-700 hover:bg-gray-100': !isSelected(cell.full) && !isInRange(cell.full) && !isBeforeMin(cell.full)
                                }"
                                class="w-6 h-6 text-[11px] rounded-full transition-colors cursor-pointer"
                                x-text="cell.date"></button>
                        <div x-show="cell.date === null" class="w-6 h-6"></div>
                    </div>
                </template>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100 text-[10px] text-center"
                 :class="openPicker==='from' ? 'text-blue-400' : 'text-[#F97316]'"
                 x-text="openPicker==='from' ? '← Pick start date' : 'Pick end date →'"></div>
        </div>

        {{-- Hidden form that gets submitted --}}
        <form x-ref="dateForm" action="{{ route('dashboard.campaign', $campaign->id) }}" method="GET" class="hidden">
            <input type="hidden" name="start_date" :value="toInputVal(dateFrom)">
            <input type="hidden" name="end_date" :value="toInputVal(dateTo)">
        </form>
    </div>

    {{-- Export Excel --}}
    <a href="{{ route('dashboard.export.excel', $campaign->id) }}?start_date={{ request('start_date', $firstReportDate) }}&end_date={{ request('end_date', date('Y-m-d')) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 hover:text-[#F97316] hover:border-[#F97316]/30 transition-colors whitespace-nowrap">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span class="hidden sm:inline">Export</span>
    </a>

    {{-- Status badge --}}
    @php
        $statusClass = match($campaign->status ?? 'pending') {
            'active'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'pending'   => 'bg-amber-50 text-amber-700 border-amber-200',
            default     => 'bg-gray-100 text-gray-500 border-gray-200',
        };
    @endphp
    <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $statusClass }}">
        @if(($campaign->status ?? '') === 'active')
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
        @endif
        {{ ucfirst($campaign->status ?? 'Pending') }}
    </span>
@endpush

    {{-- ── Campaign info bar ─────────────────────────────────────── --}}
    @php
        $hasBudget      = auth()->user()?->hasPermission('can_view_budget');
        $showImprPacing = !empty($summary['expected_impressions']);
        if ($showImprPacing) {
            $pacingPct = $summary['expected_impressions'] > 0
                ? ($summary['impressions'] / $summary['expected_impressions']) * 100 : 0;
        }
    @endphp
    <x-page-box class="px-5 py-3.5 mb-5">
        <div class="flex flex-wrap items-start gap-x-8 gap-y-3">
            <div>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold mb-0.5">Campaign</p>
                <p class="text-sm font-bold text-gray-900">{{ $campaign->name }}</p>
            </div>
            <div>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold mb-0.5">Client</p>
                <p class="text-sm font-medium text-gray-700">{{ $campaign->client->name }}</p>
            </div>
            @if($campaign->start_date && $campaign->end_date)
            <div>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold mb-0.5">Period</p>
                <p class="text-sm font-medium text-gray-700">
                    {{ \Carbon\Carbon::parse($campaign->start_date)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($campaign->end_date)->format('M j, Y') }}
                </p>
            </div>
            @endif
            @if($showImprPacing)
            <div class="flex-1 min-w-[200px]">
                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold mb-1.5">Impression Pacing</p>
                <div class="flex items-center gap-2.5">
                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ ($pacingPct ?? 0) >= 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-[#F97316] to-[#FB923C]' }}"
                             style="width: {{ min($pacingPct ?? 0, 100) }}%"></div>
                    </div>
                    <span class="text-xs font-bold text-gray-700 whitespace-nowrap tabular-nums">{{ number_format($pacingPct ?? 0, 1) }}%</span>
                </div>
                @if(($pacingPct ?? 0) > 100)
                    <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">Overdelivering</p>
                @else
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ number_format($summary['impressions']) }} / {{ number_format($summary['expected_impressions']) }} expected</p>
                @endif
            </div>
            @endif
        </div>
    </x-page-box>

    {{-- ── Stat cards ──────────────────────────────────────────────── --}}
    @php
        $isVideo = $campaign->is_video;
        $row1Count = 3 + ($hasBudget ? 2 : 0) + ($isVideo ? 1 : 0) + ($isVideo && $hasBudget ? 1 : 0);
        $row2Count = 3 + ($hasBudget ? 2 : 0) + ($isVideo ? 1 : 0);
        $maxCols = max($row1Count, $row2Count);
        $statGridCols = match(true) {
            $maxCols >= 7 => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7',
            $maxCols >= 5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
            $maxCols >= 4 => 'grid-cols-2 sm:grid-cols-4',
            default       => 'grid-cols-3',
        };
    @endphp

    {{-- Row 1: Impressions · CTR · Reach · [Spent · CPM] · [Video Complete · Avg. CPV] --}}
    <div class="grid {{ $statGridCols }} gap-3 mb-3">

        {{-- Impressions --}}
        <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-blue-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Impressions</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['impressions'] ?? 0) }}</div>
        </div>

        {{-- CTR --}}
        <div class="bg-rose-50/50 border border-rose-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-rose-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-rose-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4.5V19a1 1 0 001 1h15M7 14l4-4 4 4 5-5m0 0h-3.207M20 9v3.207"/></svg>
            </div>
            <div class="text-[10px] font-bold text-rose-600 uppercase tracking-wider mb-1">CTR</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">
                @if(!empty($summary['impressions']))
                    {{ number_format(($summary['clicks'] / $summary['impressions']) * 100, 2) }}%
                @else —
                @endif
            </div>
        </div>

        {{-- Reach --}}
        <div class="bg-orange-50/50 border border-orange-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-orange-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-orange-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M16 19h4a1 1 0 001-1v-1a3 3 0 00-3-3h-2m-2.236-4a3 3 0 100-4M3 18v-1a3 3 0 013-3h4a3 3 0 013 3v1a1 1 0 01-1 1H4a1 1 0 01-1-1zm8-10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-orange-600 uppercase tracking-wider mb-1">Reach</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['uniques'] ?? 0) }}</div>
        </div>

        @if($hasBudget)
        {{-- Spent --}}
        <div class="bg-green-50/50 border border-green-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-green-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-green-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17.345a4.76 4.76 0 002.558 1.618c2.274.589 4.512-.446 4.999-2.31.487-1.866-1.273-3.9-3.546-4.49-2.273-.59-4.034-2.623-3.547-4.488.486-1.865 2.724-2.899 4.998-2.31.982.236 1.87.793 2.538 1.592m-3.879 12.171V21m0-18v2.2"/></svg>
            </div>
            <div class="text-[10px] font-bold text-green-600 uppercase tracking-wider mb-1">Spent</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">₪{{ number_format($spent ?? 0) }}</div>
        </div>

        {{-- CPM --}}
        <div class="bg-amber-50/50 border border-amber-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-amber-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-amber-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M8 7V6a1 1 0 011-1h11a1 1 0 011 1v7a1 1 0 01-1 1h-1M3 18v-7a1 1 0 011-1h11a1 1 0 011 1v7a1 1 0 01-1 1H4a1 1 0 01-1-1zm8-3.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">CPM</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">₪{{ number_format($cpm ?? 0, 2) }}</div>
        </div>
        @endif

        @if($isVideo)
        {{-- Video Complete --}}
        <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-blue-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 6H4a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1V7a1 1 0 00-1-1zm7 11l-6-2V9l6-2v10z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Video Complete</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['video_complete'] ?? 0) }}</div>
        </div>

        @if($hasBudget)
        {{-- Avg. CPV --}}
        <div class="bg-yellow-50/50 border border-yellow-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-yellow-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-yellow-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-yellow-600 uppercase tracking-wider mb-1">Avg. CPV</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">₪{{ number_format($summary['cpv'] ?? 0, 2) }}</div>
        </div>
        @endif
        @endif

    </div>

    {{-- Row 2: Clicks · Viewability · Frequency · [Budget · CPC] · [VCR] --}}
    <div class="grid {{ $statGridCols }} gap-3 mb-5">

        {{-- Clicks --}}
        <div class="bg-purple-50/50 border border-purple-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-purple-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-purple-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15v4m6-6v6m6-4v4m6-6v6M3 11l6-5 6 5 5.5-5.5"/></svg>
            </div>
            <div class="text-[10px] font-bold text-purple-600 uppercase tracking-wider mb-1">Clicks</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">{{ number_format($summary['clicks'] ?? 0) }}</div>
        </div>

        {{-- Viewability --}}
        <div class="bg-cyan-50/50 border border-cyan-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-cyan-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-cyan-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6.025A7.5 7.5 0 1 0 17.975 14H10V6.025Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 3c-.169 0-.334.014-.5.025V11h7.975c.011-.166.025-.331.025-.5A7.5 7.5 0 0 0 13.5 3Z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-cyan-600 uppercase tracking-wider mb-1">Viewability</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">
                @if(!empty($summary['impressions']))
                    {{ number_format(($summary['visible'] / $summary['impressions']) * 100, 2) }}%
                @else —
                @endif
            </div>
        </div>

        {{-- Frequency --}}
        <div class="bg-teal-50/50 border border-teal-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-teal-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-teal-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16 10 3-3m0 0-3-3m3 3H5v3m3 4-3 3m0 0 3 3m-3-3h14v-3"/></svg>
            </div>
            <div class="text-[10px] font-bold text-teal-600 uppercase tracking-wider mb-1">Frequency</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">
                @if(!empty($summary['uniques']))
                    {{ number_format($summary['impressions'] / $summary['uniques'], 2) }}
                @else —
                @endif
            </div>
        </div>

        @if($hasBudget)
        {{-- Budget --}}
        <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-emerald-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-emerald-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 011 1v2.6M17 8l-4-4M5 8a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 100 4h4a1 1 0 001-1v-2a1 1 0 00-1-1z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Budget</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">₪{{ number_format($budget ?? 0) }}</div>
        </div>

        {{-- CPC --}}
        <div class="bg-yellow-50/50 border border-yellow-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-yellow-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-yellow-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.6 16.733c.234.269.548.456.895.534a1.4 1.4 0 001.75-.762c.172-.615-.446-1.287-1.242-1.481-.796-.194-1.41-.861-1.241-1.481a1.4 1.4 0 011.75-.762c.343.077.654.26.888.524m-1.358 4.017v.617m0-5.939v.725M4 15v4m3-6v6M6 8.5L10.5 5 14 7.5 18 4m0 0h-3.5M18 4v3m2 8a5 5 0 11-10 0 5 5 0 0110 0z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-yellow-600 uppercase tracking-wider mb-1">CPC</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">₪{{ number_format($cpc ?? 0, 2) }}</div>
        </div>
        @endif

        @if($isVideo)
        {{-- VCR --}}
        <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-3 flex flex-col justify-center relative overflow-hidden group hover:shadow-md hover:border-indigo-200 transition-all hover:-translate-y-0.5 cursor-default">
            <div class="absolute -right-3 -bottom-3 opacity-10 text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider mb-1">VCR</div>
            <div class="text-xl font-black text-gray-900 leading-none relative z-10">
                @if(!empty($summary['vcr'])){{ number_format($summary['vcr'], 2) }}%@else —@endif
            </div>
        </div>
        @endif

    </div>

    {{-- ── Performance chart ─────────────────────────────────────── --}}
    <x-page-box class="mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-5 py-3.5 border-b border-gray-100">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Performance Overview</h2>
                <p class="text-[11px] text-gray-400">Daily Impressions &amp; CTR</p>
            </div>
            <div class="flex items-center gap-5 px-5 sm:px-0">
                <div class="flex items-center gap-1.5">
                    <div class="w-6 h-0.5 bg-blue-500 rounded"></div>
                    <span class="text-xs text-gray-500">Impressions</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-6 h-0.5 bg-[#F97316] rounded"></div>
                    <span class="text-xs text-gray-500">CTR (%)</span>
                </div>
            </div>
        </div>
        <div class="px-2 py-2">
            <div class="w-full h-[240px]">
                <canvas id="campaignChart"></canvas>
            </div>
        </div>
    </x-page-box>

    {{-- ── Tabs + Tables ──────────────────────────────────────────── --}}
    <script>
        window.__dashDateRows = @json($dashDateRows);
        window.__dashPlacementRows = @json($dashPlacementRows);
    </script>

    <x-page-box
        x-data="{
            activeTab:        localStorage.getItem('dashboardActiveTab') || 'date',
            perPage:          parseInt(localStorage.getItem('dashboardPerPage') || '25'),
            datePage:         1,
            placementPage:    1,
            search:           '',
            isVideo:          {{ $campaign->is_video ? 'true' : 'false' }},
            dateRows:         window.__dashDateRows,
            placementRows:    window.__dashPlacementRows,
            sortDateCol:      'date',
            sortDateDir:      'asc',
            sortPlacementCol: 'impr',
            sortPlacementDir: 'desc',
            nf(n)         { return Number(n || 0).toLocaleString(); },
            pct(num, den) { return den > 0 ? ((num / den) * 100).toFixed(2) + '%' : '—'; },
            _sortRows(rows, col, dir) {
                return [...rows].sort((a, b) => {
                    let va, vb;
                    if (col === 'ctr') { va = a.impr > 0 ? a.clicks / a.impr : 0; vb = b.impr > 0 ? b.clicks / b.impr : 0; }
                    else if (col === 'date' || col === 'name') { va = String(a[col] || ''); vb = String(b[col] || ''); return dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va); }
                    else { va = Number(a[col] || 0); vb = Number(b[col] || 0); }
                    return dir === 'asc' ? va - vb : vb - va;
                });
            },
            toggleDateSort(col) {
                if (this.sortDateCol === col) { this.sortDateDir = this.sortDateDir === 'asc' ? 'desc' : 'asc'; }
                else { this.sortDateCol = col; this.sortDateDir = (col === 'date') ? 'asc' : 'desc'; }
                this.datePage = 1;
            },
            togglePlacementSort(col) {
                if (this.sortPlacementCol === col) { this.sortPlacementDir = this.sortPlacementDir === 'asc' ? 'desc' : 'asc'; }
                else { this.sortPlacementCol = col; this.sortPlacementDir = (col === 'name') ? 'asc' : 'desc'; }
                this.placementPage = 1;
            },
            get filteredDateRows()       { const q = this.search.toLowerCase(); const r = q ? this.dateRows.filter(r => r.date?.toLowerCase().includes(q)) : this.dateRows; return this._sortRows(r, this.sortDateCol, this.sortDateDir); },
            get filteredPlacementRows()  { const q = this.search.toLowerCase(); const r = q ? this.placementRows.filter(r => r.name?.toLowerCase().includes(q)) : this.placementRows; return this._sortRows(r, this.sortPlacementCol, this.sortPlacementDir); },
            get pagedDateRows()          { return this.perPage === 0 ? this.filteredDateRows      : this.filteredDateRows.slice((this.datePage - 1) * this.perPage, this.datePage * this.perPage); },
            get dateTotalPages()         { return this.perPage === 0 ? 1 : Math.max(1, Math.ceil(this.filteredDateRows.length / this.perPage)); },
            get pagedPlacementRows()     { return this.perPage === 0 ? this.filteredPlacementRows : this.filteredPlacementRows.slice((this.placementPage - 1) * this.perPage, this.placementPage * this.perPage); },
            get placementTotalPages()    { return this.perPage === 0 ? 1 : Math.max(1, Math.ceil(this.filteredPlacementRows.length / this.perPage)); },
            get totalPlacementImpr()     { return this.placementRows.reduce((s, r) => s + (r.impr || 0), 0); },
            sharePct(impr)               { const t = this.totalPlacementImpr; return t > 0 ? ((impr / t) * 100).toFixed(1) : '0'; },
            setPerPage(v) { this.perPage = parseInt(v); this.datePage = 1; this.placementPage = 1; localStorage.setItem('dashboardPerPage', v); }
        }"
        x-init="$watch('activeTab', v => localStorage.setItem('dashboardActiveTab', v))"
        class="overflow-hidden">

        {{-- Tab header --}}
        <div class="flex items-center border-b border-gray-100 px-5 gap-5 overflow-x-auto [&::-webkit-scrollbar]:hidden">
            <button @click="activeTab='date'"
                    :class="activeTab==='date' ? 'border-b-2 border-[#F97316] text-[#F97316]' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700'"
                    class="py-3.5 text-sm font-medium transition-colors focus:outline-none whitespace-nowrap shrink-0 cursor-pointer">
                By Date
            </button>
            <button @click="activeTab='placement'"
                    :class="activeTab==='placement' ? 'border-b-2 border-[#F97316] text-[#F97316]' : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700'"
                    class="py-3.5 text-sm font-medium transition-colors focus:outline-none whitespace-nowrap shrink-0 cursor-pointer">
                By Placement
            </button>
            <div class="flex-1 hidden sm:block"></div>
            {{-- Search --}}
            <div class="relative py-2.5 shrink-0 hidden sm:block">
                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" placeholder="Search..."
                       class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-md text-gray-600 focus:ring-1 focus:ring-[#F97316] focus:border-[#F97316] w-36 outline-none">
            </div>
            {{-- Rows per page --}}
            <div class="flex items-center gap-2 py-2.5 shrink-0">
                <span class="text-xs text-gray-400 hidden sm:inline">Rows:</span>
                <select @change="setPerPage($event.target.value)"
                        class="text-xs border border-gray-200 rounded-md pl-2 pr-6 py-1 text-gray-600 bg-white focus:outline-none focus:ring-1 focus:ring-[#F97316]/30 cursor-pointer">
                    <option value="10"  :selected="perPage === 10">10</option>
                    <option value="25"  :selected="perPage === 25">25</option>
                    <option value="50"  :selected="perPage === 50">50</option>
                    <option value="0"   :selected="perPage === 0">All</option>
                </select>
            </div>
        </div>

        {{-- BY DATE TABLE --}}
        <div x-show="activeTab === 'date'">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70">
                            <th class="text-left pl-5 pr-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='date' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('date')"><div class="flex items-center gap-1">Date <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='date'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='date'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='impr' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('impr')"><div class="flex items-center justify-end gap-1">Impressions <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='impr'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='impr'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='clicks' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('clicks')"><div class="flex items-center justify-end gap-1">Clicks <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='clicks'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='clicks'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='ctr' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('ctr')"><div class="flex items-center justify-end gap-1">CTR <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='ctr'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='ctr'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='v25' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('v25')"><div class="flex items-center justify-end gap-1">25% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='v25'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='v25'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='v50' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('v50')"><div class="flex items-center justify-end gap-1">50% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='v50'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='v50'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='v75' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('v75')"><div class="flex items-center justify-end gap-1">75% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='v75'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='v75'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right pr-5 pl-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortDateCol==='v100' ? 'text-[#F97316]' : 'text-gray-500'" @click="toggleDateSort('v100')"><div class="flex items-center justify-end gap-1">100% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortDateCol==='v100'&&sortDateDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortDateCol==='v100'&&sortDateDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="row in pagedDateRows" :key="row.date">
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="pl-5 pr-3 py-2.5 font-medium text-gray-700 whitespace-nowrap" x-text="row.date"></td>
                                <td class="px-3 py-2.5 text-right text-gray-700 tabular-nums font-medium" x-text="nf(row.impr)"></td>
                                <td class="px-3 py-2.5 text-right text-gray-700 tabular-nums font-medium" x-text="nf(row.clicks)"></td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold text-[#F97316]" x-text="pct(row.clicks, row.impr)"></td>
                                <td x-show="isVideo" class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v25)"></td>
                                <td x-show="isVideo" class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v50)"></td>
                                <td x-show="isVideo" class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v75)"></td>
                                <td x-show="isVideo" class="pr-5 pl-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v100)"></td>
                            </tr>
                        </template>
                        {{-- Totals row --}}
                        <tr class="border-t-2 border-gray-200 bg-orange-50/30">
                            <td class="pl-5 pr-3 py-2.5 text-[10px] font-bold text-gray-600 uppercase tracking-wide">Total</td>
                            <td class="px-3 py-2.5 text-right font-bold text-gray-900 tabular-nums">{{ number_format($summary['impressions'] ?? 0) }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-gray-900 tabular-nums">{{ number_format($summary['clicks'] ?? 0) }}</td>
                            <td class="px-3 py-2.5 text-right font-bold text-[#F97316] tabular-nums">
                                @if(!empty($summary['impressions'])){{ number_format(($summary['clicks'] / $summary['impressions']) * 100, 2) }}%@else —@endif
                            </td>
                            @if($campaign->is_video)
                            <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell">—</td>
                            <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell">—</td>
                            <td class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell">—</td>
                            <td class="pr-5 pl-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell">—</td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
            {{-- Date pagination --}}
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100" x-show="perPage > 0">
                <p class="text-xs text-gray-400" x-text="'Showing ' + Math.min(datePage * perPage, filteredDateRows.length) + ' of ' + filteredDateRows.length + ' rows'"></p>
                <div class="flex items-center gap-1">
                    <button @click="datePage = Math.max(1, datePage - 1)" :disabled="datePage <= 1"
                            class="px-2.5 py-1 text-xs border border-gray-200 rounded text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">‹ Prev</button>
                    <button class="px-2.5 py-1 text-xs border border-[#F97316] rounded bg-[#F97316] text-white font-semibold" x-text="datePage"></button>
                    <button @click="datePage = Math.min(dateTotalPages, datePage + 1)" :disabled="datePage >= dateTotalPages"
                            class="px-2.5 py-1 text-xs border border-gray-200 rounded text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">Next ›</button>
                </div>
            </div>
        </div>

        {{-- BY PLACEMENT TABLE --}}
        <div x-show="activeTab === 'placement'" x-cloak style="display:none">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70">
                            <th class="text-left pl-5 pr-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='name' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('name')"><div class="flex items-center gap-1">Placement <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='name'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='name'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='impr' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('impr')"><div class="flex items-center justify-end gap-1">Impressions <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='impr'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='impr'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='clicks' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('clicks')"><div class="flex items-center justify-end gap-1">Clicks <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='clicks'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='clicks'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='ctr' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('ctr')"><div class="flex items-center justify-end gap-1">CTR <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='ctr'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='ctr'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide hidden sm:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='impr' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('impr')"><div class="flex items-center justify-end gap-1">Share <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='impr'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='impr'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='v25' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('v25')"><div class="flex items-center justify-end gap-1">25% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v25'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v25'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='v50' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('v50')"><div class="flex items-center justify-end gap-1">50% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v50'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v50'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right px-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='v75' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('v75')"><div class="flex items-center justify-end gap-1">75% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v75'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v75'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                            <th x-show="isVideo" class="text-right pr-5 pl-3 py-2.5 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap hidden lg:table-cell cursor-pointer select-none transition-colors hover:text-gray-700" :class="sortPlacementCol==='v100' ? 'text-[#F97316]' : 'text-gray-500'" @click="togglePlacementSort('v100')"><div class="flex items-center justify-end gap-1">100% <span class="flex flex-col gap-0.5"><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v100'&&sortPlacementDir==='asc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-1.5 h-1.5" :class="sortPlacementCol==='v100'&&sortPlacementDir==='desc' ? 'text-[#F97316]' : 'text-gray-300'" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="row in pagedPlacementRows" :key="row.name">
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="pl-5 pr-3 py-2.5 font-medium text-gray-800 truncate max-w-[140px] md:max-w-none" x-text="row.name"></td>
                                <td class="px-3 py-2.5 text-right text-gray-700 tabular-nums font-medium" x-text="nf(row.impr)"></td>
                                <td class="px-3 py-2.5 text-right text-gray-700 tabular-nums font-medium" x-text="nf(row.clicks)"></td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold text-[#F97316]" x-text="pct(row.clicks, row.impr)"></td>
                                <td class="px-3 py-2.5 text-right hidden sm:table-cell">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-14 h-1 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#F97316]/70 rounded-full" :style="'width:'+sharePct(row.impr)+'%'"></div>
                                        </div>
                                        <span class="text-gray-500 tabular-nums w-9 text-right" x-text="sharePct(row.impr)+'%'"></span>
                                    </div>
                                </td>
                                <td x-show="isVideo" class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v25)"></td>
                                <td x-show="isVideo" class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v50)"></td>
                                <td x-show="isVideo" class="px-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v75)"></td>
                                <td x-show="isVideo" class="pr-5 pl-3 py-2.5 text-right text-gray-500 tabular-nums hidden lg:table-cell" x-text="nf(row.v100)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            {{-- Placement pagination --}}
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100" x-show="perPage > 0">
                <p class="text-xs text-gray-400" x-text="'Showing ' + Math.min(placementPage * perPage, filteredPlacementRows.length) + ' of ' + filteredPlacementRows.length + ' placements'"></p>
                <div class="flex items-center gap-1">
                    <button @click="placementPage = Math.max(1, placementPage - 1)" :disabled="placementPage <= 1"
                            class="px-2.5 py-1 text-xs border border-gray-200 rounded text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">‹ Prev</button>
                    <button class="px-2.5 py-1 text-xs border border-[#F97316] rounded bg-[#F97316] text-white font-semibold" x-text="placementPage"></button>
                    <button @click="placementPage = Math.min(placementTotalPages, placementPage + 1)" :disabled="placementPage >= placementTotalPages"
                            class="px-2.5 py-1 text-xs border border-gray-200 rounded text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">Next ›</button>
                </div>
            </div>
        </div>

    </x-page-box>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        // ── Clear stale localStorage when switching campaigns ──────────────
        if (localStorage.getItem('campaign_id') != {{ $campaign->id }}) {
            localStorage.removeItem('dateRange');
            localStorage.removeItem('dashboardActiveTab');
            localStorage.setItem('campaign_id', {{ $campaign->id }});
        }
        (function() {
            function removeIfExpired(key) {
                const raw = localStorage.getItem(key);
                if (!raw) return;
                if (window.getWithExpiry) { if (window.getWithExpiry(key) === null) localStorage.removeItem(key); return; }
                try { const obj = JSON.parse(raw); if (obj?.expiry && Date.now() > obj.expiry) localStorage.removeItem(key); } catch(e) {}
            }
            removeIfExpired('dateRange');
            removeIfExpired('dashboardActiveTab');
        })();

        // ── Chart.js ────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const ctx = document.getElementById('campaignChart');
            if (!ctx) return;

            const gradientBlue   = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
            gradientBlue.addColorStop(0, 'rgba(59,130,246,0.18)');
            gradientBlue.addColorStop(1, 'rgba(59,130,246,0)');

            const gradientOrange = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
            gradientOrange.addColorStop(0, 'rgba(249,115,22,0.15)');
            gradientOrange.addColorStop(1, 'rgba(249,115,22,0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @js($chartLabels),
                    datasets: [
                        {
                            label: 'Impressions',
                            data: @js($chartImpressions),
                            borderColor: '#3B82F6',
                            backgroundColor: gradientBlue,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y',
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#3B82F6',
                        },
                        {
                            label: 'CTR (%)',
                            data: @js(array_map(fn($i, $c) => $i ? round(($c / $i) * 100, 2) : 0, $chartImpressions, $chartClicks)),
                            borderColor: '#F97316',
                            backgroundColor: gradientOrange,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y1',
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#F97316',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(255,255,255,0.97)',
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
                            grid: { display: false },
                            ticks: { color: '#9CA3AF', font: { size: 10 }, maxTicksLimit: 10 }
                        },
                        y: {
                            position: 'left',
                            grid: { color: '#F3F4F6' },
                            ticks: {
                                color: '#9CA3AF', font: { size: 10 },
                                callback: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(0)+'k' : v
                            }
                        },
                        y1: {
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { color: '#9CA3AF', font: { size: 10 }, callback: v => parseFloat(v.toFixed(2)) + '%' }
                        }
                    }
                }
            });
        });

        // ── Dashboard date filter Alpine component ──────────────────────────
        function dashDateFilter() {
            const firstDate = '{{ $firstReportDate }}';
            function parseDate(str) {
                if (!str) return null;
                const [y, m, d] = str.split('-').map(Number);
                return new Date(y, m - 1, d);
            }
            function toInputVal(d) {
                if (!d) return '';
                return d.getFullYear() + '-'
                    + String(d.getMonth() + 1).padStart(2, '0') + '-'
                    + String(d.getDate()).padStart(2, '0');
            }
            return {
                dateFrom:   parseDate('{{ request('start_date', $firstReportDate) }}'),
                dateTo:     parseDate('{{ request('end_date', date('Y-m-d')) }}'),
                openPicker: null,
                calView:    new Date(),

                formatDate(d) {
                    if (!d) return '';
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },
                toInputVal(d) { return toInputVal(d); },
                get calMonthYear() {
                    return this.calView.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                },
                get calDays() {
                    const y = this.calView.getFullYear(), m = this.calView.getMonth();
                    const firstDow = new Date(y, m, 1).getDay();
                    const dim = new Date(y, m + 1, 0).getDate();
                    const cells = [];
                    for (let i = 0; i < firstDow; i++) cells.push({ key: 'e'+i, date: null, full: null });
                    for (let day = 1; day <= dim; day++) cells.push({ key: `${y}-${m}-${day}`, date: day, full: new Date(y, m, day) });
                    return cells;
                },
                togglePicker(which) {
                    if (this.openPicker === which) { this.openPicker = null; return; }
                    this.openPicker = which;
                    this.calView = new Date(which === 'from' ? this.dateFrom : this.dateTo);
                },
                prevMonth() { this.calView = new Date(this.calView.getFullYear(), this.calView.getMonth() - 1, 1); },
                nextMonth() { this.calView = new Date(this.calView.getFullYear(), this.calView.getMonth() + 1, 1); },
                selectDate(d) {
                    if (this.openPicker === 'from') {
                        this.dateFrom = d;
                        if (d > this.dateTo) this.dateTo = d;
                        this.openPicker = 'to';
                        this.calView = new Date(this.dateTo);
                    } else {
                        this.dateTo = d;
                        if (d < this.dateFrom) this.dateFrom = d;
                        this.openPicker = null;
                        this.$nextTick(() => this.$refs.dateForm.submit());
                    }
                },
                applyPreset(preset) {
                    const today = new Date();
                    switch (preset) {
                        case 'all':
                            this.dateFrom = parseDate(firstDate);
                            this.dateTo   = today;
                            break;
                        case 'yesterday': {
                            const y = new Date(today); y.setDate(today.getDate() - 1);
                            this.dateFrom = y; this.dateTo = y;
                            break;
                        }
                        case 'last_7': {
                            const s = new Date(today); s.setDate(today.getDate() - 6);
                            this.dateFrom = s; this.dateTo = today;
                            break;
                        }
                        case 'mtd':
                            this.dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
                            this.dateTo   = today;
                            break;
                        case 'ytd':
                            this.dateFrom = new Date(today.getFullYear(), 0, 1);
                            this.dateTo   = today;
                            break;
                    }
                    this.$nextTick(() => this.$refs.dateForm.submit());
                },
                isSelected(d)   { return d && (this.isSameDay(d, this.dateFrom) || this.isSameDay(d, this.dateTo)); },
                isInRange(d)    { return d && this.dateFrom && this.dateTo && d > this.dateFrom && d < this.dateTo; },
                isBeforeMin(d)  { return this.openPicker === 'to' && d && this.dateFrom && d < this.dateFrom; },
                isSameDay(a, b) { return a && b && a.toDateString() === b.toDateString(); },
            };
        }
    </script>
    @endpush

</x-app-layout>
