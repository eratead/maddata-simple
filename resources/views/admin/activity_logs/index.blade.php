<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Activity Logs</h1>
@endpush

    {{-- Filter bar --}}
    <x-page-box class="p-4 mb-4">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}"
              class="flex flex-col lg:flex-row gap-4 lg:items-end">

            <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-5 gap-3 flex-1">

                {{-- Action --}}
                <div>
                    <x-input-label for="action" value="Action" />
                    <select name="action" id="action"
                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer">
                        <option value="">All Actions</option>
                        <option value="created"  {{ request('action') === 'created'  ? 'selected' : '' }}>Created</option>
                        <option value="updated"  {{ request('action') === 'updated'  ? 'selected' : '' }}>Updated</option>
                        <option value="deleted"  {{ request('action') === 'deleted'  ? 'selected' : '' }}>Deleted</option>
                    </select>
                </div>

                {{-- User --}}
                <div>
                    <x-input-label for="user_id" value="User" />
                    <select name="user_id" id="user_id"
                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Campaign --}}
                <div class="col-span-2 lg:col-span-1">
                    <x-input-label for="campaign" value="Campaign" />
                    <input type="text" name="campaign" id="campaign" value="{{ request('campaign') }}" placeholder="Search name…"
                           class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
                </div>

                {{-- Date range — Alpine datepicker --}}
                <div class="col-span-2 grid grid-cols-2 gap-3 relative"
                     x-data="activityDatePicker()" @click.away="openPicker=null">

                    {{-- Hidden inputs submitted with the GET form --}}
                    <input type="hidden" name="date_start" :value="toInputVal(dateFrom)">
                    <input type="hidden" name="date_end"   :value="toInputVal(dateTo)">

                    {{-- From --}}
                    <div>
                        <x-input-label value="From Date" />
                        <button type="button" @click="togglePicker('from')"
                                :class="openPicker==='from' ? 'border-[#F97316] ring-2 ring-[#F97316]/30' : 'border-gray-300 hover:border-gray-400'"
                                class="mt-1.5 w-full flex items-center gap-2 px-3 py-2 bg-white border rounded-lg text-sm shadow-sm transition-colors cursor-pointer">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span :class="dateFrom ? 'text-gray-700' : 'text-gray-400'"
                                  x-text="dateFrom ? formatDate(dateFrom) : 'From date'"></span>
                        </button>
                    </div>

                    {{-- To --}}
                    <div>
                        <x-input-label value="To Date" />
                        <button type="button" @click="togglePicker('to')"
                                :class="openPicker==='to' ? 'border-[#F97316] ring-2 ring-[#F97316]/30' : 'border-gray-300 hover:border-gray-400'"
                                class="mt-1.5 w-full flex items-center gap-2 px-3 py-2 bg-white border rounded-lg text-sm shadow-sm transition-colors cursor-pointer">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span :class="dateTo ? 'text-gray-700' : 'text-gray-400'"
                                  x-text="dateTo ? formatDate(dateTo) : 'To date'"></span>
                        </button>
                    </div>

                    {{-- Shared calendar dropdown --}}
                    <div x-show="openPicker !== null" x-cloak
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"  x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute top-full left-0 mt-1 z-50 bg-white border border-gray-200 rounded-xl shadow-xl p-3 w-64 select-none"
                         style="display:none">

                        {{-- Month nav --}}
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" @click.stop="prevMonth()" class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <span x-text="calMonthYear" class="text-xs font-semibold text-gray-800"></span>
                            <button type="button" @click.stop="nextMonth()" class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>

                        {{-- Day headers --}}
                        <div class="grid grid-cols-7 mb-1">
                            <template x-for="h in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="h">
                                <div class="text-center text-[9px] font-semibold text-gray-400 pb-1" x-text="h"></div>
                            </template>
                        </div>

                        {{-- Day cells --}}
                        <div class="grid grid-cols-7">
                            <template x-for="cell in calDays" :key="cell.key">
                                <div class="flex items-center justify-center h-7">
                                    <button type="button"
                                            x-show="cell.date !== null"
                                            @click.stop="!isBeforeMin(cell.full) && selectDate(cell.full)"
                                            :disabled="isBeforeMin(cell.full)"
                                            :class="{
                                                'bg-[#F97316] text-white font-bold shadow-sm': isSelected(cell.full),
                                                'bg-orange-50 text-[#F97316]': isInRange(cell.full) && !isSelected(cell.full),
                                                'text-gray-300 cursor-not-allowed': isBeforeMin(cell.full),
                                                'text-gray-700 hover:bg-gray-100 cursor-pointer': !isSelected(cell.full) && !isInRange(cell.full) && !isBeforeMin(cell.full)
                                            }"
                                            class="w-6 h-6 text-[11px] rounded-full transition-colors"
                                            x-text="cell.date"></button>
                                    <div x-show="cell.date === null" class="w-6 h-6"></div>
                                </div>
                            </template>
                        </div>

                        {{-- Footer hint + clear --}}
                        <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                            <button type="button" @click.stop="clearDates()"
                                    class="text-[10px] text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">Clear</button>
                            <span class="text-[10px]"
                                  :class="openPicker==='from' ? 'text-blue-400' : 'text-[#F97316]'"
                                  x-text="openPicker==='from' ? '← Pick start date' : 'Pick end date →'"></span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Search + actions --}}
            <div class="flex flex-col sm:flex-row gap-3 lg:w-auto w-full border-t border-gray-100 pt-3 mt-1 lg:border-t-0 lg:pt-0 lg:mt-0 shrink-0">
                <div class="flex-1 sm:w-48 lg:w-44 shrink-0">
                    <x-input-label for="search" value="Search" class="lg:hidden" />
                    <div class="relative mt-1.5">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search details…"
                               class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
                    </div>
                </div>
                <div class="flex items-end gap-2 shrink-0">
                    <a href="{{ route('admin.activity-logs.index', ['clear' => 1]) }}">
                        <x-secondary-button>Reset</x-secondary-button>
                    </a>
                    <x-primary-button type="submit">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter
                    </x-primary-button>
                </div>
            </div>

        </form>
    </x-page-box>

    {{-- Table --}}
    <x-page-box class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full w-full whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Date</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">User</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Action</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Campaign</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Subject</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-sm">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-400 text-xs">
                                {{ $log->created_at->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $log->user->name ?? 'System' }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $actionColor = match($log->action) {
                                        'deleted' => 'red',
                                        'created' => 'emerald',
                                        default   => 'blue',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    bg-{{ $actionColor }}-50 text-{{ $actionColor }}-700 border border-{{ $actionColor }}-100">
                                    {{ ucfirst($log->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                @if($log->campaign)
                                    <a href="{{ route('campaigns.edit', $log->campaign_id) }}"
                                       class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors">
                                        {{ Str::limit($log->campaign->name, 25) }}
                                    </a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                @if($log->subject_type === 'App\Models\Creative' && $log->subject)
                                    <a href="{{ route('creatives.edit', $log->subject_id) }}"
                                       class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors">
                                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                    </a>
                                @elseif($log->subject_type === 'App\Models\CreativeFile' && $log->subject && $log->subject->creative)
                                    <a href="{{ route('creatives.edit', $log->subject->creative_id) }}"
                                       class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors">
                                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                    </a>
                                    <span class="text-xs text-gray-400 block">(of Creative #{{ $log->subject->creative_id }})</span>
                                @else
                                    {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 max-w-xs" title="{{ $log->description }}">
                                @if($log->changes)
                                    <div class="text-xs space-y-1">
                                        @foreach($log->changes as $field => $change)
                                            @if($field !== 'updated_at')
                                                @php
                                                    $hasOld    = is_array($change) && array_key_exists('old', $change);
                                                    $oldRaw    = $hasOld ? $change['old'] : null;
                                                    $oldIsArr  = is_array($oldRaw);
                                                    $oldString = $oldIsArr ? json_encode($oldRaw) : (string)$oldRaw;
                                                    $oldDisplay= $oldIsArr ? '…' : Str::limit($oldString, 15);

                                                    $hasNew    = is_array($change) && array_key_exists('new', $change);
                                                    $newRaw    = $hasNew ? $change['new'] : $change;
                                                    $newIsArr  = is_array($newRaw);
                                                    $newString = $newIsArr ? json_encode($newRaw) : (string)$newRaw;
                                                    $newDisplay= $newIsArr ? 'array…' : Str::limit($newString, 25);
                                                @endphp
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-medium text-gray-600 capitalize w-20 shrink-0 truncate">{{ str_replace('_', ' ', $field) }}:</span>
                                                    @if($hasOld && $oldRaw !== null)
                                                        <span class="line-through text-gray-300 truncate max-w-[80px]" title="{{ $oldString }}">{{ $oldDisplay }}</span>
                                                        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                                    @endif
                                                    <span class="text-emerald-600 truncate max-w-[120px]" title="{{ $newString }}">{{ $newDisplay }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400">{{ $log->description }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">
                                No activity logs match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page-box>

    {{-- Pagination --}}
    <div class="mt-4 flex justify-end">
        {{ $logs->links() }}
    </div>

    @push('scripts')
    <script>
    function activityDatePicker() {
        function parseDate(str) {
            if (!str) return null;
            // Parse as local time to avoid UTC offset shifting the day
            const [y, m, d] = str.split('-').map(Number);
            return new Date(y, m - 1, d);
        }

        return {
            dateFrom: parseDate('{{ request('date_start') }}'),
            dateTo:   parseDate('{{ request('date_end') }}'),
            openPicker: null,
            calView: new Date(),

            formatDate(d) {
                if (!d) return '';
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            },
            toInputVal(d) {
                if (!d) return '';
                return d.getFullYear() + '-' +
                       String(d.getMonth() + 1).padStart(2, '0') + '-' +
                       String(d.getDate()).padStart(2, '0');
            },
            get calMonthYear() {
                return this.calView.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            },
            get calDays() {
                const year = this.calView.getFullYear(), month = this.calView.getMonth();
                const firstDow = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const cells = [];
                for (let i = 0; i < firstDow; i++) cells.push({ key: 'e' + i, date: null, full: null });
                for (let d = 1; d <= daysInMonth; d++) cells.push({ key: `${year}-${month}-${d}`, date: d, full: new Date(year, month, d) });
                return cells;
            },
            togglePicker(which) {
                if (this.openPicker === which) { this.openPicker = null; return; }
                this.openPicker = which;
                this.calView = new Date(which === 'from' ? (this.dateFrom || new Date()) : (this.dateTo || new Date()));
            },
            prevMonth() { this.calView = new Date(this.calView.getFullYear(), this.calView.getMonth() - 1, 1); },
            nextMonth() { this.calView = new Date(this.calView.getFullYear(), this.calView.getMonth() + 1, 1); },
            selectDate(d) {
                if (this.openPicker === 'from') {
                    this.dateFrom = d;
                    if (this.dateTo && d > this.dateTo) this.dateTo = d;
                    this.openPicker = 'to';
                    this.calView = new Date(this.dateTo || d);
                } else {
                    this.dateTo = d;
                    if (this.dateFrom && d < this.dateFrom) this.dateFrom = d;
                    this.openPicker = null;
                }
            },
            clearDates() { this.dateFrom = null; this.dateTo = null; this.openPicker = null; },
            isSelected(d)  { return d && ((this.dateFrom && this.isSameDay(d, this.dateFrom)) || (this.dateTo && this.isSameDay(d, this.dateTo))); },
            isInRange(d)   { return d && this.dateFrom && this.dateTo && d > this.dateFrom && d < this.dateTo; },
            isBeforeMin(d) { return this.openPicker === 'to' && d && this.dateFrom && d < this.dateFrom; },
            isSameDay(a, b){ return a && b && a.toDateString() === b.toDateString(); },
        };
    }
    </script>
    @endpush

</x-app-layout>
