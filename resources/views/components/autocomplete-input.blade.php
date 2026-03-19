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

    <input type="text" name="{{ $name }}"
           x-model="query"
           @focus="show = true"
           @click.away="show = false"
           @keydown.escape.window="show = false"
           placeholder="{{ $placeholder }}"
           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-800 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors" />

    <ul x-show="show && filtered.length"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
        style="display: none;">
        <template x-for="(option, index) in filtered" :key="index">
            <li @click="select(option)"
                class="px-3 py-2 text-sm text-gray-700 hover:bg-[#F97316]/5 hover:text-[#F97316] cursor-pointer transition-colors"
                x-text="option">
            </li>
        </template>
    </ul>

</div>
