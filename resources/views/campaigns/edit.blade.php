<x-app-layout>
        <x-title>
                Edit Campaign
                @isset($campaign->name)
                        {{ $campaign->name }}
                @endisset
        </x-title>
        <x-page-box>
                <form action="{{ route('campaigns.update', $campaign->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Campaign Name</label>
                                <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Client</label>
                                <select name="client_id" required
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                                        <option value="">Select a client</option>
                                        @foreach ($clients as $client)
                                                <option value="{{ $client->id }}"
                                                        @if (old('client_id', $campaign->client_id) == $client->id) selected @endif>
                                                        {{ $client->name }}
                                                </option>
                                        @endforeach
                                </select>
                        </div>

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Expected Impressions</label>
                                <input type="number" name="expected_impressions" min="0"
                                        value="{{ old('expected_impressions', $campaign->expected_impressions) }}"
                                        {{ auth()->user()->is_admin ? '' : 'disabled' }}
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300 {{ auth()->user()->is_admin ? '' : 'bg-gray-100 cursor-not-allowed' }}">
                        </div>

                        @if (auth()->user()->can_view_budget)
                                <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700">Budget</label>
                                        <input type="number" name="budget" min="0"
                                                value="{{ old('budget', $campaign->budget) }}"
                                                {{ auth()->user()->is_admin ? '' : 'disabled' }}
                                                class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300 {{ auth()->user()->is_admin ? '' : 'bg-gray-100 cursor-not-allowed' }}">
                                </div>
                        @endif

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" name="start_date"
                                        value="{{ old('start_date', $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('Y-m-d') : '') }}"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" name="end_date"
                                        value="{{ old('end_date', $campaign->end_date ? \Carbon\Carbon::parse($campaign->end_date)->format('Y-m-d') : '') }}"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        @if(auth()->user()->is_admin)
                        <div x-data="{
                            selectedSizes: ['{{ implode("','", explode(',', $campaign->required_sizes ?? '')) }}'].filter(s => s !== ''),
                            videoSizes: ['1920x1080', '1080x1920'],
                            staticSizes: ['640x820', '640x960', '640x1175', '640x1280', '640x1370', '640x360', '300x250', '1080x1920'],
                            isAdmin: true,
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
                                <h4 @click="toggleGroup(videoSizes)" class="text-sm font-medium text-gray-600 mb-2 select-none cursor-pointer hover:text-blue-600">Video Sizes</h4>
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
                                <h4 @click="toggleGroup(staticSizes)" class="text-sm font-medium text-gray-600 mb-2 select-none cursor-pointer hover:text-blue-600">Static Sizes</h4>
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

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Sizes (comma separated)</label>
                                <input type="text" 
                                    @input="selectedSizes = $event.target.value.split(',').map(s => s.trim()).filter(s => s !== '')"
                                    :value="selectedSizes.join(', ')"
                                    class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300"
                                    placeholder="e.g. 100x100, 200x200">
                            </div>
                        </div>
                        @else
                            <!-- Show read-only Required Sizes for non-admins if desired, or just hide completely? Request said "Hide sizes area". I'll hide it completely. -->
                        @endif

                        <!-- Accordion -->
                        <div x-data="{ active: null }" class="space-y-4 mb-6">
                            <!-- Creatives Section -->
                            <div class="border rounded-md" style="border-color: #E85E26;">
                                <button type="button" @click="active = (active === 'creatives' ? null : 'creatives')"
                                    class="w-full flex justify-between items-center px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left rounded-t-md focus:outline-none">
                                    <span class="font-medium text-gray-700">Creatives ({{ $campaign->creatives->count() }})</span>
                                    <span :class="active === 'creatives' ? 'transform rotate-180' : ''" class="transition-transform duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                                <div x-show="active === 'creatives'" x-collapse class="p-4 bg-white border-t border-gray-200">
                                    <div class="mb-4 flex justify-between items-center">
                                        <div class="flex items-center" x-data="{ optimization: {{ old('creative_optimization', $campaign->creative_optimization) ? '1' : '0' }} }">
                                            <input type="hidden" name="creative_optimization" :value="optimization">
                                            <span class="block text-sm font-medium text-gray-700 mr-3">Creative optimization:</span>
                                            
                                            <div class="flex space-x-2">
                                                <button type="button" 
                                                    @click="optimization = 1"
                                                    :class="optimization == 1 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                    class="px-3 py-1 rounded-full text-sm border transition-colors duration-200">
                                                    CTR
                                                </button>
                                                
                                                <button type="button" 
                                                    @click="optimization = 0"
                                                    :class="optimization == 0 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                    class="px-3 py-1 rounded-full text-sm border transition-colors duration-200">
                                                    Equal Weights
                                                </button>
                                            </div>
                                        </div>

                                        <a href="{{ route('creatives.create', $campaign) }}" class="inline-flex items-center px-3 py-1 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                            + Add Creative
                                        </a>
                                    </div>

                                    @if($campaign->creatives->isEmpty())
                                        <p class="text-gray-500 text-sm">No creatives found.</p>
                                    @else
                                        <div class="space-y-3">
                                            @foreach($campaign->creatives as $creative)
                                                <a href="{{ route('creatives.edit', $creative) }}" class="block flex items-center justify-between p-3 bg-gray-50 rounded border border-gray-100 hover:bg-gray-100 transition duration-150">
                                                    <div>
                                                        <h4 class="font-medium">{{ $creative->name }}</h4>
                                                        <span class="text-xs px-2 py-0.5 rounded-full {{ $creative->status ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                            {{ $creative->status ? 'Active' : 'Paused' }}
                                                        </span>
                                                    </div>
                                                    <div class="flex space-x-2">
                                                        <span class="text-sm text-blue-600">Edit</span>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Audiences Section -->
                            <div class="border rounded-md" style="border-color: #E85E26;">
                                <button type="button" @click="active = (active === 'audiences' ? null : 'audiences')"
                                    class="w-full flex justify-between items-center px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left rounded-t-md focus:outline-none">
                                    <span class="font-medium text-gray-700">Audiences</span>
                                    <span :class="active === 'audiences' ? 'transform rotate-180' : ''" class="transition-transform duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                                <div x-show="active === 'audiences'" x-collapse class="p-4 bg-white border-t border-gray-200">
                                    <p class="text-gray-500">Audiences content goes here.</p>
                                </div>
                            </div>

                            <!-- Targeting Section -->
                            <div class="border rounded-md" style="border-color: #E85E26;">
                                <button type="button" @click="active = (active === 'targeting' ? null : 'targeting')"
                                    class="w-full flex justify-between items-center px-4 py-3 bg-gray-50 hover:bg-gray-100 text-left rounded-t-md focus:outline-none">
                                    <span class="font-medium text-gray-700">Targeting</span>
                                    <span :class="active === 'targeting' ? 'transform rotate-180' : ''" class="transition-transform duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                                <div x-show="active === 'targeting'" x-collapse class="p-4 bg-white border-t border-gray-200">
                                    <p class="text-gray-500">Targeting content goes here.</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                                        Save Changes
                                </button>
                        </div>
                </form>
                </div>
        </x-page-box>
</x-app-layout>
