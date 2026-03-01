<x-app-layout>
        <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                    <div>
                        <!-- BREADCRUMBS BLOCK (Fixed Height) -->
                        <nav class="flex items-center gap-2 text-sm font-medium h-6 mb-2">
                            <a href="{{ route('campaigns.index') }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">Campaigns</a>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                            <span class="text-gray-600">New Campaign</span>
                        </nav>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                            Create New Campaign
                            @isset($clientName)
                                for {{ $clientName }}
                            @endisset
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">Set up a new campaign with target metrics and required creatives.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                        <button type="submit" form="campaignForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                            Create Campaign
                        </button>
                    </div>
                </header>

                <!-- Form Layout -->
                <form id="campaignForm" class="flex flex-col gap-6" action="{{ route('campaigns.store') }}" method="POST">
                    @csrf

                    <!-- Card 1: Core Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                        <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3 bg-white">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-primary flex items-center justify-center">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-base font-semibold text-gray-900">Campaign Details</h2>
                        </div>
                        <div class=" p-4 sm:p-6  flex flex-col gap-5">
                            <div class="flex flex-col gap-1.5">
                                <label for="name" class="text-sm font-medium text-gray-700">Campaign Name</label>
                                <input type="text" id="name" name="name" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all" required>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="client" class="text-sm font-medium text-gray-700">Client</label>
                                <select id="client" name="client_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all" required>
                                    <option value="">Select a client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label for="impressions" class="text-sm font-medium text-gray-700">Expected Impressions</label>
                                    <input type="number" id="impressions" name="expected_impressions" min="0" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                </div>
                                @if(auth()->user()->hasPermission('can_view_budget'))
                                <div class="flex flex-col gap-1.5">
                                    <label for="budget" class="text-sm font-medium text-gray-700">Total Budget</label>
                                    <input type="number" id="budget" name="budget" min="0" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                </div>
                                @endif
                            </div>

                            <!-- Status Toggle -->
                            <div class="mt-2 pt-5 border-t border-gray-100 flex items-center justify-between" x-data="{ state: '{{ old('status', 'active') }}' }">
                                <span class="text-sm font-medium text-gray-700">Campaign Status</span>
                                <input type="hidden" name="status" :value="state">
                                
                                <div class="relative bg-gray-100/80 p-1 rounded-xl flex items-center shadow-inner overflow-hidden cursor-pointer w-[200px]"
                                    @click="state = state === 'active' ? 'paused' : 'active'">
                                    
                                    <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-lg transition-transform duration-300 ease-in-out"
                                         :class="state === 'active' ? 'translate-x-[calc(0%+4px)] left-0 bg-green-500' : 'translate-x-[calc(100%+4px)] left-0 bg-gray-400'">
                                    </div>

                                    <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                        :class="state === 'active' ? 'text-white' : 'text-gray-600'">
                                        Active
                                    </div>

                                    <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                        :class="state === 'paused' ? 'text-white' : 'text-gray-600'">
                                        Paused
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Schedule -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                        <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3 bg-white">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-primary flex items-center justify-center">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <h2 class="text-base font-semibold text-gray-900">Schedule</h2>
                        </div>
                        <div class=" p-4 sm:p-6 ">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label for="start_date" class="text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label for="end_date" class="text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Manage Configuration (Accordions) -->
                    <div class="flex flex-col" x-data="{
                            selectedSizes: [],
                            videoSizes: ['1920x1080', '1080x1920'],
                            staticSizes: ['640x820', '640x960', '640x1175', '640x1280', '640x1370', '640x360', '300x250', '1080x1920'],
                            accordionOpen: false,
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
                        
                        <!-- Accordion 1: Required Sizes -->
                        <div class="mb-3 bg-white rounded-md border border-[#F18561] overflow-hidden" 
                             :class="{ 'open': accordionOpen }">
                            <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                @click="accordionOpen = !accordionOpen">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-base font-bold text-gray-900 transition-colors text-left">Required Creative Sizes</span>
                                </div>
                                <svg class="text-gray-500 transition-transform duration-300 transform" 
                                     :class="{ 'rotate-180': accordionOpen }"
                                     width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            
                            <div x-show="accordionOpen" x-collapse>
                                <div class=" p-4 sm:p-6  border-t border-gray-200">
                                    <div class="flex flex-col gap-4">
                                        <div class="flex flex-col gap-2">
                                            <h4 @click="toggleGroup(videoSizes)" class="text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-pointer hover:text-primary transition-colors select-none">Video Sizes</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="size in videoSizes">
                                                    <div @click="toggleSize(size)"
                                                        :class="selectedSizes.includes(size) ? 'bg-primary text-white border-primary shadow-[0_2px_4px_rgba(79,70,229,0.2)]' : 'bg-white text-gray-500 border-gray-200 hover:border-primary hover:text-primary hover:bg-indigo-50'"
                                                        class="px-3.5 py-1.5 text-xs font-medium rounded-full cursor-pointer transition-colors select-none border"
                                                        x-text="size"></div>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-2 mt-2">
                                            <h4 @click="toggleGroup(staticSizes)" class="text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-pointer hover:text-primary transition-colors select-none">Static Sizes</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="size in staticSizes">
                                                    <div @click="toggleSize(size)"
                                                        :class="selectedSizes.includes(size) ? 'bg-primary text-white border-primary shadow-[0_2px_4px_rgba(79,70,229,0.2)]' : 'bg-white text-gray-500 border-gray-200 hover:border-primary hover:text-primary hover:bg-indigo-50'"
                                                        class="px-3.5 py-1.5 text-xs font-medium rounded-full cursor-pointer transition-colors select-none border"
                                                        x-text="size"></div>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-1.5 mt-3">
                                            <label for="custom_sizes" class="text-[0.85rem] font-medium text-gray-700">Additional Sizes (comma separated)</label>
                                            <input type="text" id="custom_sizes" placeholder="e.g. 728x90, 160x600"
                                                @input="selectedSizes = $event.target.value.split(',').map(s => s.trim()).filter(s => s !== '')"
                                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-3 mt-4 mb-8 pt-6 border-t border-gray-100">
                        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                        <button type="submit" form="campaignForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                            Create Campaign
                        </button>
                    </div>
                </form>
            </div>
        </main>
</x-app-layout>
