@props(['options' => [], 'placeholder' => 'Select...', 'name' => '', 'value' => ''])

<div x-data="{
    query: @js($value),
    selected: null,
    show: false,
    options: @js($options),
    get filtered() {
        return this.options.filter(o => (o ?? '').toLowerCase().includes(this.query.toLowerCase()))
    },
    select(value) {
        this.query = value
        this.selected = value
        this.show = false
    }
}" class="relative">
        <input type="text" name="{{ $name }}" x-model="query" @focus="show = true" @click.away="show = false"
                @keydown.escape.window="show = false"
                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all"
                placeholder="{{ $placeholder }}" />

        <ul x-show="show && filtered.length"
                class="absolute z-50 bg-white border border-gray-200 shadow-elevated mt-1 rounded-md w-full max-h-60 overflow-y-auto"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                style="display: none;">
                <template x-for="(option, index) in filtered" :key="index">
                        <li @click="select(option)" class="px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-primary cursor-pointer transition-colors" x-text="option">
                        </li>
                </template>
        </ul>
</div>
