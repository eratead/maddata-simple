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
                class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300"
                placeholder="{{ $placeholder }}" />

        <ul x-show="show && filtered.length"
                class="absolute z-10 bg-white border mt-1 rounded w-full max-h-60 overflow-auto">
                <template x-for="(option, index) in filtered" :key="index">
                        <li @click="select(option)" class="px-3 py-1 hover:bg-gray-200 cursor-pointer" x-text="option">
                        </li>
                </template>
        </ul>
</div>
