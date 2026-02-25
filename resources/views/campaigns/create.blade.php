<x-app-layout>
        <x-title>
                Create New Campaign
                @isset($clientName)
                        for {{ $clientName }}
                @endisset
        </x-title>
        <x-page-box>
                <form method="POST" action="{{ route('campaigns.store') }}" class="space-y-4">
                        @csrf

                        <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" required
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        <div>
                                <label class="block text-sm font-medium text-gray-700">Client</label>
                                <select name="client_id" required
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                                        <option value="">Select a client</option>
                                        @foreach ($clients as $client)
                                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                </select>
                        </div>

                        <div>
                                <label class="block text-sm font-medium text-gray-700">Expected Impressions</label>
                                <input type="number" name="expected_impressions" min="0"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        @if(auth()->user()->hasPermission('can_view_budget'))
                        <div>
                                <label class="block text-sm font-medium text-gray-700">Budget</label>
                                <input type="number" name="budget" min="0"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>
                        @endif

                        <div>
                                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" name="start_date"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" name="end_date"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        <div x-data="{
                            selectedSizes: [],
                            videoSizes: ['1920x1080', '1080x1920'],
                            staticSizes: ['640x820', '640x960', '640x1175', '640x1280', '640x1370', '640x360', '300x250', '1080x1920'],
                            toggleSize(size) {
                                if (this.selectedSizes.includes(size)) {
                                    this.selectedSizes = this.selectedSizes.filter(s => s !== size);
                                } else {
                                    this.selectedSizes.push(size);
                                }
                            },
                            toggleGroup(groupSizes) {
                                const allSelected = groupSizes.every(size => this.selectedSizes.includes(size));
                                if (allSelected) {
                                    this.selectedSizes = this.selectedSizes.filter(s => !groupSizes.includes(s));
                                } else {
                                    groupSizes.forEach(size => {
                                        if (!this.selectedSizes.includes(size)) {
                                            this.selectedSizes.push(size);
                                        }
                                    });
                                }
                            }
                        }">
                            <input type="hidden" name="required_sizes" :value="selectedSizes.join(',')">
                            
                            <label class="block text-sm font-medium text-gray-700 mb-2">Required Creative Sizes</label>
                            
                            <div class="mb-4">
                                <h4 @click="toggleGroup(videoSizes)" class="text-sm font-medium text-gray-600 mb-2 cursor-pointer hover:text-blue-600 select-none">Video Sizes</h4>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="size in videoSizes">
                                        <button type="button" 
                                            @click="toggleSize(size)"
                                            :class="selectedSizes.includes(size) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-1 rounded-full text-sm border transition-colors duration-200"
                                            x-text="size">
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h4 @click="toggleGroup(staticSizes)" class="text-sm font-medium text-gray-600 mb-2 cursor-pointer hover:text-blue-600 select-none">Static Sizes</h4>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="size in staticSizes">
                                        <button type="button" 
                                            @click="toggleSize(size)"
                                            :class="selectedSizes.includes(size) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-1 rounded-full text-sm border transition-colors duration-200"
                                            x-text="size">
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Sizes (comma separated)</label>
                                <input type="text" 
                                    @input="selectedSizes = $event.target.value.split(',').map(s => s.trim()).filter(s => s !== '')"
                                    :value="selectedSizes.join(', ')"
                                    class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300"
                                    placeholder="e.g. 100x100, 200x200">
                            </div>
                        </div>

                        <div class="flex justify-end">
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                        Create
                                </button>
                        </div>
                </form>
        </x-page-box>
</x-app-layout>
