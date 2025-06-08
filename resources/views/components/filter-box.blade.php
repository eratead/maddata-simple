@props([
    'data' => [],
    'fields' => [],
])

<div x-data="{
    filter: '',
    get filtered() {
        if (!this.filter.trim()) return {{ json_encode($data) }};
        const keyword = this.filter.toLowerCase();
        return {{ json_encode($data) }}.filter(item => {
            return {{ json_encode($fields) }}.some(field =>
                (item[field] || '').toLowerCase().includes(keyword)
            );
        });
    }
}">
        <input type="text" x-model="filter" placeholder="Filter..."
                class="mb-4 w-full px-4 py-2 border rounded shadow text-sm" />

        <template x-for="item in filtered" :key="item.id">
                <div class="py-2 border-b">
                        <a :href="`/dashboard/${item.id}`" class="text-blue-600 hover:underline" x-text="item.name"></a>
                </div>
        </template>
</div>
