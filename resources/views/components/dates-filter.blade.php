@props(['action', 'firstReportDate'])

<form x-data="{
    range: localStorage.getItem('dateRange') || '',
    start: '{{ request('start_date') ?? '' }}',
    end: '{{ request('end_date') ?? '' }}',
    init() {
        const today = new Date().toISOString().split('T')[0];
        if (!this.start) {
            this.start = '{{ $firstReportDate }}';
            $refs.start_date.value = this.start;
        }
        if (!this.end) {
            this.end = today;
            $refs.end_date.value = this.end;
        }
        this.$watch('range', value => localStorage.setItem('dateRange', value));
    },
    updateDates(range) {
        const today = new Date();

        let start = new Date(today);
        let end = new Date(today);

        switch (range) {
            case 'yesterday':
                start.setDate(today.getDate() - 1);
                end = new Date(start);
                break;
            case 'week_to_date':
                start.setDate(today.getDate() - today.getDay());
                break;
            case 'month_to_date':
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'year_to_date':
                start = new Date(today.getFullYear(), 0, 1);
                break;
            case 'last_7_days':
                start.setDate(today.getDate() - 6);
                break;
            case 'all':
                this.start = '{{ $firstReportDate }}';
                this.end = today.toISOString().split('T')[0];
                $refs.start_date.value = this.start;
                $refs.end_date.value = this.end;
                $refs.form.submit();
                return;
        }

        const startVal = start.toISOString().split('T')[0];
        const endVal = end.toISOString().split('T')[0];
        this.start = startVal;
        this.end = endVal;
        $refs.start_date.value = startVal;
        $refs.end_date.value = endVal;
        $refs.form.submit();
    }
}" x-ref="form" method="GET" action="{{ $action }}"
        class="flex flex-wrap items-end gap-4">
        <div>
                {{-- <label for="date_range" class="block text-sm font-medium text-gray-700">Date Range</label> --}}
                <select id="date_range" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm text-sm"
                        x-model="range"
                        @change="updateDates($event.target.value); $dispatch('daterange-changed', $event.target.value)">
                        <option value="" disabled>Select dates</option>
                        <option value="all">Lifetime</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week_to_date">Week to Date</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="month_to_date">Month to Date</option>
                        <option value="year_to_date">Year to Date</option>
                </select>
        </div>

        <div class="flex gap-4">
                <div>
                        {{-- <label for="start_date" class="block text-sm font-medium text-gray-700">Start
                                Date</label> --}}
                        <input x-ref="start_date" name="start_date" type="date" id="start_date" x-model="start"
                                value="{{ request('start_date') ?? \Carbon\Carbon::today()->toDateString() }}"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm text-sm"
                                @change="range = ''; $refs.form.submit()">
                </div>
                <div>
                        {{-- <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label> --}}
                        <input x-ref="end_date" name="end_date" type="date" id="end_date" x-model="end"
                                value="{{ request('end_date') ?? date('Y-m-d') }}"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm text-sm"
                                @change="range = ''; $refs.form.submit()">
                </div>
        </div>
</form>
