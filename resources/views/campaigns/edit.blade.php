<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
                            <span class="text-gray-600">Edit Campaign</span>
                        </nav>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                            Edit Campaign
                            @isset($campaign->name)
                                – {{ $campaign->name }}
                            @endisset
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">Update campaign details, targeting, and creative requirements.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                        <button type="submit" form="campaignForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                            Save Changes
                        </button>
                    </div>
                </header>

                <!-- Form Layout -->
                <form id="campaignForm" class="flex flex-col gap-6" action="{{ route('campaigns.update', $campaign->id) }}" method="POST">
                    @csrf
                    @method('PUT')

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
                                <input type="text" id="name" name="name" value="{{ old('name', $campaign->name) }}" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all" required>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="client" class="text-sm font-medium text-gray-700">Client</label>
                                <select id="client" name="client_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all" required>
                                    <option value="">Select a client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @if (old('client_id', $campaign->client_id) == $client->id) selected @endif>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label for="impressions" class="text-sm font-medium text-gray-700">Expected Impressions</label>
                                    <input type="number" id="impressions" name="expected_impressions" min="0" value="{{ old('expected_impressions', $campaign->expected_impressions) }}" {{ auth()->user()->hasPermission('is_admin') ? '' : 'disabled' }} class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all {{ auth()->user()->hasPermission('is_admin') ? '' : 'cursor-not-allowed bg-gray-100' }}">
                                </div>
                                @if(auth()->user()->hasPermission('can_view_budget'))
                                <div class="flex flex-col gap-1.5">
                                    <label for="budget" class="text-sm font-medium text-gray-700">Total Budget</label>
                                    <input type="number" id="budget" name="budget" min="0" value="{{ old('budget', $campaign->budget) }}" {{ auth()->user()->can('editBudget', App\Models\Campaign::class) ? '' : 'disabled' }} class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all {{ auth()->user()->can('editBudget', App\Models\Campaign::class) ? '' : 'cursor-not-allowed bg-gray-100' }}">
                                </div>
                                @endif
                            </div>

                            <!-- Status Toggle -->
                            <div class="mt-2 pt-5 border-t border-gray-100 flex items-center justify-between" x-data="{ state: '{{ old('status', $campaign->status ?? 'active') }}' }">
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
                                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date', $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('Y-m-d') : '') }}" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label for="end_date" class="text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" id="end_date" name="end_date" value="{{ old('end_date', $campaign->end_date ? \Carbon\Carbon::parse($campaign->end_date)->format('Y-m-d') : '') }}" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Manage Configuration (Accordions) -->
                    <div class="flex flex-col" x-data="{ activeAccordion: null }">
                        
                        @if(auth()->user()->hasPermission('is_admin'))
                        <!-- Accordion 1: Required Sizes -->
                        <div class="mb-3 bg-white rounded-md border border-[#F18561] overflow-hidden" 
                             :class="{ 'open': activeAccordion === 'sizes' }"
                             x-data="{
                                selectedSizes: ['{{ implode("','", explode(',', $campaign->required_sizes ?? '')) }}'].filter(s => s !== ''),
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
                            <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                @click="activeAccordion = (activeAccordion === 'sizes' ? null : 'sizes')">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-base font-bold text-gray-900 transition-colors text-left">Required Creative Sizes</span>
                                </div>
                                <svg class="text-gray-500 transition-transform duration-300 transform" 
                                     :class="{ 'rotate-180': activeAccordion === 'sizes' }"
                                     width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            
                            <div x-show="activeAccordion === 'sizes'" x-collapse>
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
                                                :value="selectedSizes.join(', ')"
                                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Accordion 2: Creatives -->
                        <div class="mb-3 bg-white rounded-md border border-[#F18561] overflow-hidden"
                             :class="{ 'open': activeAccordion === 'creatives' }">
                            <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                @click="activeAccordion = (activeAccordion === 'creatives' ? null : 'creatives')">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-base font-bold text-gray-900 transition-colors text-left">
                                        Creatives
                                        <span class="ml-1.5 inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-bold">{{ $campaign->creatives->count() }}</span>
                                    </span>
                                </div>
                                <svg class="text-gray-500 transition-transform duration-300 transform" 
                                     :class="{ 'rotate-180': activeAccordion === 'creatives' }"
                                     width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            <div x-show="activeAccordion === 'creatives'" x-collapse>
                                <div class=" p-4 sm:p-6  border-t border-gray-200">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 mb-6">
                                        <div class="flex flex-col md:flex-row md:items-center gap-3 text-sm font-medium text-gray-900" 
                                            x-data="{ optimization: {{ old('creative_optimization', $campaign->creative_optimization) ? '1' : '0' }} }">
                                            <span>Creative optimization:</span>
                                            <input type="hidden" name="creative_optimization" :value="optimization">

                                            <!-- Alpine Switcher -->
                                            <div class="relative bg-gray-100/80 p-1 rounded-xl flex items-center shadow-inner overflow-hidden cursor-pointer w-[240px]"
                                                @click="optimization = optimization == 1 ? 0 : 1">
                                                <!-- The sliding background block inside the switch -->
                                                <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] bg-[#2563EB] rounded-lg transition-transform duration-300 ease-in-out"
                                                     :class="optimization == 1 ? 'translate-x-[calc(0%+4px)] left-0' : 'translate-x-[calc(100%+4px)] left-0'">
                                                </div>

                                                <!-- Option 1: CTR -->
                                                <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                                    :class="optimization == 1 ? 'text-white' : 'text-gray-600'">
                                                    CTR
                                                </div>

                                                <!-- Option 2: Equal Weights -->
                                                <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                                    :class="optimization == 0 ? 'text-white' : 'text-gray-600'">
                                                    Equal Weights
                                                </div>
                                            </div>
                                        </div>
                                        <a href="{{ route('creatives.create', $campaign) }}" class="inline-flex items-center justify-center self-start md:self-auto px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium shadow-sm transition-colors cursor-pointer">
                                            + ADD CREATIVE
                                        </a>
                                    </div>

                                    <!-- Creatives List -->
                                    @if($campaign->creatives->isEmpty())
                                        <p class="text-gray-500 text-sm">No creatives found.</p>
                                    @else
                                        <div class="space-y-3">
                                            @foreach($campaign->creatives as $creative)
                                            <div onclick="window.location.href='{{ route('creatives.edit', $creative) }}'" class="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/60 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 group cursor-pointer">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-100 to-indigo-50 border border-blue-200/50 flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="flex flex-col min-w-0">
                                                        <span class="text-sm font-bold text-gray-900 truncate">{{ $creative->name }}</span>
                                                        <span class="inline-flex items-center gap-1 mt-0.5 text-xs font-semibold {{ $creative->status ? 'text-green-600' : 'text-gray-500' }}">
                                                            <span class="w-1.5 h-1.5 rounded-full {{ $creative->status ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                                            {{ $creative->status ? 'Active' : 'Paused' }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="text-sm font-semibold text-primary group-hover:text-blue-700 transition-colors focus:outline-none group-hover:underline underline-offset-2 cursor-pointer flex-shrink-0 opacity-70 group-hover:opacity-100">
                                                    Edit
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 3: Audiences -->
                        <div class="mb-3 bg-white rounded-md border border-[#F18561] overflow-hidden"
                             x-data="audienceManager({{ $campaign->id }}, {{ Js::from($connectedAudiences) }})"
                             :class="{ 'open': activeAccordion === 'audiences' }">
                            <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                @click="activeAccordion = (activeAccordion === 'audiences' ? null : 'audiences')">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <span class="text-base font-bold text-gray-900 transition-colors text-left">
                                        Audiences
                                        <span class="ml-1.5 inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-bold" x-text="connected.length"></span>
                                    </span>
                                </div>
                                <svg class="text-gray-500 transition-transform duration-300 transform"
                                     :class="{ 'rotate-180': activeAccordion === 'audiences' }"
                                     width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>

                            <!-- Accordion Body -->
                            <div x-show="activeAccordion === 'audiences'" x-collapse>
                                <div class="p-4 sm:p-6 border-t border-gray-200">
                                    <div class="flex justify-end mb-4">
                                        <button type="button" @click.stop="openModal()"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium shadow-sm transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Add Audiences
                                        </button>
                                    </div>

                                    <!-- Connected pills -->
                                    <div x-show="connected.length > 0" class="flex flex-wrap gap-2">
                                        <template x-for="audience in connected" :key="audience.id">
                                            <div class="inline-flex items-center gap-1.5 pl-3 pr-1 py-1 bg-blue-50 border border-blue-200 rounded-full text-sm font-medium text-blue-800">
                                                <span class="text-xs text-blue-400 font-normal" x-text="audience.sub_category + ' ·'"></span>
                                                <span x-text="audience.name"></span>
                                                <button type="button" @click="removeAudience(audience.id)"
                                                    class="ml-0.5 w-5 h-5 rounded-full flex items-center justify-center hover:bg-blue-200 text-blue-400 hover:text-blue-700 transition-colors flex-shrink-0">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <div x-show="connected.length === 0" class="text-sm text-gray-400 italic">No audiences connected yet.</div>
                                </div>
                            </div>

                            <!-- Audience Selector Modal -->
                            <template x-teleport="body">
                                <div x-show="showModal" x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
                                    style="background: rgba(30,41,59,0.45); backdrop-filter: blur(2px)">
                                    <div @click.away="showModal = false"
                                        class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col overflow-hidden"
                                        style="max-height: 90vh">

                                        <!-- Modal Search Header -->
                                        <div class="border-b border-gray-100 px-5 py-4 flex items-center gap-3">
                                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                                            </svg>
                                            <input type="search" x-model="search" placeholder="Search audiences, categories..."
                                                class="flex-1 text-base text-gray-900 placeholder-gray-400 border-none focus:outline-none focus:ring-0 bg-transparent"
                                                autofocus>
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-500 cursor-pointer select-none flex-shrink-0">
                                                <input type="checkbox" x-model="filterConnected" class="rounded border-gray-300 text-primary focus:ring-primary">
                                                Connected only
                                            </label>
                                        </div>

                                        <!-- Modal Body -->
                                        <div class="flex flex-1 overflow-hidden" style="min-height: 480px; height: 65vh">

                                            <!-- Left: Categories -->
                                            <div class="w-60 border-r border-gray-100 bg-gray-50/60 flex flex-col flex-shrink-0 overflow-y-auto"
                                                style="-webkit-overflow-scrolling: touch">
                                                <div class="px-4 pt-4 pb-2">
                                                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Categories</h3>
                                                </div>
                                                <nav class="flex-1 px-2 pb-4 space-y-0.5">
                                                    <!-- All -->
                                                    <button type="button"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-left text-sm transition-colors"
                                                        :class="activeCategory === null ? 'bg-blue-50 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-100 font-medium'"
                                                        @click="activeCategory = null">
                                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                        </svg>
                                                        All Categories
                                                    </button>
                                                    <template x-for="cat in mainCategories" :key="cat.name">
                                                        <button type="button"
                                                            class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-left text-sm transition-colors"
                                                            :class="activeCategory === cat.name ? 'bg-blue-50 text-primary font-semibold' : 'text-gray-600 hover:bg-gray-100 font-medium'"
                                                            @click="activeCategory = cat.name">
                                                            <!-- Custom icon if set -->
                                                            <template x-if="cat.icon">
                                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="cat.icon"/>
                                                                </svg>
                                                            </template>
                                                            <template x-if="!cat.icon">
                                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a2 2 0 012-2z"/>
                                                                </svg>
                                                            </template>
                                                            <span class="flex-1 truncate" x-text="cat.name"></span>
                                                            <!-- Connected badge -->
                                                            <span x-show="categoryHasSelected(cat.name)"
                                                                class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                                                        </button>
                                                    </template>
                                                </nav>

                                                <!-- Loading state -->
                                                <div x-show="loading" class="flex items-center justify-center py-8 text-gray-400 text-sm gap-2">
                                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Loading...
                                                </div>
                                            </div>

                                            <!-- Right: Audiences -->
                                            <div class="flex-1 flex flex-col bg-white min-w-0">
                                                <!-- Column headers -->
                                                <div class="px-5 py-2.5 border-b border-gray-100 bg-white flex justify-between items-center text-xs font-semibold text-gray-400 uppercase tracking-wider flex-shrink-0">
                                                    <span>Audience Segment</span>
                                                    <span>Est. Size</span>
                                                </div>

                                                <!-- Audience list -->
                                                <div class="flex-1 overflow-y-auto p-3" style="-webkit-overflow-scrolling: touch">

                                                    <!-- Empty state -->
                                                    <div x-show="!loading && filteredAudiences.length === 0"
                                                        class="flex flex-col items-center justify-center h-full text-gray-400 gap-2 py-12">
                                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <span class="text-sm">No audiences found</span>
                                                    </div>

                                                    <!-- Groups -->
                                                    <template x-for="(audiences, subCategory) in groupedBySub" :key="subCategory">
                                                        <div class="mb-5">
                                                            <!-- Sub-category header -->
                                                            <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm px-3 py-2 mb-1 border-b border-gray-100 flex items-center gap-2">
                                                                <span class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></span>
                                                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider" x-text="subCategory"></h3>
                                                            </div>

                                                            <!-- Audience rows -->
                                                            <template x-for="audience in audiences" :key="audience.id">
                                                                <label
                                                                    class="flex items-center justify-between px-3 py-3 rounded-xl transition-colors cursor-pointer group mb-0.5"
                                                                    :class="isSelected(audience.id) ? 'bg-blue-50/70 hover:bg-blue-50' : 'hover:bg-gray-50'">
                                                                    <div class="flex items-center gap-3 min-w-0">
                                                                        <input type="checkbox"
                                                                            :checked="isSelected(audience.id)"
                                                                            @change="toggle(audience.id)"
                                                                            class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer flex-shrink-0">
                                                                        <div class="min-w-0">
                                                                            <p class="text-sm font-semibold text-gray-900 group-hover:text-primary transition-colors truncate" x-text="audience.name"></p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-xs font-mono px-2.5 py-1 rounded-md border flex-shrink-0 ml-3"
                                                                        :class="isSelected(audience.id) ? 'bg-white border-gray-200 text-gray-500' : 'bg-gray-50 border-gray-200 text-gray-400'"
                                                                        x-text="formatUsers(audience.estimated_users)">
                                                                    </div>
                                                                </label>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Footer -->
                                        <div class="border-t border-gray-100 bg-gray-50/60 px-6 py-4 flex items-center justify-between flex-shrink-0">
                                            <div class="text-sm text-gray-500">
                                                <span class="font-bold text-primary bg-blue-100 px-2 py-0.5 rounded-full mr-1.5" x-text="selectedCount"></span>
                                                audiences selected
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <button type="button" @click="showModal = false"
                                                    class="px-5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:border-gray-300 hover:bg-gray-50 rounded-xl transition-all shadow-sm">
                                                    Cancel
                                                </button>
                                                <button type="button" @click="applySync()"
                                                    class="px-5 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl transition-all shadow-sm"
                                                    :disabled="syncing"
                                                    :class="{ 'opacity-60 cursor-wait': syncing }">
                                                    <span x-show="!syncing" x-text="`Apply (${selectedCount} selected)`"></span>
                                                    <span x-show="syncing">Saving...</span>
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Accordion 4: Targeting -->
                        <div class="mb-3 bg-white rounded-md border border-[#F18561] overflow-hidden"
                             x-data="targetingData({
                                 activeTab: 'demographics',
                                 genders: {{ Js::from(old('targeting_rules.genders', $campaign->targeting_rules['genders'] ?? [])) }},
                                 ages: {{ Js::from(old('targeting_rules.ages', $campaign->targeting_rules['ages'] ?? [])) }},
                                 deviceTypes: {{ Js::from(old('targeting_rules.device_types', $campaign->targeting_rules['device_types'] ?? [])) }},
                                 os: {{ Js::from(old('targeting_rules.os', $campaign->targeting_rules['os'] ?? [])) }},
                                 connectionTypes: {{ Js::from(old('targeting_rules.connection_types', $campaign->targeting_rules['connection_types'] ?? [])) }},
                                 environments: {{ Js::from(old('targeting_rules.environments', $campaign->targeting_rules['environments'] ?? [])) }},
                                 days: {{ Js::from(old('targeting_rules.days', $campaign->targeting_rules['days'] ?? [])) }},
                                 locations: {{ Js::from($campaign->locations->map(fn($l) => ['name' => $l->name, 'lat' => $l->lat, 'lng' => $l->lng, 'radius_meters' => $l->radius_meters])->values()) }},
                             })">

                            {{-- Hidden inputs drive the form submission --}}
                            <template x-for="g in genders" :key="'g-' + g">
                                <input type="hidden" name="targeting_rules[genders][]" :value="g">
                            </template>
                            <template x-for="a in ages" :key="'a-' + a">
                                <input type="hidden" name="targeting_rules[ages][]" :value="a">
                            </template>
                            <template x-for="d in deviceTypes" :key="'d-' + d">
                                <input type="hidden" name="targeting_rules[device_types][]" :value="d">
                            </template>
                            <template x-for="o in os" :key="'o-' + o">
                                <input type="hidden" name="targeting_rules[os][]" :value="o">
                            </template>
                            <template x-for="c in connectionTypes" :key="'c-' + c">
                                <input type="hidden" name="targeting_rules[connection_types][]" :value="c">
                            </template>
                            <template x-for="e in environments" :key="'e-' + e">
                                <input type="hidden" name="targeting_rules[environments][]" :value="e">
                            </template>
                            <template x-for="day in days" :key="'day-' + day">
                                <input type="hidden" name="targeting_rules[days][]" :value="day">
                            </template>
                            <template x-for="(loc, i) in locations" :key="'loc-' + i">
                                <span>
                                    <input type="hidden" :name="'locations[' + i + '][name]'" :value="loc.name">
                                    <input type="hidden" :name="'locations[' + i + '][lat]'" :value="loc.lat">
                                    <input type="hidden" :name="'locations[' + i + '][lng]'" :value="loc.lng">
                                    <input type="hidden" :name="'locations[' + i + '][radius_meters]'" :value="loc.radius_meters">
                                </span>
                            </template>

                            <!-- Header -->
                            <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                 @click="activeAccordion = (activeAccordion === 'targeting' ? null : 'targeting')">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                                        </svg>
                                    </div>
                                    <span class="text-base font-bold text-gray-900">Targeting</span>
                                </div>
                                <svg class="text-gray-500 transition-transform duration-300 transform"
                                     :class="{ 'rotate-180': activeAccordion === 'targeting' }"
                                     width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>

                            <!-- Body -->
                            <div x-show="activeAccordion === 'targeting'" x-collapse>
                                <div class="border-t border-gray-200">

                                    <!-- Tab Nav -->
                                    <div class="flex border-b border-gray-100 px-5 bg-white overflow-x-auto">
                                        @foreach([
                                            ['demographics', 'Demographics'],
                                            ['geo',          'Geo &amp; Locations'],
                                            ['devices',      'Devices &amp; Tech'],
                                            ['inventory',    'Inventory'],
                                            ['schedule',     'Schedule'],
                                        ] as [$tabId, $tabLabel])
                                        <button type="button"
                                                @click="activeTab = '{{ $tabId }}'"
                                                :class="activeTab === '{{ $tabId }}'
                                                    ? 'text-primary border-b-2 border-primary'
                                                    : 'text-textMuted hover:text-gray-700'"
                                                class="px-4 py-3 text-sm font-medium transition-colors -mb-px whitespace-nowrap flex-shrink-0">
                                            {!! $tabLabel !!}
                                        </button>
                                        @endforeach
                                    </div>

                                    <!-- Tab Panels -->
                                    <div class="p-5 sm:p-6">

                                        <!-- Demographics -->
                                        <div x-show="activeTab === 'demographics'">

                                            <!-- Gender -->
                                            <div class="mb-6">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Gender</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach([['male', 'Male'], ['female', 'Female'], ['unknown', 'Unknown']] as [$val, $label])
                                                    <button type="button"
                                                            @click="genders.includes('{{ $val }}') ? genders = genders.filter(g => g !== '{{ $val }}') : genders.push('{{ $val }}')"
                                                            :class="genders.includes('{{ $val }}')
                                                                ? 'bg-primary text-white border-primary shadow-sm'
                                                                : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                            class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                        {{ $label }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Age Brackets -->
                                            <div>
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Age Brackets</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach(['18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'Unknown'] as $age)
                                                    <button type="button"
                                                            @click="ages.includes('{{ $age }}') ? ages = ages.filter(a => a !== '{{ $age }}') : ages.push('{{ $age }}')"
                                                            :class="ages.includes('{{ $age }}')
                                                                ? 'bg-primary text-white border-primary shadow-sm'
                                                                : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                            class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                        {{ $age }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Geo & Locations -->
                                        <div x-show="activeTab === 'geo'">

                                            <!-- Map -->
                                            <div id="geo-map" class="w-full rounded-xl border border-border mb-2" style="height:320px;z-index:0"></div>
                                            <p class="text-xs text-textLight mb-4">Click the map to pin a location — name is auto-filled via reverse geocoding.</p>

                                            <!-- Add Location Form -->
                                            <div class="bg-background border border-border rounded-lg p-4 mb-4">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">New Location</h4>
                                                <!-- Row 1: Name (full width) -->
                                                <div class="mb-3">
                                                    <label class="block text-xs font-medium text-textMuted mb-1">Location Name <span class="text-textLight">(optional)</span></label>
                                                    <input type="text" x-model="newLocation.name" placeholder="e.g. Tel Aviv City Center"
                                                           class="w-full rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                </div>
                                                <!-- Row 2: Lat, Lng, Radius -->
                                                <div class="grid grid-cols-3 gap-3 mb-3">
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Latitude <span class="text-danger">*</span></label>
                                                        <input type="number" x-model="newLocation.lat" step="any" placeholder="32.0853"
                                                               class="w-full rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Longitude <span class="text-danger">*</span></label>
                                                        <input type="number" x-model="newLocation.lng" step="any" placeholder="34.7818"
                                                               class="w-full rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Radius (m)</label>
                                                        <input type="number" x-model.number="newLocation.radius_meters" min="100" step="100"
                                                               class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                    </div>
                                                </div>
                                                <button type="button" @click="addLocation()"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-sm font-medium rounded-md hover:bg-primaryHover transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    Add Location
                                                </button>
                                            </div>

                                            <!-- Locations List -->
                                            <div x-show="locations.length > 0" class="space-y-1.5">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-2">Saved Locations</h4>
                                                <template x-for="(loc, i) in locations" :key="'locrow-' + i">
                                                    <div class="flex items-center justify-between gap-3 bg-surface border border-border rounded-lg px-4 py-3 cursor-pointer hover:border-primary hover:bg-primaryLight transition-colors"
                                                         @click="openEdit(i)">
                                                        <div class="flex items-start gap-3 min-w-0">
                                                            <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                                                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="text-sm font-semibold text-textMain truncate" x-text="loc.name || 'Unnamed Location'"></p>
                                                                <p class="text-xs text-textMuted mt-0.5 font-mono">
                                                                    <span x-text="loc.lat"></span>, <span x-text="loc.lng"></span>
                                                                    &nbsp;&middot;&nbsp;
                                                                    <span x-text="Number(loc.radius_meters).toLocaleString()"></span>m radius
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <svg class="w-4 h-4 text-textLight flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                    </div>
                                                </template>
                                            </div>

                                            <div x-show="locations.length === 0" class="text-sm text-textLight italic text-center py-4">
                                                No locations added yet.
                                            </div>

                                            <!-- Edit Location Modal -->
                                            <div x-show="showEditModal" x-cloak
                                                 class="fixed inset-0 flex items-center justify-center"
                                                 style="z-index:9999">
                                                <div class="absolute inset-0 bg-black/40" @click="closeEdit()"></div>
                                                <div class="relative bg-white rounded-xl shadow-elevated p-6 w-full max-w-md mx-4">
                                                    <h3 class="text-base font-bold text-textMain mb-5">Edit Location</h3>
                                                    <!-- Name -->
                                                    <div class="mb-4">
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Location Name</label>
                                                        <input type="text" x-model="editingLocation.name"
                                                               class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                    </div>
                                                    <!-- Lat / Lng -->
                                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                                        <div>
                                                            <label class="block text-xs font-medium text-textMuted mb-1">Latitude</label>
                                                            <input type="number" x-model="editingLocation.lat" step="any"
                                                                   class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-medium text-textMuted mb-1">Longitude</label>
                                                            <input type="number" x-model="editingLocation.lng" step="any"
                                                                   class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                        </div>
                                                    </div>
                                                    <!-- Radius slider -->
                                                    <div class="mb-6">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <label class="text-xs font-medium text-textMuted">Radius</label>
                                                            <span class="text-sm font-semibold text-primary" x-text="Number(editingLocation.radius_meters).toLocaleString() + ' m'"></span>
                                                        </div>
                                                        <input type="range" x-model.number="editingLocation.radius_meters"
                                                               min="100" max="20000" step="100"
                                                               class="w-full accent-primary">
                                                        <div class="flex justify-between text-xs text-textLight mt-1">
                                                            <span>100m</span><span>20km</span>
                                                        </div>
                                                    </div>
                                                    <!-- Actions -->
                                                    <div class="flex gap-2">
                                                        <button type="button" @click="saveEdit()"
                                                                class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primaryHover transition-colors">
                                                            Save
                                                        </button>
                                                        <button type="button" @click="deleteEdit()"
                                                                class="inline-flex justify-center items-center px-4 py-2 bg-dangerLight text-danger text-sm font-medium rounded-md hover:bg-red-200 transition-colors">
                                                            Delete
                                                        </button>
                                                        <button type="button" @click="closeEdit()"
                                                                class="inline-flex justify-center items-center px-4 py-2 bg-white border border-border text-textMuted text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Devices & Tech -->
                                        <div x-show="activeTab === 'devices'">

                                            <!-- Device Types -->
                                            <div class="mb-6">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Device Types</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach([['Mobile', 'Mobile'], ['Tablet', 'Tablet'], ['Desktop', 'Desktop'], ['CTV', 'CTV']] as [$val, $label])
                                                    <button type="button"
                                                            @click="deviceTypes.includes('{{ $val }}') ? deviceTypes = deviceTypes.filter(d => d !== '{{ $val }}') : deviceTypes.push('{{ $val }}')"
                                                            :class="deviceTypes.includes('{{ $val }}')
                                                                ? 'bg-primary text-white border-primary shadow-sm'
                                                                : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                            class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                        {{ $label }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Operating Systems -->
                                            <div class="mb-6">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Operating Systems</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach([['iOS', 'iOS'], ['Android', 'Android'], ['Windows', 'Windows'], ['macOS', 'macOS']] as [$val, $label])
                                                    <button type="button"
                                                            @click="os.includes('{{ $val }}') ? os = os.filter(o => o !== '{{ $val }}') : os.push('{{ $val }}')"
                                                            :class="os.includes('{{ $val }}')
                                                                ? 'bg-primary text-white border-primary shadow-sm'
                                                                : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                            class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                        {{ $label }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Connection Type -->
                                            <div>
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Connection Type</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach([['WiFi', 'Wi-Fi'], ['Cellular', 'Cellular']] as [$val, $label])
                                                    <button type="button"
                                                            @click="connectionTypes.includes('{{ $val }}') ? connectionTypes = connectionTypes.filter(c => c !== '{{ $val }}') : connectionTypes.push('{{ $val }}')"
                                                            :class="connectionTypes.includes('{{ $val }}')
                                                                ? 'bg-primary text-white border-primary shadow-sm'
                                                                : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                            class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                        {{ $label }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Inventory -->
                                        <div x-show="activeTab === 'inventory'">

                                            <!-- Environment -->
                                            <div class="mb-6">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Environment</h4>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach([['In-App', 'In-App'], ['Mobile Web', 'Mobile Web']] as [$val, $label])
                                                    <button type="button"
                                                            @click="environments.includes('{{ $val }}') ? environments = environments.filter(e => e !== '{{ $val }}') : environments.push('{{ $val }}')"
                                                            :class="environments.includes('{{ $val }}')
                                                                ? 'bg-primary text-white border-primary shadow-sm'
                                                                : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                            class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                        {{ $label }}
                                                    </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Allow / Block lists -->
                                            <div>
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Domain / App Lists</h4>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Allowlist</label>
                                                        <textarea name="targeting_rules[allowlist]" rows="5"
                                                                  placeholder="example.com, com.app.id"
                                                                  class="w-full rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary resize-y">{{ old('targeting_rules.allowlist', $campaign->targeting_rules['allowlist'] ?? '') }}</textarea>
                                                        <p class="mt-1 text-xs text-textLight">Enter domains or App IDs separated by commas.</p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Blocklist</label>
                                                        <textarea name="targeting_rules[blocklist]" rows="5"
                                                                  placeholder="example.com, com.app.id"
                                                                  class="w-full rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary resize-y">{{ old('targeting_rules.blocklist', $campaign->targeting_rules['blocklist'] ?? '') }}</textarea>
                                                        <p class="mt-1 text-xs text-textLight">Enter domains or App IDs separated by commas.</p>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Schedule -->
                                        <div x-show="activeTab === 'schedule'">

                                            <!-- Days of the Week (Israel workweek: Sun–Thu / Weekend: Fri–Sat) -->
                                            <div class="mb-6">
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Days of the Week</h4>
                                                <div class="flex flex-wrap gap-3">
                                                    <!-- Workweek group -->
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach([['Sun','Sun'],['Mon','Mon'],['Tue','Tue'],['Wed','Wed'],['Thu','Thu']] as [$val, $label])
                                                        <button type="button"
                                                                @click="days.includes('{{ $val }}') ? days = days.filter(d => d !== '{{ $val }}') : days.push('{{ $val }}')"
                                                                :class="days.includes('{{ $val }}')
                                                                    ? 'bg-primary text-white border-primary shadow-sm'
                                                                    : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                            {{ $label }}
                                                        </button>
                                                        @endforeach
                                                    </div>
                                                    <!-- Divider -->
                                                    <div class="w-px bg-border self-stretch mx-1"></div>
                                                    <!-- Weekend group -->
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach([['Fri','Fri'],['Sat','Sat']] as [$val, $label])
                                                        <button type="button"
                                                                @click="days.includes('{{ $val }}') ? days = days.filter(d => d !== '{{ $val }}') : days.push('{{ $val }}')"
                                                                :class="days.includes('{{ $val }}')
                                                                    ? 'bg-primary text-white border-primary shadow-sm'
                                                                    : 'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                            {{ $label }}
                                                        </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <p class="mt-2 text-xs text-textLight">Workweek: Sun–Thu &nbsp;|&nbsp; Weekend: Fri–Sat</p>
                                            </div>

                                            <!-- Active Hours -->
                                            <div>
                                                <h4 class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">Active Hours</h4>
                                                <div class="grid grid-cols-2 gap-4 max-w-xs">
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">Start Time</label>
                                                        <input type="time" name="targeting_rules[time_start]"
                                                               value="{{ old('targeting_rules.time_start', $campaign->targeting_rules['time_start'] ?? '') }}"
                                                               class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-textMuted mb-1">End Time</label>
                                                        <input type="time" name="targeting_rules[time_end]"
                                                               value="{{ old('targeting_rules.time_end', $campaign->targeting_rules['time_end'] ?? '') }}"
                                                               class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                    </div>
                                                </div>
                                                <p class="mt-2 text-xs text-textLight">Leave blank to serve ads at all hours.</p>
                                            </div>

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
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>

<script>
function audienceManager(campaignId, initialConnected) {
    return {
        campaignId: campaignId,
        connected: initialConnected,
        showModal: false,
        allAudiences: [],
        loading: false,
        syncing: false,
        search: '',
        filterConnected: false,
        activeCategory: null,
        selectedIds: [],

        get mainCategories() {
            const seen = new Map();
            this.allAudiences.forEach(a => {
                if (!seen.has(a.main_category)) {
                    seen.set(a.main_category, { name: a.main_category, icon: a.icon });
                }
            });
            return Array.from(seen.values());
        },

        categoryHasSelected(cat) {
            return this.allAudiences.some(a => a.main_category === cat && this.selectedIds.includes(a.id));
        },

        get filteredAudiences() {
            return this.allAudiences.filter(a => {
                if (this.activeCategory && a.main_category !== this.activeCategory) return false;
                if (this.filterConnected && !this.selectedIds.includes(a.id)) return false;
                if (this.search) {
                    const q = this.search.toLowerCase();
                    return a.name.toLowerCase().includes(q)
                        || a.main_category.toLowerCase().includes(q)
                        || a.sub_category.toLowerCase().includes(q);
                }
                return true;
            });
        },

        get groupedBySub() {
            const groups = {};
            this.filteredAudiences.forEach(a => {
                if (!groups[a.sub_category]) groups[a.sub_category] = [];
                groups[a.sub_category].push(a);
            });
            return groups;
        },

        get selectedCount() {
            return this.selectedIds.length;
        },

        isSelected(id) {
            return this.selectedIds.includes(id);
        },

        toggle(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            } else {
                this.selectedIds.push(id);
            }
        },

        async openModal() {
            this.selectedIds = this.connected.map(a => a.id);
            this.showModal = true;
            if (this.allAudiences.length === 0) {
                this.loading = true;
                try {
                    const res = await fetch(`/campaigns/${this.campaignId}/audiences`);
                    this.allAudiences = await res.json();
                    if (this.mainCategories.length > 0) {
                        this.activeCategory = this.mainCategories[0].name;
                    }
                } finally {
                    this.loading = false;
                }
            }
        },

        async applySync() {
            this.syncing = true;
            try {
                const res = await fetch(`/campaigns/${this.campaignId}/audiences/sync`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ audience_ids: this.selectedIds }),
                });
                const data = await res.json();
                this.connected = data.connected;
                this.showModal = false;
            } finally {
                this.syncing = false;
            }
        },

        async removeAudience(id) {
            this.selectedIds = this.connected.map(a => a.id).filter(i => i !== id);
            const res = await fetch(`/campaigns/${this.campaignId}/audiences/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ audience_ids: this.selectedIds }),
            });
            const data = await res.json();
            this.connected = data.connected;
        },

        formatUsers(n) {
            if (!n) return '—';
            if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M Users';
            if (n >= 1000) return Math.round(n / 1000) + 'K Users';
            return n + ' Users';
        },
    };
}

function targetingData(initial) {
    return {
        ...initial,
        newLocation: { name: '', lat: '', lng: '', radius_meters: 1000 },
        showEditModal: false,
        editingIndex: null,
        editingLocation: { name: '', lat: '', lng: '', radius_meters: 1000 },
        init() {
            this._map = null;
            this._layers = [];
            this._clickMarker = null;
            this.$watch('activeTab', (tab) => {
                if (tab === 'geo') {
                    setTimeout(() => {
                        if (!this._map) this._initMap();
                        else this._map.invalidateSize();
                    }, 50);
                }
            });
            if (this.activeTab === 'geo') setTimeout(() => this._initMap(), 50);
        },
        _initMap() {
            this._map = L.map('geo-map').setView([31.5, 34.8], 8);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '\u00a9 OpenStreetMap contributors',
            }).addTo(this._map);
            this._map.on('click', (e) => {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);
                this.newLocation.lat = lat;
                this.newLocation.lng = lng;
                // Temporary orange dot at click position
                if (this._clickMarker) this._clickMarker.remove();
                this._clickMarker = L.circleMarker([lat, lng], {
                    radius: 7, color: '#F97316', fillColor: '#F97316',
                    fillOpacity: 0.9, weight: 2,
                }).addTo(this._map);
                // Reverse geocode via Nominatim
                fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&accept-language=en')
                    .then(r => r.json())
                    .then(data => {
                        const a = data.address || {};
                        this.newLocation.name = a.neighbourhood || a.suburb || a.city_district
                            || a.town || a.city || a.county
                            || (data.display_name || '').split(',')[0];
                    })
                    .catch(() => {});
            });
            this.drawMarkers();
            setTimeout(() => this._map && this._map.invalidateSize(), 100);
        },
        drawMarkers() {
            if (!this._map) return;
            this._layers.forEach(l => l.remove());
            this._layers = [];
            this.locations.forEach((loc, i) => {
                const lat = parseFloat(loc.lat), lng = parseFloat(loc.lng);
                if (isNaN(lat) || isNaN(lng)) return;
                const m = L.marker([lat, lng]);
                if (loc.name) {
                    m.bindTooltip(loc.name, { permanent: true, direction: 'top', offset: [0, -8] });
                }
                m.on('click', () => this.openEdit(i));
                m.addTo(this._map);
                const c = L.circle([lat, lng], {
                    radius: loc.radius_meters, color: '#2563EB', weight: 2, fillOpacity: 0.1,
                }).addTo(this._map);
                this._layers.push(m, c);
            });
        },
        addLocation() {
            if (this.newLocation.lat !== '' && this.newLocation.lng !== '') {
                this.locations.push({ ...this.newLocation });
                this.newLocation = { name: '', lat: '', lng: '', radius_meters: 1000 };
                if (this._clickMarker) { this._clickMarker.remove(); this._clickMarker = null; }
                this.drawMarkers();
            }
        },
        removeLocation(index) {
            this.locations.splice(index, 1);
            this.drawMarkers();
        },
        openEdit(index) {
            this.editingIndex = index;
            this.editingLocation = { ...this.locations[index] };
            this.showEditModal = true;
        },
        saveEdit() {
            if (this.editingIndex !== null) {
                this.locations[this.editingIndex] = { ...this.editingLocation };
                this.drawMarkers();
            }
            this.closeEdit();
        },
        deleteEdit() {
            if (this.editingIndex !== null) this.removeLocation(this.editingIndex);
            this.closeEdit();
        },
        closeEdit() {
            this.showEditModal = false;
            this.editingIndex = null;
        },
    };
}
</script>

</x-app-layout>
