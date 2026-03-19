@props(['campaign'])
@php
    $startDate = old('start_date', $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('Y-m-d') : '');
    $endDate   = old('end_date',   $campaign->end_date   ? \Carbon\Carbon::parse($campaign->end_date)->format('Y-m-d')   : '');
@endphp

<div class="bg-white border border-gray-200 rounded-xl"
    x-data="{
        dateFrom: {{ $startDate ? "new Date('" . e($startDate) . "T12:00:00')" : 'new Date()' }},
        dateTo:   {{ $endDate   ? "new Date('" . e($endDate)   . "T12:00:00')" : 'new Date()' }},
        openPicker: null,
        calView:  {{ $startDate ? "new Date('" . e($startDate) . "T12:00:00')" : 'new Date()' }},

        formatDate(d) {
            if (!d) return 'Select date';
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },
        formatInput(d) {
            if (!d) return '';
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
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
            }
        },
        isSelected(d)   { return d && (this.isSameDay(d, this.dateFrom) || this.isSameDay(d, this.dateTo)); },
        isInRange(d)    { return d && d > this.dateFrom && d < this.dateTo; },
        isBeforeMin(d)  { return this.openPicker === 'to' && d && d < this.dateFrom; },
        isSameDay(a, b) { return a && b && a.toDateString() === b.toDateString(); },
    }"
    @keydown.window.escape="openPicker = null">

    {{-- Hidden inputs for form submission --}}
    <input type="hidden" name="start_date" :value="formatInput(dateFrom)">
    <input type="hidden" name="end_date"   :value="formatInput(dateTo)">

    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <h2 class="text-sm font-semibold text-gray-800">Schedule</h2>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- Start Date --}}
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Start Date</label>
                <div class="relative" @click.outside="if (openPicker === 'from') openPicker = null">
                    <button type="button" @click="togglePicker('from')"
                        class="w-full flex items-center gap-2.5 px-3.5 py-2.5 bg-gray-50 border rounded-lg text-sm text-left transition-all hover:border-gray-300"
                        :class="openPicker === 'from'
                            ? 'border-[#F97316] ring-2 ring-[#F97316]/20 bg-white'
                            : 'border-gray-200'">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span x-text="formatDate(dateFrom)"
                            :class="dateFrom ? 'text-gray-900' : 'text-gray-400'"></span>
                    </button>

                    {{-- Start date calendar --}}
                    <div x-show="openPicker === 'from'" x-cloak
                        class="absolute top-full left-0 mt-1 z-30 bg-white border border-gray-200 rounded-xl shadow-xl p-3 w-64 select-none"
                        style="display:none">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" @click.stop="prevMonth()"
                                class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <span x-text="calMonthYear" class="text-xs font-semibold text-gray-800"></span>
                            <button type="button" @click.stop="nextMonth()"
                                class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
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
                                        @click.stop="selectDate(cell.full)"
                                        :class="{
                                            'bg-[#F97316] text-white font-bold shadow-sm': isSelected(cell.full),
                                            'bg-orange-50 text-[#F97316]': isInRange(cell.full) && !isSelected(cell.full),
                                            'text-gray-700 hover:bg-gray-100': !isSelected(cell.full) && !isInRange(cell.full)
                                        }"
                                        class="w-6 h-6 text-[11px] rounded-full transition-colors cursor-pointer"
                                        x-text="cell.date"></button>
                                    <div x-show="cell.date === null" class="w-6 h-6"></div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-2 pt-2 border-t border-gray-100 text-[10px] text-center text-blue-400">← Pick start date</div>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('start_date')" />
            </div>

            {{-- End Date --}}
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">End Date</label>
                <div class="relative" @click.outside="if (openPicker === 'to') openPicker = null">
                    <button type="button" @click="togglePicker('to')"
                        class="w-full flex items-center gap-2.5 px-3.5 py-2.5 bg-gray-50 border rounded-lg text-sm text-left transition-all hover:border-gray-300"
                        :class="openPicker === 'to'
                            ? 'border-[#F97316] ring-2 ring-[#F97316]/20 bg-white'
                            : 'border-gray-200'">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span x-text="formatDate(dateTo)"
                            :class="dateTo ? 'text-gray-900' : 'text-gray-400'"></span>
                    </button>

                    {{-- End date calendar --}}
                    <div x-show="openPicker === 'to'" x-cloak
                        class="absolute top-full left-0 mt-1 z-30 bg-white border border-gray-200 rounded-xl shadow-xl p-3 w-64 select-none"
                        style="display:none">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" @click.stop="prevMonth()"
                                class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <span x-text="calMonthYear" class="text-xs font-semibold text-gray-800"></span>
                            <button type="button" @click.stop="nextMonth()"
                                class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
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
                        <div class="mt-2 pt-2 border-t border-gray-100 text-[10px] text-center text-[#F97316]">Pick end date →</div>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('end_date')" />
            </div>

        </div>
    </div>
</div>
