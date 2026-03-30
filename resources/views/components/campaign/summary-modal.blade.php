@props(['campaign'])
@php
    $tr   = $campaign->targeting_rules ?? [];
    $fmt  = fn($arr, $def='All') => empty($arr) ? $def : implode(', ', $arr);
    $cities = $tr['cities'] ?? [];
    if (is_string($cities)) $cities = json_decode($cities, true) ?? [];

    $lines   = [];
    $lines[] = 'Campaign:    ' . $campaign->name;
    $lines[] = 'Client:      ' . ($campaign->client->name ?? '—');
    $lines[] = 'Status:      ' . ucfirst($campaign->status ?? '—');
    $lines[] = 'Period:      '
        . ($campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('M j, Y') : '—')
        . ' – '
        . ($campaign->end_date   ? \Carbon\Carbon::parse($campaign->end_date)->format('M j, Y')   : '—');
    if (auth()->user()->hasPermission('can_view_budget')) {
        $lines[] = 'Budget:      ' . ($campaign->budget ? number_format($campaign->budget) . ' NIS' : '—');
    }
    $lines[] = 'Impressions: ' . ($campaign->expected_impressions ? number_format($campaign->expected_impressions) : '—');

    $lines[] = '';
    $lines[] = str_repeat('─', 34);
    $lines[] = 'TARGETING';
    $lines[] = str_repeat('─', 34);
    $lines[] = 'Demographics';
    $lines[] = '  Gender:       ' . $fmt($tr['genders'] ?? []);
    $lines[] = '  Age:          ' . $fmt($tr['ages']    ?? []);
    $lines[] = '  Income:       ' . $fmt($tr['incomes'] ?? []);
    $lines[] = '';
    $lines[] = 'Devices & Technology';
    $lines[] = '  Device Types: ' . $fmt($tr['device_types'] ?? ['Mobile','Tablet']);
    $lines[] = '  OS:           ' . $fmt($tr['os'] ?? ['iOS','Android','Windows','macOS']);
    $lines[] = '  Connection:   ' . $fmt($tr['connection_types'] ?? ['WiFi','Cellular']);
    $lines[] = '';
    $lines[] = 'Inventory';
    $lines[] = '  Environment:  ' . $fmt($tr['environments'] ?? []);
    $lines[] = '';
    $lines[] = 'Schedule';
    $lines[] = '  Days:         ' . $fmt($tr['days'] ?? []);
    $lines[] = '';
    $lines[] = 'Geo Targeting';
    $lines[] = '  Countries:    ' . $fmt($tr['countries'] ?? ['Israel']);
    if (!empty($tr['regions'])) $lines[] = '  Regions:      ' . implode(', ', $tr['regions']);
    if (!empty($cities))        $lines[] = '  Cities:       ' . implode(', ', $cities);
    if ($campaign->locations->isNotEmpty()) {
        $lines[] = '  Proximity:';
        foreach ($campaign->locations as $loc) {
            $km      = round(($loc->radius_meters ?? 1000) / 1000);
            $lines[] = '    • ' . ($loc->name ?? 'Unnamed')
                . ' (' . number_format((float)$loc->lat, 4) . ', ' . number_format((float)$loc->lng, 4)
                . ', ' . $km . 'km)';
        }
    }

    $lines[] = '';
    $lines[] = str_repeat('─', 34);
    $lines[] = 'AUDIENCES (' . $campaign->audiences->count() . ')';
    $lines[] = str_repeat('─', 34);
    if ($campaign->audiences->isEmpty()) {
        $lines[] = '  None connected';
    } else {
        foreach ($campaign->audiences as $audience) {
            $lines[] = '  • ' . $audience->name . '  [' . $audience->main_category . ']';
        }
    }

    $lines[] = '';
    $lines[] = str_repeat('─', 34);
    $lines[] = 'CREATIVES (' . $campaign->creatives->count() . ')';
    $lines[] = str_repeat('─', 34);
    if ($campaign->creatives->isEmpty()) {
        $lines[] = '  None added';
    } else {
        foreach ($campaign->creatives as $creative) {
            $lines[] = '';
            $lines[] = '  ' . $creative->name . '  [' . ($creative->status ? 'Active' : 'Inactive') . ']';
            if ($creative->landing) $lines[] = '  Landing: ' . $creative->landing;
            foreach ($creative->files as $file) {
                $dim     = ($file->width && $file->height) ? $file->width . '×' . $file->height : '—';
                $kb      = $file->size ? round($file->size / 1024) . ' KB' : '—';
                $lines[] = '    ▸ ' . $file->name . '  ' . $dim . '  ' . ($file->mime_type ?? '—') . '  ' . $kb;
            }
        }
    }

    $summaryText = implode("\n", $lines);
@endphp

{{-- Backdrop --}}
<div x-show="summaryOpen" x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-black/40 backdrop-blur-sm"
    style="z-index:9002"></div>

{{-- Modal --}}
<div x-show="summaryOpen" x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="fixed inset-0 flex items-center justify-center p-4"
    style="z-index:9003"
    @click.self="summaryOpen = false"
    @keydown.window.escape="summaryOpen = false"
    x-effect="if (summaryOpen) {
        const ta = document.getElementById('summaryTextarea');
        if (!ta) return;
        const val = (n) => document.querySelector('[name=&quot;' + n + '&quot;]')?.value ?? '';
        const sep = '──────────────────────────────────';
        const fmt = (arr, def) => (!arr || arr.length === 0) ? (def || 'All') : arr.join(', ');
        const fmtNum = (v) => v ? Number(v).toLocaleString() : '—';
        const fmtDate = (v) => { if (!v) return '—'; const d = new Date(v + 'T12:00:00'); return d.toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'}); };

        let td = {}; const te = document.querySelector('[x-data^=&quot;targetingData&quot;]');
        if (te) td = Alpine.$data(te);

        const canViewBudget = {{ auth()->user()->hasPermission('can_view_budget') ? 'true' : 'false' }};
        let lines = [];
        lines.push('Campaign:    ' + (val('name') || '—'));
        lines.push('Client:      ' + {{ Js::from($campaign->client->name ?? '—') }});
        const statusEl = document.querySelector('[name=&quot;status&quot;]');
        lines.push('Status:      ' + (statusEl ? statusEl.value.charAt(0).toUpperCase() + statusEl.value.slice(1) : '—'));
        lines.push('Period:      ' + fmtDate(val('start_date')) + ' – ' + fmtDate(val('end_date')));
        if (canViewBudget) lines.push('Budget:      ' + (val('budget') ? fmtNum(val('budget')) + ' NIS' : '—'));
        lines.push('Impressions: ' + (val('expected_impressions') ? fmtNum(val('expected_impressions')) : '—'));
        lines.push('', sep, 'TARGETING', sep);
        lines.push('Demographics');
        lines.push('  Gender:       ' + fmt(td.genders));
        lines.push('  Age:          ' + fmt(td.ages));
        lines.push('  Income:       ' + fmt(td.incomes));
        lines.push('', 'Devices \x26 Technology');
        lines.push('  Device Types: ' + fmt(td.deviceTypes, 'Mobile,Tablet'));
        lines.push('  OS:           ' + fmt(td.os, 'iOS,Android,Windows,macOS'));
        lines.push('  Connection:   ' + fmt(td.connectionTypes, 'WiFi,Cellular'));
        lines.push('', 'Inventory');
        lines.push('  Environment:  ' + fmt(td.environments));
        lines.push('', 'Schedule');
        lines.push('  Days:         ' + fmt(td.days));
        if (td.timeStart || td.timeEnd) lines.push('  Time:         ' + (td.timeStart || '—') + ' – ' + (td.timeEnd || '—'));
        lines.push('', 'Geo Targeting');
        lines.push('  Countries:    ' + fmt(td.countries, 'Israel'));
        if (td.regions &amp;&amp; td.regions.length) lines.push('  Regions:      ' + td.regions.join(', '));
        if (td.cities &amp;&amp; td.cities.length) lines.push('  Cities:       ' + td.cities.join(', '));

        const audienceEl = document.querySelector('[x-data^=&quot;audienceManager&quot;]');
        const audiences = audienceEl ? Alpine.$data(audienceEl).connected : [];
        lines.push('', sep, 'AUDIENCES (' + audiences.length + ')', sep);
        if (audiences.length === 0) { lines.push('  None connected'); }
        else { audiences.forEach(a => lines.push('  • ' + a.name + '  [' + a.main_category + ']')); }

        // Keep server-rendered creatives section from original
        const orig = ta.value;
        const creativesMatch = orig.match(/\n──────────────────────────────────\nCREATIVES[\s\S]*$/);
        lines.push('', sep);
        ta.value = lines.join('\n') + (creativesMatch ? creativesMatch[0].replace(/^\n──────────────────────────────────\n/, '') : 'CREATIVES (0)\n' + sep + '\n  None added');
    }">

    <div x-data="{ copied: false }"
        class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col border border-gray-100 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-gray-900">Campaign Summary</h2>
            </div>
            <button type="button" @click="summaryOpen = false"
                class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto p-5 min-h-0">
            <textarea id="summaryTextarea" readonly
                class="w-full h-full min-h-[50vh] text-xs font-mono text-gray-700 bg-gray-50 border border-gray-200 rounded-xl p-4 resize-none focus:outline-none leading-relaxed">{{ $summaryText }}</textarea>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
            <button type="button" @click="summaryOpen = false"
                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all">
                Close
            </button>
            <button type="button"
                @click="navigator.clipboard.writeText(document.getElementById('summaryTextarea').value); copied = true; setTimeout(() => copied = false, 2000)"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-[#F97316] hover:bg-orange-600 rounded-xl transition-all">
                <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <svg x-show="copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
            </button>
        </div>
    </div>
</div>
