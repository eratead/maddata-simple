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
        class="flex flex-col sm:flex-row flex-wrap items-start sm:items-center gap-3 mb-4 md:mb-6">
        <div class="relative w-full sm:w-auto">
                <select id="date_range" class="appearance-none w-full sm:w-auto bg-white border border-gray-200 text-gray-700 py-1.5 px-3 pr-8 rounded text-sm focus:outline-none focus:border-gray-400"
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
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                        </svg>
                </div>
        </div>

        <div class="flex items-center justify-between sm:justify-start w-full sm:w-auto gap-2">
                <div class="flex-1 sm:flex-none flex justify-between items-center border border-gray-200 rounded px-3 py-1 bg-white focus-within:border-gray-400">
                        <input x-ref="start_date" name="start_date" type="date" id="start_date" x-model="start"
                                value="{{ request('start_date') ?? \Carbon\Carbon::today()->toDateString() }}"
                                class="border-none bg-transparent focus:ring-0 text-sm text-gray-700 p-0"
                                @change="range = ''; $refs.form.submit()">
                </div>
                <div class="flex-1 sm:flex-none flex justify-between items-center border border-gray-200 rounded px-3 py-1 bg-white focus-within:border-gray-400">
                        <input x-ref="end_date" name="end_date" type="date" id="end_date" x-model="end"
                                value="{{ request('end_date') ?? date('Y-m-d') }}"
                                class="border-none bg-transparent focus:ring-0 text-sm text-gray-700 p-0"
                                @change="range = ''; $refs.form.submit()">
                </div>
        </div>
</form>
