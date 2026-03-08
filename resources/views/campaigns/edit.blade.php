<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<x-app-layout>
        <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen" x-data="campaignAssistant()">
                <div class="max-w-7xl mx-auto">
                        <!-- Page Header -->
                        <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                                <div>
                                        <!-- BREADCRUMBS BLOCK (Fixed Height) -->
                                        <nav class="flex items-center gap-2 text-sm font-medium h-6 mb-2">
                                                <a href="{{ route('campaigns.index') }}"
                                                        class="text-primary hover:text-primary-hover transition-colors cursor-pointer">Campaigns</a>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                        class="text-gray-400" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="m9 18 6-6-6-6" />
                                                </svg>
                                                <span class="text-gray-600">Edit Campaign</span>
                                        </nav>
                                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                                                Edit Campaign
                                                @isset($campaign->name)
                                                        – {{ $campaign->name }}
                                                @endisset
                                        </h1>
                                        <p class="text-gray-500 text-sm mt-1">Update campaign details, targeting, and
                                                creative requirements.</p>
                                </div>
                                <div class="flex items-center gap-3">
                                        @if(auth()->user()->hasPermission('is_admin') || auth()->user()->userRole?->name === 'Third party communicator')
                                        <button type="button" @click="summaryOpen = true"
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">
                                                <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                Summary
                                        </button>
                                        @endif
                                        <button type="button" @click="isOpen = true"
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-violet-200 rounded-md text-sm font-medium text-violet-700 shadow-sm hover:bg-violet-50 hover:border-violet-300 transition-all">
                                                <svg class="w-4 h-4 text-violet-500 flex-shrink-0" viewBox="0 0 24 24"
                                                        fill="currentColor">
                                                        <path
                                                                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                                </svg>
                                                Auto-Fill from Brief
                                        </button>
                                        <a href="{{ route('campaigns.index') }}"
                                                class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                                        <button type="submit" form="campaignForm"
                                                class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                                                Save Changes
                                        </button>
                                </div>
                        </header>

                        <!-- Form Layout -->
                        <form id="campaignForm" class="flex flex-col gap-6"
                                action="{{ route('campaigns.update', $campaign->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <!-- Card 1: Core Details -->
                                <div
                                        class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                                        <div
                                                class="px-6 py-5 border-b border-gray-100 flex items-center gap-3 bg-white">
                                                <div
                                                        class="w-8 h-8 rounded-lg bg-indigo-50 text-primary flex items-center justify-center">
                                                        <svg width="18" height="18" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                                </path>
                                                        </svg>
                                                </div>
                                                <h2 class="text-base font-semibold text-gray-900">Campaign Details</h2>
                                        </div>
                                        <div class=" p-4 sm:p-6  flex flex-col gap-5">
                                                <div class="flex flex-col gap-1.5">
                                                        <label for="name"
                                                                class="text-sm font-medium text-gray-700">Campaign
                                                                Name</label>
                                                        <input type="text" id="name" name="name"
                                                                value="{{ old('name', $campaign->name) }}"
                                                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all"
                                                                required>
                                                </div>

                                                <div class="flex flex-col gap-1.5">
                                                        <label for="client"
                                                                class="text-sm font-medium text-gray-700">Client</label>
                                                        <select id="client" name="client_id"
                                                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all"
                                                                required>
                                                                <option value="">Select a client</option>
                                                                @foreach ($clients as $client)
                                                                        <option value="{{ $client->id }}"
                                                                                @if (old('client_id', $campaign->client_id) == $client->id) selected @endif>
                                                                                {{ $client->name }}</option>
                                                                @endforeach
                                                        </select>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                                        <div class="flex flex-col gap-1.5">
                                                                <label for="impressions"
                                                                        class="text-sm font-medium text-gray-700">Expected
                                                                        Impressions</label>
                                                                <input type="number" id="impressions"
                                                                        name="expected_impressions" min="0"
                                                                        value="{{ old('expected_impressions', $campaign->expected_impressions) }}"
                                                                        {{ auth()->user()->hasPermission('is_admin') ? '' : 'disabled' }}
                                                                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all {{ auth()->user()->hasPermission('is_admin') ? '' : 'cursor-not-allowed bg-gray-100' }}">
                                                        </div>
                                                        @if (auth()->user()->hasPermission('can_view_budget'))
                                                                <div class="flex flex-col gap-1.5">
                                                                        <label for="budget"
                                                                                class="text-sm font-medium text-gray-700">Total
                                                                                Budget</label>
                                                                        <input type="number" id="budget"
                                                                                name="budget" min="0"
                                                                                value="{{ old('budget', $campaign->budget) }}"
                                                                                {{ auth()->user()->can('editBudget', App\Models\Campaign::class) ? '' : 'disabled' }}
                                                                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all {{ auth()->user()->can('editBudget', App\Models\Campaign::class) ? '' : 'cursor-not-allowed bg-gray-100' }}">
                                                                </div>
                                                        @endif
                                                </div>

                                                <!-- Status Toggle -->
                                                <div class="mt-2 pt-5 border-t border-gray-100 flex items-center justify-between"
                                                        x-data="{ state: '{{ old('status', $campaign->status ?? 'active') }}' }">
                                                        <span class="text-sm font-medium text-gray-700">Campaign
                                                                Status</span>
                                                        <input type="hidden" name="status" :value="state">

                                                        <div class="relative bg-gray-100/80 p-1 rounded-xl flex items-center shadow-inner overflow-hidden cursor-pointer w-[200px]"
                                                                @click="state = state === 'active' ? 'paused' : 'active'">

                                                                <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-lg transition-transform duration-300 ease-in-out"
                                                                        :class="state === 'active' ?
                                                                            'translate-x-[calc(0%+4px)] left-0 bg-green-500' :
                                                                            'translate-x-[calc(100%+4px)] left-0 bg-gray-400'">
                                                                </div>

                                                                <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                                                        :class="state === 'active' ? 'text-white' :
                                                                            'text-gray-600'">
                                                                        Active
                                                                </div>

                                                                <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                                                        :class="state === 'paused' ? 'text-white' :
                                                                            'text-gray-600'">
                                                                        Paused
                                                                </div>
                                                        </div>
                                                </div>
                                        </div>
                                </div>

                                <!-- Card 2: Schedule -->
                                <div
                                        class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                                        <div
                                                class="px-6 py-5 border-b border-gray-100 flex items-center gap-3 bg-white">
                                                <div
                                                        class="w-8 h-8 rounded-lg bg-indigo-50 text-primary flex items-center justify-center">
                                                        <svg width="18" height="18" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                                </path>
                                                        </svg>
                                                </div>
                                                <h2 class="text-base font-semibold text-gray-900">Schedule</h2>
                                        </div>
                                        <div class=" p-4 sm:p-6 ">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                                        <div class="flex flex-col gap-1.5">
                                                                <label for="start_date"
                                                                        class="text-sm font-medium text-gray-700">Start
                                                                        Date</label>
                                                                <input type="date" id="start_date"
                                                                        name="start_date"
                                                                        value="{{ old('start_date', $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('Y-m-d') : '') }}"
                                                                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                                        </div>
                                                        <div class="flex flex-col gap-1.5">
                                                                <label for="end_date"
                                                                        class="text-sm font-medium text-gray-700">End
                                                                        Date</label>
                                                                <input type="date" id="end_date" name="end_date"
                                                                        value="{{ old('end_date', $campaign->end_date ? \Carbon\Carbon::parse($campaign->end_date)->format('Y-m-d') : '') }}"
                                                                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                                        </div>
                                                </div>
                                        </div>
                                </div>

                                <!-- Section: Manage Configuration (Accordions) -->
                                <div class="flex flex-col" x-data="{ activeAccordion: null }">

                                        @if (auth()->user()->hasPermission('is_admin'))
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
                                                        <input type="hidden" name="required_sizes"
                                                                :value="selectedSizes.join(',')">
                                                        <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                                                @click="activeAccordion = (activeAccordion === 'sizes' ? null : 'sizes')">
                                                                <div class="flex items-center gap-3">
                                                                        <div
                                                                                class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                                                                <svg class="w-4 h-4 text-primary"
                                                                                        fill="none"
                                                                                        stroke="currentColor"
                                                                                        viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round"
                                                                                                stroke-linejoin="round"
                                                                                                stroke-width="2"
                                                                                                d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z">
                                                                                        </path>
                                                                                </svg>
                                                                        </div>
                                                                        <span
                                                                                class="text-base font-bold text-gray-900 transition-colors text-left">Required
                                                                                Creative Sizes</span>
                                                                </div>
                                                                <svg class="text-gray-500 transition-transform duration-300 transform"
                                                                        :class="{ 'rotate-180': activeAccordion === 'sizes' }"
                                                                        width="20" height="20" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                stroke-width="2" d="M19 9l-7 7-7-7">
                                                                        </path>
                                                                </svg>
                                                        </div>

                                                        <div x-show="activeAccordion === 'sizes'" x-collapse>
                                                                <div class=" p-4 sm:p-6  border-t border-gray-200">
                                                                        <div class="flex flex-col gap-4">
                                                                                <div class="flex flex-col gap-2">
                                                                                        <h4 @click="toggleGroup(videoSizes)"
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-pointer hover:text-primary transition-colors select-none">
                                                                                                Video Sizes</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                <template
                                                                                                        x-for="size in videoSizes">
                                                                                                        <div @click="toggleSize(size)"
                                                                                                                :class="selectedSizes
                                                                                                                    .includes(
                                                                                                                        size
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-[0_2px_4px_rgba(79,70,229,0.2)]' :
                                                                                                                    'bg-white text-gray-500 border-gray-200 hover:border-primary hover:text-primary hover:bg-indigo-50'"
                                                                                                                class="px-3.5 py-1.5 text-xs font-medium rounded-full cursor-pointer transition-colors select-none border"
                                                                                                                x-text="size">
                                                                                                        </div>
                                                                                                </template>
                                                                                        </div>
                                                                                </div>

                                                                                <div class="flex flex-col gap-2 mt-2">
                                                                                        <h4 @click="toggleGroup(staticSizes)"
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-gray-500 cursor-pointer hover:text-primary transition-colors select-none">
                                                                                                Static Sizes</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                <template
                                                                                                        x-for="size in staticSizes">
                                                                                                        <div @click="toggleSize(size)"
                                                                                                                :class="selectedSizes
                                                                                                                    .includes(
                                                                                                                        size
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-[0_2px_4px_rgba(79,70,229,0.2)]' :
                                                                                                                    'bg-white text-gray-500 border-gray-200 hover:border-primary hover:text-primary hover:bg-indigo-50'"
                                                                                                                class="px-3.5 py-1.5 text-xs font-medium rounded-full cursor-pointer transition-colors select-none border"
                                                                                                                x-text="size">
                                                                                                        </div>
                                                                                                </template>
                                                                                        </div>
                                                                                </div>

                                                                                <div
                                                                                        class="flex flex-col gap-1.5 mt-3">
                                                                                        <label for="custom_sizes"
                                                                                                class="text-[0.85rem] font-medium text-gray-700">Additional
                                                                                                Sizes (comma
                                                                                                separated)</label>
                                                                                        <input type="text"
                                                                                                id="custom_sizes"
                                                                                                placeholder="e.g. 728x90, 160x600"
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
                                                                <div
                                                                        class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                                                        <svg class="w-4 h-4 text-purple-600"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                                                </path>
                                                                        </svg>
                                                                </div>
                                                                <span
                                                                        class="text-base font-bold text-gray-900 transition-colors text-left">
                                                                        Creatives
                                                                        <span
                                                                                class="ml-1.5 inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-bold">{{ $campaign->creatives->count() }}</span>
                                                                </span>
                                                        </div>
                                                        <svg class="text-gray-500 transition-transform duration-300 transform"
                                                                :class="{ 'rotate-180': activeAccordion === 'creatives' }"
                                                                width="20" height="20" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                </div>
                                                <div x-show="activeAccordion === 'creatives'" x-collapse>
                                                        <div class=" p-4 sm:p-6  border-t border-gray-200">
                                                                <div
                                                                        class="flex flex-col md:flex-row md:items-center justify-between gap-5 mb-6">
                                                                        <div class="flex flex-col md:flex-row md:items-center gap-3 text-sm font-medium text-gray-900"
                                                                                x-data="{ optimization: {{ old('creative_optimization', $campaign->creative_optimization) ? '1' : '0' }} }">
                                                                                <span>Creative optimization:</span>
                                                                                <input type="hidden"
                                                                                        name="creative_optimization"
                                                                                        :value="optimization">

                                                                                <!-- Alpine Switcher -->
                                                                                <div class="relative bg-gray-100/80 p-1 rounded-xl flex items-center shadow-inner overflow-hidden cursor-pointer w-[240px]"
                                                                                        @click="optimization = optimization == 1 ? 0 : 1">
                                                                                        <!-- The sliding background block inside the switch -->
                                                                                        <div class="absolute top-1 bottom-1 w-[calc(50%-4px)] bg-[#2563EB] rounded-lg transition-transform duration-300 ease-in-out"
                                                                                                :class="optimization == 1 ?
                                                                                                    'translate-x-[calc(0%+4px)] left-0' :
                                                                                                    'translate-x-[calc(100%+4px)] left-0'">
                                                                                        </div>

                                                                                        <!-- Option 1: CTR -->
                                                                                        <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                                                                                :class="optimization == 1 ?
                                                                                                    'text-white' :
                                                                                                    'text-gray-600'">
                                                                                                CTR
                                                                                        </div>

                                                                                        <!-- Option 2: Equal Weights -->
                                                                                        <div class="flex-1 text-center py-1 text-sm font-semibold transition-colors duration-300 relative z-10"
                                                                                                :class="optimization == 0 ?
                                                                                                    'text-white' :
                                                                                                    'text-gray-600'">
                                                                                                Equal Weights
                                                                                        </div>
                                                                                </div>
                                                                        </div>
                                                                        <a href="{{ route('creatives.create', $campaign) }}"
                                                                                class="inline-flex items-center justify-center self-start md:self-auto px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium shadow-sm transition-colors cursor-pointer">
                                                                                + ADD CREATIVE
                                                                        </a>
                                                                </div>

                                                                <!-- Creatives List -->
                                                                @if ($campaign->creatives->isEmpty())
                                                                        <p class="text-gray-500 text-sm">No creatives
                                                                                found.</p>
                                                                @else
                                                                        <div class="space-y-3">
                                                                                @foreach ($campaign->creatives as $creative)
                                                                                        <div onclick="window.location.href='{{ route('creatives.edit', $creative) }}'"
                                                                                                class="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/60 hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 group cursor-pointer">
                                                                                                <div
                                                                                                        class="flex items-center gap-3 min-w-0">
                                                                                                        <div
                                                                                                                class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-100 to-indigo-50 border border-blue-200/50 flex items-center justify-center flex-shrink-0">
                                                                                                                <svg class="w-5 h-5 text-primary"
                                                                                                                        fill="none"
                                                                                                                        stroke="currentColor"
                                                                                                                        viewBox="0 0 24 24">
                                                                                                                        <path stroke-linecap="round"
                                                                                                                                stroke-linejoin="round"
                                                                                                                                stroke-width="2"
                                                                                                                                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
                                                                                                                        </path>
                                                                                                                </svg>
                                                                                                        </div>
                                                                                                        <div
                                                                                                                class="flex flex-col min-w-0">
                                                                                                                <span
                                                                                                                        class="text-sm font-bold text-gray-900 truncate">{{ $creative->name }}</span>
                                                                                                                <span
                                                                                                                        class="inline-flex items-center gap-1 mt-0.5 text-xs font-semibold {{ $creative->status ? 'text-green-600' : 'text-gray-500' }}">
                                                                                                                        <span
                                                                                                                                class="w-1.5 h-1.5 rounded-full {{ $creative->status ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                                                                                                        {{ $creative->status ? 'Active' : 'Paused' }}
                                                                                                                </span>
                                                                                                        </div>
                                                                                                </div>
                                                                                                <div
                                                                                                        class="text-sm font-semibold text-primary group-hover:text-blue-700 transition-colors focus:outline-none group-hover:underline underline-offset-2 cursor-pointer flex-shrink-0 opacity-70 group-hover:opacity-100">
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
                                                                <div
                                                                        class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                                                        <svg class="w-4 h-4 text-green-600"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                                        </svg>
                                                                </div>
                                                                <span
                                                                        class="text-base font-bold text-gray-900 transition-colors text-left">
                                                                        Audiences
                                                                        <span class="ml-1.5 inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-bold"
                                                                                x-text="connected.length"></span>
                                                                </span>
                                                        </div>
                                                        <svg class="text-gray-500 transition-transform duration-300 transform"
                                                                :class="{ 'rotate-180': activeAccordion === 'audiences' }"
                                                                width="20" height="20" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                </div>

                                                <!-- Accordion Body -->
                                                <div x-show="activeAccordion === 'audiences'" x-collapse>
                                                        <div class="p-4 sm:p-6 border-t border-gray-200">
                                                                <div class="flex justify-end mb-4">
                                                                        <button type="button"
                                                                                @click.stop="openModal()"
                                                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium shadow-sm transition-colors">
                                                                                <svg class="w-3.5 h-3.5"
                                                                                        fill="none"
                                                                                        stroke="currentColor"
                                                                                        viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round"
                                                                                                stroke-linejoin="round"
                                                                                                stroke-width="2"
                                                                                                d="M12 4v16m8-8H4" />
                                                                                </svg>
                                                                                Add Audiences
                                                                        </button>
                                                                </div>

                                                                <!-- Connected pills -->
                                                                <div x-show="connected.length > 0"
                                                                        class="flex flex-wrap gap-2">
                                                                        <template x-for="audience in connected"
                                                                                :key="audience.id">
                                                                                <div
                                                                                        class="inline-flex items-center gap-1.5 pl-3 pr-1 py-1 bg-blue-50 border border-blue-200 rounded-full text-sm font-medium text-blue-800">
                                                                                        <span class="text-xs text-blue-400 font-normal"
                                                                                                x-text="audience.sub_category + ' ·'"></span>
                                                                                        <span
                                                                                                x-text="audience.name"></span>
                                                                                        <button type="button"
                                                                                                @click="removeAudience(audience.id)"
                                                                                                class="ml-0.5 w-5 h-5 rounded-full flex items-center justify-center hover:bg-blue-200 text-blue-400 hover:text-blue-700 transition-colors flex-shrink-0">
                                                                                                <svg class="w-3 h-3"
                                                                                                        fill="none"
                                                                                                        stroke="currentColor"
                                                                                                        viewBox="0 0 24 24">
                                                                                                        <path stroke-linecap="round"
                                                                                                                stroke-linejoin="round"
                                                                                                                stroke-width="2.5"
                                                                                                                d="M6 18L18 6M6 6l12 12" />
                                                                                                </svg>
                                                                                        </button>
                                                                                </div>
                                                                        </template>
                                                                </div>
                                                                <div x-show="connected.length === 0"
                                                                        class="text-sm text-gray-400 italic">No
                                                                        audiences connected yet.</div>
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
                                                                        <div
                                                                                class="border-b border-gray-100 px-5 py-4 flex items-center gap-3">
                                                                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0"
                                                                                        fill="none"
                                                                                        stroke="currentColor"
                                                                                        viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round"
                                                                                                stroke-linejoin="round"
                                                                                                stroke-width="2"
                                                                                                d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                                                                                </svg>
                                                                                <input type="search" x-model="search"
                                                                                        placeholder="Search audiences, categories..."
                                                                                        class="flex-1 text-base text-gray-900 placeholder-gray-400 border-none focus:outline-none focus:ring-0 bg-transparent"
                                                                                        autofocus>
                                                                                <label
                                                                                        class="inline-flex items-center gap-2 text-sm text-gray-500 cursor-pointer select-none flex-shrink-0">
                                                                                        <input type="checkbox"
                                                                                                x-model="filterConnected"
                                                                                                class="rounded border-gray-300 text-primary focus:ring-primary">
                                                                                        Connected only
                                                                                </label>
                                                                        </div>

                                                                        <!-- Modal Body -->
                                                                        <div class="flex flex-1 overflow-hidden"
                                                                                style="min-height: 480px; height: 65vh">

                                                                                <!-- Left: Categories -->
                                                                                <div class="w-60 border-r border-gray-100 bg-gray-50/60 flex flex-col flex-shrink-0 overflow-y-auto"
                                                                                        style="-webkit-overflow-scrolling: touch">
                                                                                        <div class="px-4 pt-4 pb-2">
                                                                                                <h3
                                                                                                        class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                                                                                        Categories</h3>
                                                                                        </div>
                                                                                        <nav
                                                                                                class="flex-1 px-2 pb-4 space-y-0.5">
                                                                                                <!-- All -->
                                                                                                <button type="button"
                                                                                                        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-left text-sm transition-colors"
                                                                                                        :class="activeCategory
                                                                                                            === null ?
                                                                                                            'bg-blue-50 text-primary font-semibold' :
                                                                                                            'text-gray-600 hover:bg-gray-100 font-medium'"
                                                                                                        @click="activeCategory = null">
                                                                                                        <svg class="w-4 h-4 flex-shrink-0"
                                                                                                                fill="none"
                                                                                                                stroke="currentColor"
                                                                                                                viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round"
                                                                                                                        stroke-linejoin="round"
                                                                                                                        stroke-width="2"
                                                                                                                        d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                                                                                        </svg>
                                                                                                        All Categories
                                                                                                </button>
                                                                                                <template
                                                                                                        x-for="cat in mainCategories"
                                                                                                        :key="cat.name">
                                                                                                        <button type="button"
                                                                                                                class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-left text-sm transition-colors"
                                                                                                                :class="activeCategory
                                                                                                                    ===
                                                                                                                    cat
                                                                                                                    .name ?
                                                                                                                    'bg-blue-50 text-primary font-semibold' :
                                                                                                                    'text-gray-600 hover:bg-gray-100 font-medium'"
                                                                                                                @click="activeCategory = cat.name">
                                                                                                                <!-- Custom icon if set -->
                                                                                                                <template
                                                                                                                        x-if="cat.icon">
                                                                                                                        <svg class="w-4 h-4 flex-shrink-0"
                                                                                                                                fill="none"
                                                                                                                                stroke="currentColor"
                                                                                                                                viewBox="0 0 24 24">
                                                                                                                                <path stroke-linecap="round"
                                                                                                                                        stroke-linejoin="round"
                                                                                                                                        stroke-width="2"
                                                                                                                                        :d="cat.icon" />
                                                                                                                        </svg>
                                                                                                                </template>
                                                                                                                <template
                                                                                                                        x-if="!cat.icon">
                                                                                                                        <svg class="w-4 h-4 flex-shrink-0"
                                                                                                                                fill="none"
                                                                                                                                stroke="currentColor"
                                                                                                                                viewBox="0 0 24 24">
                                                                                                                                <path stroke-linecap="round"
                                                                                                                                        stroke-linejoin="round"
                                                                                                                                        stroke-width="2"
                                                                                                                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a2 2 0 012-2z" />
                                                                                                                        </svg>
                                                                                                                </template>
                                                                                                                <span class="flex-1 truncate"
                                                                                                                        x-text="cat.name"></span>
                                                                                                                <!-- Connected badge -->
                                                                                                                <span x-show="categoryHasSelected(cat.name)"
                                                                                                                        class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                                                                                                        </button>
                                                                                                </template>
                                                                                        </nav>

                                                                                        <!-- Loading state -->
                                                                                        <div x-show="loading"
                                                                                                class="flex items-center justify-center py-8 text-gray-400 text-sm gap-2">
                                                                                                <svg class="w-4 h-4 animate-spin"
                                                                                                        fill="none"
                                                                                                        viewBox="0 0 24 24">
                                                                                                        <circle class="opacity-25"
                                                                                                                cx="12"
                                                                                                                cy="12"
                                                                                                                r="10"
                                                                                                                stroke="currentColor"
                                                                                                                stroke-width="4">
                                                                                                        </circle>
                                                                                                        <path class="opacity-75"
                                                                                                                fill="currentColor"
                                                                                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                                                                                                        </path>
                                                                                                </svg>
                                                                                                Loading...
                                                                                        </div>
                                                                                </div>

                                                                                <!-- Right: Audiences -->
                                                                                <div
                                                                                        class="flex-1 flex flex-col bg-white min-w-0">
                                                                                        <!-- Column headers -->
                                                                                        <div
                                                                                                class="px-5 py-2.5 border-b border-gray-100 bg-white flex justify-between items-center text-xs font-semibold text-gray-400 uppercase tracking-wider flex-shrink-0">
                                                                                                <span>Audience
                                                                                                        Segment</span>
                                                                                                <span>Est. Size</span>
                                                                                        </div>

                                                                                        <!-- Audience list -->
                                                                                        <div class="flex-1 overflow-y-auto p-3"
                                                                                                style="-webkit-overflow-scrolling: touch">

                                                                                                <!-- Empty state -->
                                                                                                <div x-show="!loading && filteredAudiences.length === 0"
                                                                                                        class="flex flex-col items-center justify-center h-full text-gray-400 gap-2 py-12">
                                                                                                        <svg class="w-8 h-8"
                                                                                                                fill="none"
                                                                                                                stroke="currentColor"
                                                                                                                viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round"
                                                                                                                        stroke-linejoin="round"
                                                                                                                        stroke-width="1.5"
                                                                                                                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                                        </svg>
                                                                                                        <span
                                                                                                                class="text-sm">No
                                                                                                                audiences
                                                                                                                found</span>
                                                                                                </div>

                                                                                                <!-- Groups -->
                                                                                                <template
                                                                                                        x-for="(audiences, subCategory) in groupedBySub"
                                                                                                        :key="subCategory">
                                                                                                        <div
                                                                                                                class="mb-5">
                                                                                                                <!-- Sub-category header -->
                                                                                                                <div
                                                                                                                        class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm px-3 py-2 mb-1 border-b border-gray-100 flex items-center gap-2">
                                                                                                                        <span
                                                                                                                                class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></span>
                                                                                                                        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider"
                                                                                                                                x-text="subCategory">
                                                                                                                        </h3>
                                                                                                                </div>

                                                                                                                <!-- Audience rows -->
                                                                                                                <template
                                                                                                                        x-for="audience in audiences"
                                                                                                                        :key="audience.id">
                                                                                                                        <label class="flex items-center justify-between px-3 py-3 rounded-xl transition-colors cursor-pointer group mb-0.5"
                                                                                                                                :class="isSelected
                                                                                                                                    (audience
                                                                                                                                        .id
                                                                                                                                        ) ?
                                                                                                                                    'bg-blue-50/70 hover:bg-blue-50' :
                                                                                                                                    'hover:bg-gray-50'">
                                                                                                                                <div
                                                                                                                                        class="flex items-center gap-3 min-w-0">
                                                                                                                                        <input type="checkbox"
                                                                                                                                                :checked="isSelected
                                                                                                                                                    (audience
                                                                                                                                                        .id
                                                                                                                                                        )"
                                                                                                                                                @change="toggle(audience.id)"
                                                                                                                                                class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer flex-shrink-0">
                                                                                                                                        <div
                                                                                                                                                class="min-w-0">
                                                                                                                                                <p class="text-sm font-semibold text-gray-900 group-hover:text-primary transition-colors truncate"
                                                                                                                                                        x-text="audience.name">
                                                                                                                                                </p>
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                                <div class="text-xs font-mono px-2.5 py-1 rounded-md border flex-shrink-0 ml-3"
                                                                                                                                        :class="isSelected
                                                                                                                                            (audience
                                                                                                                                                .id
                                                                                                                                                ) ?
                                                                                                                                            'bg-white border-gray-200 text-gray-500' :
                                                                                                                                            'bg-gray-50 border-gray-200 text-gray-400'"
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
                                                                        <div
                                                                                class="border-t border-gray-100 bg-gray-50/60 px-6 py-4 flex items-center justify-between flex-shrink-0">
                                                                                <div class="text-sm text-gray-500">
                                                                                        <span class="font-bold text-primary bg-blue-100 px-2 py-0.5 rounded-full mr-1.5"
                                                                                                x-text="selectedCount"></span>
                                                                                        audiences selected
                                                                                </div>
                                                                                <div class="flex items-center gap-3">
                                                                                        <button type="button"
                                                                                                @click="showModal = false"
                                                                                                class="px-5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:border-gray-300 hover:bg-gray-50 rounded-xl transition-all shadow-sm">
                                                                                                Cancel
                                                                                        </button>
                                                                                        <button type="button"
                                                                                                @click="applySync()"
                                                                                                class="px-5 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl transition-all shadow-sm"
                                                                                                :disabled="syncing"
                                                                                                :class="{ 'opacity-60 cursor-wait': syncing }">
                                                                                                <span x-show="!syncing"
                                                                                                        x-text="`Apply (${selectedCount} selected)`"></span>
                                                                                                <span
                                                                                                        x-show="syncing">Saving...</span>
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
                                                    incomes: {{ Js::from(old('targeting_rules.incomes', $campaign->targeting_rules['incomes'] ?? ['0-195K', '195-220K', '220-245K', '245K+'])) }},
                                                    deviceTypes: {{ Js::from(old('targeting_rules.device_types', $campaign->targeting_rules['device_types'] ?? ['Mobile', 'Tablet'])) }},
                                                    os: {{ Js::from(old('targeting_rules.os', $campaign->targeting_rules['os'] ?? ['iOS', 'Android', 'Windows', 'macOS'])) }},
                                                    connectionTypes: ['WiFi', 'Cellular'],
                                                    environments: {{ Js::from(old('targeting_rules.environments', $campaign->targeting_rules['environments'] ?? ['In-App', 'Mobile Web'])) }},
                                                    days: {{ Js::from(old('targeting_rules.days', $campaign->targeting_rules['days'] ?? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])) }},
                                                    locations: {{ Js::from($campaign->locations->map(fn($l) => ['name' => $l->name, 'lat' => $l->lat, 'lng' => $l->lng, 'radius_meters' => $l->radius_meters])->values()) }},
                                                    countries: {{ Js::from(old('targeting_rules.countries', $campaign->targeting_rules['countries'] ?? ['Israel'])) }},
                                                    regions: {{ Js::from(old('targeting_rules.regions', $campaign->targeting_rules['regions'] ?? [])) }},
                                                    cities: {{ Js::from(old('targeting_rules.cities', is_string($campaign->targeting_rules['cities'] ?? null) ? json_decode($campaign->targeting_rules['cities'] ?? '[]', true) : $campaign->targeting_rules['cities'] ?? [])) }},
                                                })">

                                                {{-- Hidden inputs drive the form submission --}}
                                                <template x-for="g in genders" :key="'g-' + g">
                                                        <input type="hidden" name="targeting_rules[genders][]"
                                                                :value="g">
                                                </template>
                                                <template x-for="a in ages" :key="'a-' + a">
                                                        <input type="hidden" name="targeting_rules[ages][]"
                                                                :value="a">
                                                </template>
                                                <template x-for="inc in incomes" :key="'inc-' + inc">
                                                        <input type="hidden" name="targeting_rules[incomes][]"
                                                                :value="inc">
                                                </template>
                                                <template x-for="d in deviceTypes" :key="'d-' + d">
                                                        <input type="hidden" name="targeting_rules[device_types][]"
                                                                :value="d">
                                                </template>
                                                <template x-for="o in os" :key="'o-' + o">
                                                        <input type="hidden" name="targeting_rules[os][]"
                                                                :value="o">
                                                </template>
                                                <template x-for="c in connectionTypes" :key="'c-' + c">
                                                        <input type="hidden"
                                                                name="targeting_rules[connection_types][]"
                                                                :value="c">
                                                </template>
                                                <template x-for="e in environments" :key="'e-' + e">
                                                        <input type="hidden" name="targeting_rules[environments][]"
                                                                :value="e">
                                                </template>
                                                <template x-for="day in days" :key="'day-' + day">
                                                        <input type="hidden" name="targeting_rules[days][]"
                                                                :value="day">
                                                </template>
                                                <template x-for="(loc, i) in locations" :key="'loc-' + i">
                                                        <span>
                                                                <input type="hidden"
                                                                        :name="'locations[' + i + '][name]'"
                                                                        :value="loc.name">
                                                                <input type="hidden"
                                                                        :name="'locations[' + i + '][lat]'"
                                                                        :value="loc.lat">
                                                                <input type="hidden"
                                                                        :name="'locations[' + i + '][lng]'"
                                                                        :value="loc.lng">
                                                                <input type="hidden"
                                                                        :name="'locations[' + i + '][radius_meters]'"
                                                                        :value="loc.radius_meters">
                                                        </span>
                                                </template>
                                                <template x-for="co in countries" :key="'co-' + co">
                                                        <input type="hidden" name="targeting_rules[countries][]"
                                                                :value="co">
                                                </template>
                                                <template x-for="r in regions" :key="'r-' + r">
                                                        <input type="hidden" name="targeting_rules[regions][]"
                                                                :value="r">
                                                </template>
                                                <input type="hidden" name="targeting_rules[cities]"
                                                        :value="JSON.stringify(cities)">

                                                <!-- Header -->
                                                <div class="p-4 px-5 flex justify-between items-center cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors select-none"
                                                        @click="activeAccordion = (activeAccordion === 'targeting' ? null : 'targeting')">
                                                        <div class="flex items-center gap-3">
                                                                <div
                                                                        class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                                                                        <svg class="w-4 h-4 text-purple-600"
                                                                                fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                        stroke-linejoin="round"
                                                                                        stroke-width="2"
                                                                                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                                                                        </svg>
                                                                </div>
                                                                <span
                                                                        class="text-base font-bold text-gray-900">Targeting</span>
                                                        </div>
                                                        <svg class="text-gray-500 transition-transform duration-300 transform"
                                                                :class="{ 'rotate-180': activeAccordion === 'targeting' }"
                                                                width="20" height="20" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                </div>

                                                <!-- Body -->
                                                <div x-show="activeAccordion === 'targeting'" x-collapse>
                                                        <div class="border-t border-gray-200">

                                                                <!-- Targeting Summary -->
                                                                <div
                                                                        class="px-5 pt-4 pb-3 border-b border-gray-100 bg-gray-50/50">
                                                                        <p
                                                                                class="text-[10px] font-semibold uppercase tracking-widest text-textLight mb-2.5">
                                                                                Targeting Summary</p>
                                                                        <div class="space-y-1.5 text-xs">

                                                                                <div
                                                                                        class="flex gap-2 items-baseline flex-wrap">
                                                                                        <span
                                                                                                class="font-semibold text-textMuted shrink-0 w-28">Demographics</span>
                                                                                        <span
                                                                                                class="text-textLight">Gender:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(genders, 2)"></span>
                                                                                        <span
                                                                                                class="text-gray-300 select-none">·</span>
                                                                                        <span
                                                                                                class="text-textLight">Age:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(ages, 7)"></span>
                                                                                        <span
                                                                                                class="text-gray-300 select-none">·</span>
                                                                                        <span
                                                                                                class="text-textLight">Income:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(incomes, 4)"></span>
                                                                                </div>

                                                                                <div
                                                                                        class="flex gap-2 items-baseline flex-wrap">
                                                                                        <span
                                                                                                class="font-semibold text-textMuted shrink-0 w-28">Geo</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="summaryGeo()"></span>
                                                                                </div>

                                                                                <div
                                                                                        class="flex gap-2 items-baseline flex-wrap">
                                                                                        <span
                                                                                                class="font-semibold text-textMuted shrink-0 w-28">Devices
                                                                                                &amp; Tech</span>
                                                                                        <span
                                                                                                class="text-textLight">Devices:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(deviceTypes, 4)"></span>
                                                                                        <span
                                                                                                class="text-gray-300 select-none">·</span>
                                                                                        <span
                                                                                                class="text-textLight">OS:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(os, 4)"></span>
                                                                                        <span
                                                                                                class="text-gray-300 select-none">·</span>
                                                                                        <span
                                                                                                class="text-textLight">Connection:</span>
                                                                                        <span
                                                                                                class="text-textMain">All</span>
                                                                                </div>

                                                                                <div
                                                                                        class="flex gap-2 items-baseline flex-wrap">
                                                                                        <span
                                                                                                class="font-semibold text-textMuted shrink-0 w-28">Inventory</span>
                                                                                        <span
                                                                                                class="text-textLight">Environment:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(environments, 2)"></span>
                                                                                </div>

                                                                                <div
                                                                                        class="flex gap-2 items-baseline flex-wrap">
                                                                                        <span
                                                                                                class="font-semibold text-textMuted shrink-0 w-28">Schedule</span>
                                                                                        <span
                                                                                                class="text-textLight">Days:</span>
                                                                                        <span class="text-textMain"
                                                                                                x-text="_fmt(days, 7)"></span>
                                                                                </div>

                                                                        </div>
                                                                </div>

                                                                <!-- Tab Nav -->
                                                                <div
                                                                        class="flex border-b border-gray-100 px-5 bg-white overflow-x-hidden">
                                                                        @foreach ([['demographics', 'Demographics'], ['geo', 'Geo &amp; Locations'], ['devices', 'Devices &amp; Tech'], ['inventory', 'Inventory'], ['schedule', 'Schedule']] as [$tabId, $tabLabel])
                                                                                <button type="button"
                                                                                        @click="activeTab = '{{ $tabId }}'"
                                                                                        :class="activeTab === '{{ $tabId }}'
                                                                                            ?
                                                                                            'text-primary border-b-2 border-primary' :
                                                                                            'text-textMuted hover:text-gray-700'"
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
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Gender</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                @foreach ([['male', 'Male'], ['female', 'Female'], ['unknown', 'Unknown']] as [$val, $label])
                                                                                                        <button type="button"
                                                                                                                @click="genders.includes('{{ $val }}') ? genders = genders.filter(g => g !== '{{ $val }}') : genders.push('{{ $val }}')"
                                                                                                                :class="genders
                                                                                                                    .includes(
                                                                                                                        '{{ $val }}'
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-sm' :
                                                                                                                    'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                {{ $label }}
                                                                                                        </button>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                                <!-- Age Brackets -->
                                                                                <div class="mb-6">
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Age Brackets</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                @foreach (['13-17', '18-24', '25-34', '35-44', '45-54', '55-64', '65+'] as $age)
                                                                                                        <button type="button"
                                                                                                                @click="ages.includes('{{ $age }}') ? ages = ages.filter(a => a !== '{{ $age }}') : ages.push('{{ $age }}')"
                                                                                                                :class="ages.includes(
                                                                                                                        '{{ $age }}'
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-sm' :
                                                                                                                    'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                {{ $age }}
                                                                                                        </button>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                                <!-- Income -->
                                                                                <div>
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Income</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                @foreach (['0-195K', '195-220K', '220-245K', '245K+'] as $inc)
                                                                                                        <button type="button"
                                                                                                                @click="incomes.includes('{{ $inc }}') ? incomes = incomes.filter(i => i !== '{{ $inc }}') : incomes.push('{{ $inc }}')"
                                                                                                                :class="incomes
                                                                                                                    .includes(
                                                                                                                        '{{ $inc }}'
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-sm' :
                                                                                                                    'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                {{ $inc }}
                                                                                                        </button>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                        </div>

                                                                        <!-- Geo & Locations -->
                                                                        <div x-show="activeTab === 'geo'">

                                                                                <!-- Broad Targeting -->
                                                                                <div
                                                                                        class="rounded-xl border border-border bg-background p-5 mb-6">
                                                                                        <div
                                                                                                class="flex items-center gap-2 mb-4">
                                                                                                <div
                                                                                                        class="w-6 h-6 rounded-md bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                                                                        <svg class="w-3.5 h-3.5 text-primary"
                                                                                                                fill="none"
                                                                                                                stroke="currentColor"
                                                                                                                viewBox="0 0 24 24">
                                                                                                                <path stroke-linecap="round"
                                                                                                                        stroke-linejoin="round"
                                                                                                                        stroke-width="2"
                                                                                                                        d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 004 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                                        </svg>
                                                                                                </div>
                                                                                                <h4
                                                                                                        class="text-xs font-semibold uppercase tracking-wider text-textMuted">
                                                                                                        Broad Targeting
                                                                                                </h4>
                                                                                        </div>

                                                                                        <div
                                                                                                class="grid grid-cols-1 md:grid-cols-3 gap-4">

                                                                                                <!-- Countries -->
                                                                                                <div>
                                                                                                        <div
                                                                                                                class="flex items-center justify-between mb-1.5">
                                                                                                                <label
                                                                                                                        class="text-xs font-medium text-textMuted">Countries</label>
                                                                                                                <span x-show="countries.length > 0"
                                                                                                                        class="text-xs text-primary font-medium"
                                                                                                                        x-text="countries.length + (countries.length === 1 ? ' selected' : ' selected')"></span>
                                                                                                        </div>
                                                                                                        <div class="rounded-lg border border-border bg-white focus-within:border-primary focus-within:ring-1 focus-within:ring-primary/20 transition-all p-2.5 flex flex-col gap-2"
                                                                                                                style="min-height:100px"
                                                                                                                @click="$el.querySelector('input').focus()">
                                                                                                                <input type="text"
                                                                                                                        x-model="countryInput"
                                                                                                                        @keydown.enter.prevent="addCountry()"
                                                                                                                        @keydown.comma.prevent="addCountry()"
                                                                                                                        placeholder="e.g. Israel, USA..."
                                                                                                                        class="w-full bg-transparent text-sm text-textMain placeholder-textLight outline-none px-0.5 py-0.5 cursor-text">
                                                                                                                <div
                                                                                                                        class="flex flex-wrap gap-1.5">
                                                                                                                        <template
                                                                                                                                x-for="(co, ci) in countries"
                                                                                                                                :key="'co-' +
                                                                                                                                ci">
                                                                                                                                <span
                                                                                                                                        class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-medium rounded-full border border-blue-200">
                                                                                                                                        <span
                                                                                                                                                x-text="co"></span>
                                                                                                                                        <button type="button"
                                                                                                                                                @click.stop="removeCountry(ci)"
                                                                                                                                                class="w-3.5 h-3.5 rounded-full flex items-center justify-center hover:bg-blue-200 transition-colors leading-none">&times;</button>
                                                                                                                                </span>
                                                                                                                        </template>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <!-- Regions -->
                                                                                                <div>
                                                                                                        <div
                                                                                                                class="flex items-center justify-between mb-1.5">
                                                                                                                <label
                                                                                                                        class="text-xs font-medium text-textMuted">Regions</label>
                                                                                                                <span x-show="regions.length > 0"
                                                                                                                        class="text-xs text-primary font-medium"
                                                                                                                        x-text="regions.length + ' selected'"></span>
                                                                                                        </div>
                                                                                                        <div class="rounded-lg border border-border bg-white focus-within:border-primary focus-within:ring-1 focus-within:ring-primary/20 transition-all p-2.5 flex flex-col gap-2"
                                                                                                                style="min-height:100px"
                                                                                                                @click="$el.querySelector('input').focus()">
                                                                                                                <input type="text"
                                                                                                                        x-model="regionInput"
                                                                                                                        @keydown.enter.prevent="addRegion()"
                                                                                                                        @keydown.comma.prevent="addRegion()"
                                                                                                                        placeholder="e.g. Central, North..."
                                                                                                                        class="w-full bg-transparent text-sm text-textMain placeholder-textLight outline-none px-0.5 py-0.5 cursor-text">
                                                                                                                <div
                                                                                                                        class="flex flex-wrap gap-1.5">
                                                                                                                        <template
                                                                                                                                x-for="(reg, ri) in regions"
                                                                                                                                :key="'reg-' +
                                                                                                                                ri">
                                                                                                                                <span
                                                                                                                                        class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-0.5 bg-purple-50 text-purple-700 text-xs font-medium rounded-full border border-purple-200">
                                                                                                                                        <span
                                                                                                                                                x-text="reg"></span>
                                                                                                                                        <button type="button"
                                                                                                                                                @click.stop="removeRegion(ri)"
                                                                                                                                                class="w-3.5 h-3.5 rounded-full flex items-center justify-center hover:bg-purple-200 transition-colors leading-none">&times;</button>
                                                                                                                                </span>
                                                                                                                        </template>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>

                                                                                                <!-- Cities -->
                                                                                                <div>
                                                                                                        <div
                                                                                                                class="flex items-center justify-between mb-1.5">
                                                                                                                <label
                                                                                                                        class="text-xs font-medium text-textMuted">Cities</label>
                                                                                                                <span x-show="cities.length > 0"
                                                                                                                        class="text-xs text-primary font-medium"
                                                                                                                        x-text="cities.length + ' selected'"></span>
                                                                                                        </div>
                                                                                                        <div class="rounded-lg border border-border bg-white focus-within:border-primary focus-within:ring-1 focus-within:ring-primary/20 transition-all p-2.5 flex flex-col gap-2"
                                                                                                                style="min-height:100px"
                                                                                                                @click="$el.querySelector('input').focus()">
                                                                                                                <input type="text"
                                                                                                                        x-model="cityInput"
                                                                                                                        @keydown.enter.prevent="addCity()"
                                                                                                                        @keydown.comma.prevent="addCity()"
                                                                                                                        placeholder="e.g. Tel Aviv, Haifa..."
                                                                                                                        class="w-full bg-transparent text-sm text-textMain placeholder-textLight outline-none px-0.5 py-0.5 cursor-text">
                                                                                                                <div
                                                                                                                        class="flex flex-wrap gap-1.5">
                                                                                                                        <template
                                                                                                                                x-for="(city, ci) in cities"
                                                                                                                                :key="'city-' +
                                                                                                                                ci">
                                                                                                                                <span
                                                                                                                                        class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-0.5 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-full border border-emerald-200">
                                                                                                                                        <span
                                                                                                                                                x-text="city"></span>
                                                                                                                                        <button type="button"
                                                                                                                                                @click.stop="removeCity(ci)"
                                                                                                                                                class="w-3.5 h-3.5 rounded-full flex items-center justify-center hover:bg-emerald-200 transition-colors leading-none">&times;</button>
                                                                                                                                </span>
                                                                                                                        </template>
                                                                                                                </div>
                                                                                                        </div>
                                                                                                </div>

                                                                                        </div>

                                                                                        <p
                                                                                                class="text-xs text-textLight mt-3">
                                                                                                Type a value and press
                                                                                                <kbd
                                                                                                        class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">Enter</kbd>
                                                                                                or <kbd
                                                                                                        class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[10px] font-mono">,</kbd>
                                                                                                to add. Leave all empty
                                                                                                to target all.</p>
                                                                                </div>

                                                                                <!-- Proximity Targeting -->
                                                                                <div
                                                                                        class="border-t border-border pt-6">
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-4">
                                                                                                Proximity Targeting
                                                                                                <span
                                                                                                        class="font-normal normal-case text-textLight ml-1">(Points
                                                                                                        of
                                                                                                        Interest)</span>
                                                                                        </h4>

                                                                                        <div
                                                                                                class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                                                                                                <!-- Left: Search + Map -->
                                                                                                <div>

                                                                                                        <!-- AI Assistant -->
                                                                                                        <div
                                                                                                                class="mb-3">
                                                                                                                <button type="button"
                                                                                                                        @click="isAiOpen = !isAiOpen"
                                                                                                                        :class="isAiOpen ? 'bg-violet-50 border-violet-300 text-violet-700' : 'bg-white border-border text-textMuted hover:border-violet-300 hover:text-violet-600 hover:bg-violet-50'"
                                                                                                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-all">
                                                                                                                        <span
                                                                                                                                class="flex items-center gap-2">
                                                                                                                                <svg class="w-4 h-4 text-violet-500 flex-shrink-0"
                                                                                                                                        viewBox="0 0 24 24"
                                                                                                                                        fill="currentColor">
                                                                                                                                        <path
                                                                                                                                                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                                                                                                                </svg>
                                                                                                                                Ask
                                                                                                                                AI
                                                                                                                                to
                                                                                                                                find
                                                                                                                                locations
                                                                                                                        </span>
                                                                                                                        <svg class="w-3.5 h-3.5 transition-transform"
                                                                                                                                :class="isAiOpen
                                                                                                                                    ?
                                                                                                                                    'rotate-180' :
                                                                                                                                    ''"
                                                                                                                                fill="none"
                                                                                                                                stroke="currentColor"
                                                                                                                                viewBox="0 0 24 24">
                                                                                                                                <path stroke-linecap="round"
                                                                                                                                        stroke-linejoin="round"
                                                                                                                                        stroke-width="2"
                                                                                                                                        d="M19 9l-7 7-7-7" />
                                                                                                                        </svg>
                                                                                                                </button>

                                                                                                                <div x-show="isAiOpen"
                                                                                                                        x-transition:enter="transition ease-out duration-150"
                                                                                                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                                                                                                        x-transition:enter-end="opacity-100 translate-y-0"
                                                                                                                        class="mt-2 rounded-lg border border-violet-200 bg-violet-50/60 p-3">
                                                                                                                        <p
                                                                                                                                class="text-xs text-violet-600 font-medium mb-2">
                                                                                                                                Describe
                                                                                                                                the
                                                                                                                                locations
                                                                                                                                you
                                                                                                                                want
                                                                                                                                to
                                                                                                                                target
                                                                                                                        </p>
                                                                                                                        <div
                                                                                                                                class="flex gap-2">
                                                                                                                                <input type="text"
                                                                                                                                        x-model="aiPrompt"
                                                                                                                                        @keydown.enter.prevent="generateAiLocations()"
                                                                                                                                        :disabled="isAiLoading"
                                                                                                                                        placeholder="e.g. Find 5 universities in Tel Aviv..."
                                                                                                                                        class="flex-1 rounded-md border border-violet-200 bg-white text-sm text-textMain placeholder-textLight px-3 py-1.5 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-300 disabled:opacity-50">
                                                                                                                                <button type="button"
                                                                                                                                        @click="generateAiLocations()"
                                                                                                                                        :disabled="isAiLoading
                                                                                                                                            ||
                                                                                                                                            aiPrompt
                                                                                                                                            .trim() === ''"
                                                                                                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 text-white text-sm font-medium rounded-md hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap">
                                                                                                                                        <span x-show="!isAiLoading"
                                                                                                                                                class="flex items-center gap-1.5">
                                                                                                                                                <svg class="w-3.5 h-3.5"
                                                                                                                                                        viewBox="0 0 24 24"
                                                                                                                                                        fill="currentColor">
                                                                                                                                                        <path
                                                                                                                                                                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                                                                                                                                </svg>
                                                                                                                                                Generate
                                                                                                                                        </span>
                                                                                                                                        <span x-show="isAiLoading"
                                                                                                                                                class="flex items-center gap-1.5">
                                                                                                                                                <svg class="w-3.5 h-3.5 animate-spin"
                                                                                                                                                        fill="none"
                                                                                                                                                        viewBox="0 0 24 24">
                                                                                                                                                        <circle class="opacity-25"
                                                                                                                                                                cx="12"
                                                                                                                                                                cy="12"
                                                                                                                                                                r="10"
                                                                                                                                                                stroke="currentColor"
                                                                                                                                                                stroke-width="4" />
                                                                                                                                                        <path class="opacity-75"
                                                                                                                                                                fill="currentColor"
                                                                                                                                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                                                                                                                </svg>
                                                                                                                                                Thinking...
                                                                                                                                        </span>
                                                                                                                                </button>
                                                                                                                        </div>
                                                                                                                        <!-- Loading state -->
                                                                                                                        <div x-show="isAiLoading"
                                                                                                                                class="mt-2 flex items-center gap-2 text-xs text-violet-500">
                                                                                                                                <svg class="w-3 h-3 animate-spin flex-shrink-0"
                                                                                                                                        fill="none"
                                                                                                                                        viewBox="0 0 24 24">
                                                                                                                                        <circle class="opacity-25"
                                                                                                                                                cx="12"
                                                                                                                                                cy="12"
                                                                                                                                                r="10"
                                                                                                                                                stroke="currentColor"
                                                                                                                                                stroke-width="4" />
                                                                                                                                        <path class="opacity-75"
                                                                                                                                                fill="currentColor"
                                                                                                                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                                                                                                </svg>
                                                                                                                                AI
                                                                                                                                is
                                                                                                                                thinking...
                                                                                                                                finding
                                                                                                                                the
                                                                                                                                best
                                                                                                                                locations
                                                                                                                                for
                                                                                                                                you
                                                                                                                        </div>
                                                                                                                </div>
                                                                                                        </div>

                                                                                                        <!-- Place Search -->
                                                                                                        <div
                                                                                                                class="relative mb-3">
                                                                                                                <div
                                                                                                                        class="relative">
                                                                                                                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-textLight pointer-events-none"
                                                                                                                                fill="none"
                                                                                                                                stroke="currentColor"
                                                                                                                                viewBox="0 0 24 24">
                                                                                                                                <path stroke-linecap="round"
                                                                                                                                        stroke-linejoin="round"
                                                                                                                                        stroke-width="2"
                                                                                                                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                                                                                        </svg>
                                                                                                                        <input type="text"
                                                                                                                                x-model="searchQuery"
                                                                                                                                @input.debounce.350ms="searchPlace()"
                                                                                                                                @keydown.escape="searchResults = []; searchQuery = ''"
                                                                                                                                placeholder="Search for a place or address..."
                                                                                                                                class="w-full pl-9 pr-8 rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                                        <button type="button"
                                                                                                                                x-show="searchQuery"
                                                                                                                                @click="searchQuery = ''; searchResults = []"
                                                                                                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-textLight hover:text-textMain leading-none text-lg">&times;</button>
                                                                                                                </div>
                                                                                                                <!-- Results dropdown -->
                                                                                                                <ul x-show="searchResults.length > 0"
                                                                                                                        @click.outside="searchResults = []"
                                                                                                                        class="absolute left-0 right-0 top-full mt-1 bg-white border border-border rounded-lg shadow-elevated overflow-hidden"
                                                                                                                        style="z-index:1000">
                                                                                                                        <template
                                                                                                                                x-for="result in searchResults"
                                                                                                                                :key="result
                                                                                                                                    .place_id">
                                                                                                                                <li @click="selectResult(result)"
                                                                                                                                        class="px-4 py-2.5 text-sm text-textMain cursor-pointer hover:bg-primaryLight hover:text-primary border-b border-border last:border-b-0 truncate"
                                                                                                                                        x-text="result.display_name">
                                                                                                                                </li>
                                                                                                                        </template>
                                                                                                                </ul>
                                                                                                        </div>

                                                                                                        <!-- Map -->
                                                                                                        <div id="geo-map"
                                                                                                                class="w-full rounded-xl border border-border"
                                                                                                                style="height:400px;z-index:0">
                                                                                                        </div>
                                                                                                        <p
                                                                                                                class="text-xs text-textLight mt-2">
                                                                                                                Click
                                                                                                                the map
                                                                                                                to pin a
                                                                                                                location
                                                                                                                — name
                                                                                                                is
                                                                                                                auto-filled
                                                                                                                via
                                                                                                                reverse
                                                                                                                geocoding.
                                                                                                        </p>
                                                                                                </div>

                                                                                                <!-- Right: New Location form + Saved Locations list -->
                                                                                                <div>
                                                                                                        <!-- Add Location Form -->
                                                                                                        <div
                                                                                                                class="bg-background border border-border rounded-lg p-4 mb-4">
                                                                                                                <h4
                                                                                                                        class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                                        New
                                                                                                                        Location
                                                                                                                </h4>
                                                                                                                <div
                                                                                                                        class="mb-3">
                                                                                                                        <label
                                                                                                                                class="block text-xs font-medium text-textMuted mb-1">Location
                                                                                                                                Name
                                                                                                                                <span
                                                                                                                                        class="text-textLight">(optional)</span></label>
                                                                                                                        <input type="text"
                                                                                                                                x-model="newLocation.name"
                                                                                                                                placeholder="e.g. Tel Aviv City Center"
                                                                                                                                class="w-full rounded-md border border-border bg-surface text-sm text-textMain placeholder-textLight px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                                </div>
                                                                                                                <!-- Pending coordinates (read-only info) -->
                                                                                                                <p x-show="newLocation.lat !== ''"
                                                                                                                        class="text-xs font-mono text-textMuted mb-3">
                                                                                                                        <svg class="inline w-3 h-3 mr-0.5 text-primary"
                                                                                                                                fill="currentColor"
                                                                                                                                viewBox="0 0 24 24">
                                                                                                                                <path
                                                                                                                                        d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                                                                                                        </svg>
                                                                                                                        <span
                                                                                                                                x-text="newLocation.lat"></span>,
                                                                                                                        <span
                                                                                                                                x-text="newLocation.lng"></span>
                                                                                                                        &nbsp;&middot;&nbsp;
                                                                                                                        1
                                                                                                                        km
                                                                                                                        radius
                                                                                                                        <span
                                                                                                                                class="text-textLight">(adjustable
                                                                                                                                after
                                                                                                                                adding)</span>
                                                                                                                </p>
                                                                                                                <p x-show="newLocation.lat === ''"
                                                                                                                        class="text-xs text-textLight italic mb-3">
                                                                                                                        Search
                                                                                                                        or
                                                                                                                        click
                                                                                                                        the
                                                                                                                        map
                                                                                                                        to
                                                                                                                        set
                                                                                                                        a
                                                                                                                        pin.
                                                                                                                </p>
                                                                                                                <div
                                                                                                                        class="flex gap-2">
                                                                                                                        <button type="button"
                                                                                                                                @click="addLocation()"
                                                                                                                                :disabled="newLocation
                                                                                                                                    .lat === ''"
                                                                                                                                :class="newLocation
                                                                                                                                    .lat === '' ?
                                                                                                                                    'opacity-50 cursor-not-allowed' :
                                                                                                                                    'hover:bg-primaryHover'"
                                                                                                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-sm font-medium rounded-md transition-colors">
                                                                                                                                <svg class="w-4 h-4"
                                                                                                                                        fill="none"
                                                                                                                                        stroke="currentColor"
                                                                                                                                        viewBox="0 0 24 24">
                                                                                                                                        <path stroke-linecap="round"
                                                                                                                                                stroke-linejoin="round"
                                                                                                                                                stroke-width="2"
                                                                                                                                                d="M12 4v16m8-8H4" />
                                                                                                                                </svg>
                                                                                                                                Add
                                                                                                                        </button>
                                                                                                                        <button type="button"
                                                                                                                                @click="openNewEdit()"
                                                                                                                                x-show="newLocation.lat !== ''"
                                                                                                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-border text-textMuted text-sm font-medium rounded-md hover:bg-gray-50 transition-colors">
                                                                                                                                <svg class="w-4 h-4"
                                                                                                                                        fill="none"
                                                                                                                                        stroke="currentColor"
                                                                                                                                        viewBox="0 0 24 24">
                                                                                                                                        <path stroke-linecap="round"
                                                                                                                                                stroke-linejoin="round"
                                                                                                                                                stroke-width="2"
                                                                                                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                                                                                </svg>
                                                                                                                                Edit
                                                                                                                                Details
                                                                                                                        </button>
                                                                                                                </div>
                                                                                                        </div>

                                                                                                        <!-- Locations List -->
                                                                                                        <div x-show="locations.length > 0"
                                                                                                                class="space-y-1.5">
                                                                                                                <h4
                                                                                                                        class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-2">
                                                                                                                        Saved
                                                                                                                        Locations
                                                                                                                </h4>
                                                                                                                <template
                                                                                                                        x-for="(loc, i) in locations"
                                                                                                                        :key="'locrow-' + i">
                                                                                                                        <div class="flex items-center justify-between gap-3 bg-surface border border-border rounded-lg px-4 py-3 cursor-pointer hover:border-primary hover:bg-primaryLight transition-colors"
                                                                                                                                @click="openEdit(i)">
                                                                                                                                <div
                                                                                                                                        class="flex items-start gap-3 min-w-0">
                                                                                                                                        <div
                                                                                                                                                class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                                                                                                                                                <svg class="w-3.5 h-3.5 text-primary"
                                                                                                                                                        fill="none"
                                                                                                                                                        stroke="currentColor"
                                                                                                                                                        viewBox="0 0 24 24">
                                                                                                                                                        <path stroke-linecap="round"
                                                                                                                                                                stroke-linejoin="round"
                                                                                                                                                                stroke-width="2"
                                                                                                                                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                                                                                                        <path stroke-linecap="round"
                                                                                                                                                                stroke-linejoin="round"
                                                                                                                                                                stroke-width="2"
                                                                                                                                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                                                                                                </svg>
                                                                                                                                        </div>
                                                                                                                                        <div
                                                                                                                                                class="min-w-0">
                                                                                                                                                <p class="text-sm font-semibold text-textMain truncate"
                                                                                                                                                        x-text="loc.name || 'Unnamed Location'">
                                                                                                                                                </p>
                                                                                                                                                <p
                                                                                                                                                        class="text-xs text-textMuted mt-0.5 font-mono">
                                                                                                                                                        <span
                                                                                                                                                                x-text="loc.lat"></span>,
                                                                                                                                                        <span
                                                                                                                                                                x-text="loc.lng"></span>
                                                                                                                                                        &nbsp;&middot;&nbsp;
                                                                                                                                                        <span
                                                                                                                                                                x-text="Math.round((loc.radius_meters || 1000) / 1000)"></span>
                                                                                                                                                        km
                                                                                                                                                        radius
                                                                                                                                                </p>
                                                                                                                                        </div>
                                                                                                                                </div>
                                                                                                                                <svg class="w-4 h-4 text-textLight flex-shrink-0"
                                                                                                                                        fill="none"
                                                                                                                                        stroke="currentColor"
                                                                                                                                        viewBox="0 0 24 24">
                                                                                                                                        <path stroke-linecap="round"
                                                                                                                                                stroke-linejoin="round"
                                                                                                                                                stroke-width="2"
                                                                                                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                                                                                </svg>
                                                                                                                        </div>
                                                                                                                </template>
                                                                                                        </div>

                                                                                                        <div x-show="locations.length === 0"
                                                                                                                class="text-sm text-textLight italic text-center py-6">
                                                                                                                No
                                                                                                                locations
                                                                                                                added
                                                                                                                yet.
                                                                                                        </div>
                                                                                                </div>

                                                                                        </div>
                                                                                </div>

                                                                                <!-- Edit Location Modal -->
                                                                                <div x-show="showEditModal" x-cloak
                                                                                        class="fixed inset-0 flex items-center justify-center"
                                                                                        style="z-index:9999">
                                                                                        <div class="absolute inset-0 bg-black/40"
                                                                                                @click="closeEdit()">
                                                                                        </div>
                                                                                        <div
                                                                                                class="relative bg-white rounded-xl shadow-elevated p-6 w-full max-w-md mx-4">
                                                                                                <h3
                                                                                                        class="text-base font-bold text-textMain mb-5">
                                                                                                        Edit Location
                                                                                                </h3>
                                                                                                <!-- Name -->
                                                                                                <div class="mb-4">
                                                                                                        <label
                                                                                                                class="block text-xs font-medium text-textMuted mb-1">Location
                                                                                                                Name</label>
                                                                                                        <input type="text"
                                                                                                                x-model="editingLocation.name"
                                                                                                                class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                </div>
                                                                                                <!-- Lat / Lng -->
                                                                                                <div
                                                                                                        class="grid grid-cols-2 gap-3 mb-4">
                                                                                                        <div>
                                                                                                                <label
                                                                                                                        class="block text-xs font-medium text-textMuted mb-1">Latitude</label>
                                                                                                                <input type="number"
                                                                                                                        x-model="editingLocation.lat"
                                                                                                                        step="any"
                                                                                                                        class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                        </div>
                                                                                                        <div>
                                                                                                                <label
                                                                                                                        class="block text-xs font-medium text-textMuted mb-1">Longitude</label>
                                                                                                                <input type="number"
                                                                                                                        x-model="editingLocation.lng"
                                                                                                                        step="any"
                                                                                                                        class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                        </div>
                                                                                                </div>
                                                                                                <!-- Radius slider (km) -->
                                                                                                <div class="mb-6">
                                                                                                        <div
                                                                                                                class="flex justify-between items-center mb-2">
                                                                                                                <label
                                                                                                                        class="text-xs font-medium text-textMuted">Radius</label>
                                                                                                                <span class="text-sm font-semibold text-primary"
                                                                                                                        x-text="editingLocation.radius_km + ' km'"></span>
                                                                                                        </div>
                                                                                                        <input type="range"
                                                                                                                x-model.number="editingLocation.radius_km"
                                                                                                                min="1"
                                                                                                                max="50"
                                                                                                                step="1"
                                                                                                                class="w-full accent-primary">
                                                                                                        <div
                                                                                                                class="flex justify-between text-xs text-textLight mt-1">
                                                                                                                <span>1
                                                                                                                        km</span><span>50
                                                                                                                        km</span>
                                                                                                        </div>
                                                                                                </div>
                                                                                                <!-- Actions -->
                                                                                                <div
                                                                                                        class="flex gap-2">
                                                                                                        <button type="button"
                                                                                                                @click="saveEdit()"
                                                                                                                class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primaryHover transition-colors">
                                                                                                                Save
                                                                                                        </button>
                                                                                                        <button type="button"
                                                                                                                @click="deleteEdit()"
                                                                                                                class="inline-flex justify-center items-center px-4 py-2 bg-dangerLight text-danger text-sm font-medium rounded-md hover:bg-red-200 transition-colors">
                                                                                                                Delete
                                                                                                        </button>
                                                                                                        <button type="button"
                                                                                                                @click="closeEdit()"
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
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Device Types</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                @foreach ([['Mobile', 'Mobile'], ['Tablet', 'Tablet'], ['Desktop', 'Desktop'], ['CTV', 'CTV']] as [$val, $label])
                                                                                                        <span :class="deviceTypes
                                                                                                            .includes(
                                                                                                                '{{ $val }}'
                                                                                                                ) ?
                                                                                                            'bg-primary/10 text-primary border-primary/30' :
                                                                                                            'bg-white text-gray-300 border-gray-200'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border select-none cursor-default">
                                                                                                                {{ $label }}
                                                                                                        </span>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                                <!-- Operating Systems -->
                                                                                <div class="mb-6">
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Operating Systems</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                {{-- iOS and Android: interactive --}}
                                                                                                @foreach ([['iOS', 'iOS'], ['Android', 'Android']] as [$val, $label])
                                                                                                        <button type="button"
                                                                                                                @click="os.includes('{{ $val }}') ? os = os.filter(o => o !== '{{ $val }}') : os.push('{{ $val }}')"
                                                                                                                :class="os.includes(
                                                                                                                        '{{ $val }}'
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-sm' :
                                                                                                                    'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                {{ $label }}
                                                                                                        </button>
                                                                                                @endforeach
                                                                                                {{-- Windows and macOS: disabled --}}
                                                                                                @foreach ([['Windows', 'Windows'], ['macOS', 'macOS']] as [$val, $label])
                                                                                                        <span :class="os.includes(
                                                                                                                '{{ $val }}'
                                                                                                                ) ?
                                                                                                            'bg-primary/10 text-primary border-primary/30' :
                                                                                                            'bg-white text-gray-300 border-gray-200'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border select-none cursor-default">
                                                                                                                {{ $label }}
                                                                                                        </span>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                                <!-- Connection Type -->
                                                                                <div>
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Connection Type</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                @foreach ([['WiFi', 'Wi-Fi'], ['Cellular', 'Cellular']] as [$val, $label])
                                                                                                        <span :class="connectionTypes
                                                                                                            .includes(
                                                                                                                '{{ $val }}'
                                                                                                                ) ?
                                                                                                            'bg-primary/10 text-primary border-primary/30' :
                                                                                                            'bg-white text-gray-300 border-gray-200'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border select-none cursor-default">
                                                                                                                {{ $label }}
                                                                                                        </span>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                        </div>

                                                                        <!-- Inventory -->
                                                                        <div x-show="activeTab === 'inventory'">

                                                                                <!-- Environment -->
                                                                                <div class="mb-6">
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Environment</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-2">
                                                                                                @foreach ([['In-App', 'In-App'], ['Mobile Web', 'Mobile Web']] as [$val, $label])
                                                                                                        <button type="button"
                                                                                                                @click="environments.includes('{{ $val }}') ? environments = environments.filter(e => e !== '{{ $val }}') : environments.push('{{ $val }}')"
                                                                                                                :class="environments
                                                                                                                    .includes(
                                                                                                                        '{{ $val }}'
                                                                                                                        ) ?
                                                                                                                    'bg-primary text-white border-primary shadow-sm' :
                                                                                                                    'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                {{ $label }}
                                                                                                        </button>
                                                                                                @endforeach
                                                                                        </div>
                                                                                </div>

                                                                                {{-- Domain / App Lists hidden for now --}}

                                                                        </div>

                                                                        <!-- Schedule -->
                                                                        <div x-show="activeTab === 'schedule'">

                                                                                <!-- Days of the Week (Israel workweek: Sun–Thu / Weekend: Fri–Sat) -->
                                                                                <div class="mb-6">
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Days of the Week</h4>
                                                                                        <div
                                                                                                class="flex flex-wrap gap-3">
                                                                                                <!-- Workweek group -->
                                                                                                <div
                                                                                                        class="flex flex-wrap gap-2">
                                                                                                        @foreach ([['Sun', 'Sun'], ['Mon', 'Mon'], ['Tue', 'Tue'], ['Wed', 'Wed'], ['Thu', 'Thu']] as [$val, $label])
                                                                                                                <button type="button"
                                                                                                                        @click="days.includes('{{ $val }}') ? days = days.filter(d => d !== '{{ $val }}') : days.push('{{ $val }}')"
                                                                                                                        :class="days.includes('{{ $val }}') ?
                                                                                                                            'bg-primary text-white border-primary shadow-sm' :
                                                                                                                            'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                        class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                        {{ $label }}
                                                                                                                </button>
                                                                                                        @endforeach
                                                                                                </div>
                                                                                                <!-- Divider -->
                                                                                                <div
                                                                                                        class="w-px bg-border self-stretch mx-1">
                                                                                                </div>
                                                                                                <!-- Weekend group -->
                                                                                                <div
                                                                                                        class="flex flex-wrap gap-2">
                                                                                                        @foreach ([['Fri', 'Fri'], ['Sat', 'Sat']] as [$val, $label])
                                                                                                                <button type="button"
                                                                                                                        @click="days.includes('{{ $val }}') ? days = days.filter(d => d !== '{{ $val }}') : days.push('{{ $val }}')"
                                                                                                                        :class="days.includes('{{ $val }}') ?
                                                                                                                            'bg-primary text-white border-primary shadow-sm' :
                                                                                                                            'bg-white text-textMuted border-gray-200 hover:border-primary hover:text-primary hover:bg-blue-50'"
                                                                                                                        class="px-4 py-2 text-sm font-medium rounded-full border transition-all select-none">
                                                                                                                        {{ $label }}
                                                                                                                </button>
                                                                                                        @endforeach
                                                                                                </div>
                                                                                        </div>
                                                                                        <p
                                                                                                class="mt-2 text-xs text-textLight">
                                                                                                Workweek: Sun–Thu
                                                                                                &nbsp;|&nbsp; Weekend:
                                                                                                Fri–Sat</p>
                                                                                </div>

                                                                                <!-- Active Hours -->
                                                                                <div>
                                                                                        <h4
                                                                                                class="text-xs font-semibold uppercase tracking-wider text-textMuted mb-3">
                                                                                                Active Hours</h4>
                                                                                        <div
                                                                                                class="grid grid-cols-2 gap-4 max-w-xs">
                                                                                                <div>
                                                                                                        <label
                                                                                                                class="block text-xs font-medium text-textMuted mb-1">Start
                                                                                                                Time</label>
                                                                                                        <input type="time"
                                                                                                                name="targeting_rules[time_start]"
                                                                                                                value="{{ old('targeting_rules.time_start', $campaign->targeting_rules['time_start'] ?? '') }}"
                                                                                                                class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                </div>
                                                                                                <div>
                                                                                                        <label
                                                                                                                class="block text-xs font-medium text-textMuted mb-1">End
                                                                                                                Time</label>
                                                                                                        <input type="time"
                                                                                                                name="targeting_rules[time_end]"
                                                                                                                value="{{ old('targeting_rules.time_end', $campaign->targeting_rules['time_end'] ?? '') }}"
                                                                                                                class="w-full rounded-md border border-border bg-surface text-sm text-textMain px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                                                                                </div>
                                                                                        </div>
                                                                                        <p
                                                                                                class="mt-2 text-xs text-textLight">
                                                                                                Leave blank to serve ads
                                                                                                at all hours.</p>
                                                                                </div>

                                                                        </div>

                                                                </div>
                                                        </div>
                                                </div>
                                        </div>

                                </div>
                                <!-- Footer Actions -->
                                <div
                                        class="flex items-center justify-end gap-3 mt-4 mb-8 pt-6 border-t border-gray-100">
                                        <a href="{{ route('campaigns.index') }}"
                                                class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                                        <button type="submit" form="campaignForm"
                                                class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                                                Save Changes
                                        </button>
                                </div>
                        </form>
                </div>

                <!-- AI Campaign Assistant: Backdrop -->
                <div x-show="isOpen" x-cloak x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0" @click="isOpen = false"
                        class="fixed inset-0 bg-black/25 backdrop-blur-sm" style="z-index:9000"></div>

                <!-- AI Campaign Assistant: Sliding Panel -->
                <div x-show="isOpen" x-cloak x-transition:enter="transition ease-out duration-300 transform"
                        x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-200 transform"
                        x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                        class="fixed top-0 right-0 h-full w-full max-w-[440px] bg-white shadow-2xl flex flex-col border-l border-gray-200 transform"
                        style="z-index:9001">

                        <!-- Panel Header -->
                        <div
                                class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-violet-50 to-white flex-shrink-0">
                                <div class="flex items-center gap-3">
                                        <div
                                                class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-5 h-5 text-violet-600" viewBox="0 0 24 24"
                                                        fill="currentColor">
                                                        <path
                                                                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                                </svg>
                                        </div>
                                        <div>
                                                <p class="text-sm font-semibold text-gray-900">AI Campaign Assistant
                                                </p>
                                                <p class="text-xs text-gray-400">Paste a brief or give instructions</p>
                                        </div>
                                </div>
                                <button type="button" @click="isOpen = false"
                                        class="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                </button>
                        </div>

                        <!-- Messages Area -->
                        <div class="flex-1 overflow-y-auto px-4 py-5 space-y-3" x-ref="messagesArea">

                                <!-- Empty state -->
                                <div x-show="messages.length === 0"
                                        class="flex flex-col items-center justify-center h-full text-center py-10">
                                        <div
                                                class="w-14 h-14 rounded-2xl bg-violet-100 flex items-center justify-center mb-4">
                                                <svg class="w-7 h-7 text-violet-500" viewBox="0 0 24 24"
                                                        fill="currentColor">
                                                        <path
                                                                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                                                </svg>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-700 mb-1">Ready to assist</p>
                                        <p class="text-xs text-gray-400 max-w-[240px] leading-relaxed">Paste an email
                                                brief or describe changes and I'll update the form fields automatically.
                                        </p>
                                        <div class="mt-5 grid grid-cols-1 gap-2 w-full max-w-[280px]">
                                                <button type="button"
                                                        @click="inputText = 'Campaign for women 25-45 in Tel Aviv, budget 50,000 NIS, mobile only, March 2025'; $nextTick(() => $refs.chatInput.focus())"
                                                        class="text-left text-xs px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 hover:bg-violet-50 hover:border-violet-200 hover:text-violet-700 transition-colors">
                                                        💡 "Women 25-45, Tel Aviv, 50K budget..."
                                                </button>
                                                <button type="button"
                                                        @click="inputText = 'Change budget to 30,000 and remove Jerusalem region'; $nextTick(() => $refs.chatInput.focus())"
                                                        class="text-left text-xs px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 hover:bg-violet-50 hover:border-violet-200 hover:text-violet-700 transition-colors">
                                                        ✏️ "Change budget to 30,000..."
                                                </button>
                                        </div>
                                </div>

                                <!-- Chat messages -->
                                <template x-for="(msg, i) in messages" :key="i">
                                        <div
                                                :class="msg.role === 'user' ? 'flex justify-end' : 'flex items-start gap-2'">
                                                <!-- AI avatar -->
                                                <div x-show="msg.role === 'ai'"
                                                        class="w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                                        <svg class="w-3.5 h-3.5 text-violet-600" viewBox="0 0 24 24"
                                                                fill="currentColor">
                                                                <path
                                                                        d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                                                        </svg>
                                                </div>
                                                <div :class="msg.role === 'user' ?
                                                    'bg-primary text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[85%] text-sm leading-relaxed' :
                                                    'bg-gray-100 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%] text-sm leading-relaxed'"
                                                        x-text="msg.content">
                                                </div>
                                        </div>
                                </template>

                                <!-- Typing indicator -->
                                <div x-show="isTyping" class="flex items-start gap-2">
                                        <div
                                                class="w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-3.5 h-3.5 text-violet-600" viewBox="0 0 24 24"
                                                        fill="currentColor">
                                                        <path
                                                                d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                                                </svg>
                                        </div>
                                        <div class="bg-gray-100 rounded-2xl rounded-tl-sm px-4 py-3">
                                                <div class="flex gap-1 items-center">
                                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                                                                style="animation-delay:0ms"></span>
                                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                                                                style="animation-delay:150ms"></span>
                                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                                                                style="animation-delay:300ms"></span>
                                                </div>
                                        </div>
                                </div>

                        </div>

                        <!-- Input Area -->
                        <div class="border-t border-gray-100 px-4 py-3 bg-white flex-shrink-0">
                                <div class="flex gap-2 items-end">
                                        <textarea x-model="inputText" x-ref="chatInput"
                                                @keydown.enter.prevent="!$event.shiftKey ? sendMessage() : inputText += '\n'" :disabled="isTyping"
                                                rows="2" placeholder="Paste a brief or describe changes... (Enter to send)"
                                                class="flex-1 text-sm px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-300 focus:ring-1 focus:ring-violet-200 focus:bg-white resize-none transition-all disabled:opacity-50"></textarea>
                                        <button type="button" @click="sendMessage()"
                                                :disabled="isTyping || inputText.trim() === ''"
                                                class="flex-shrink-0 w-9 h-9 rounded-xl bg-violet-600 text-white flex items-center justify-center hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all hover:-translate-y-0.5">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                                </svg>
                                        </button>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1.5">Shift+Enter for new line · Enter to send
                                </p>
                        </div>

                </div>

            @php
                $tr     = $campaign->targeting_rules ?? [];
                $fmt    = fn($arr, $def = 'All') => empty($arr) ? $def : implode(', ', $arr);
                $cities = $tr['cities'] ?? [];
                if (is_string($cities)) $cities = json_decode($cities, true) ?? [];

                // ── Header ───────────────────────────────────────────────
                $lines   = [];
                $lines[] = 'Campaign:    ' . $campaign->name;
                $lines[] = 'Client:      ' . ($campaign->client->name ?? '—');
                $lines[] = 'Status:      ' . ucfirst($campaign->status ?? '—');
                $lines[] = 'Period:      '
                    . ($campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('M j, Y') : '—')
                    . ' – '
                    . ($campaign->end_date   ? \Carbon\Carbon::parse($campaign->end_date)->format('M j, Y')   : '—');
                if (auth()->user()->hasPermission('can_view_budget')) {
                    $lines[] = 'Budget:      ' . ($campaign->budget ? number_format($campaign->budget) . ' NIS' : '—');
                }
                $lines[] = 'Impressions: ' . ($campaign->expected_impressions ? number_format($campaign->expected_impressions) : '—');

                // ── Targeting ────────────────────────────────────────────
                $lines[] = '';
                $lines[] = str_repeat('─', 34);
                $lines[] = 'TARGETING';
                $lines[] = str_repeat('─', 34);

                // Targeting summary line
                $tSummaryParts = [];
                $gStr = $fmt($tr['genders'] ?? []);
                $aStr = $fmt($tr['ages']    ?? []);
                $iStr = $fmt($tr['incomes'] ?? []);
                $dStr = $fmt($tr['days']    ?? []);
                $coStr = $fmt($tr['countries'] ?? ['Israel']);
                if ($gStr !== 'All' || $aStr !== 'All') $tSummaryParts[] = trim($gStr . ($gStr !== 'All' && $aStr !== 'All' ? ', ' : '') . ($aStr !== 'All' ? $aStr : ''));
                if ($coStr !== 'All') $tSummaryParts[] = $coStr;
                if (!empty($tr['regions'])) $tSummaryParts[] = implode(', ', $tr['regions']);
                if ($dStr !== 'All') $tSummaryParts[] = $dStr;
                $lines[] = '  → ' . (empty($tSummaryParts) ? 'Broad / No restrictions' : implode(' · ', $tSummaryParts));

                $lines[] = '';
                $lines[] = 'Demographics';
                $lines[] = '  Gender:       ' . $fmt($tr['genders'] ?? []);
                $lines[] = '  Age:          ' . $fmt($tr['ages']    ?? []);
                $lines[] = '  Income:       ' . $fmt($tr['incomes'] ?? []);
                $lines[] = '';
                $lines[] = 'Devices & Technology';
                $lines[] = '  Device Types: ' . $fmt($tr['device_types'] ?? ['Mobile', 'Tablet']);
                $lines[] = '  OS:           ' . $fmt($tr['os'] ?? ['iOS', 'Android', 'Windows', 'macOS']);
                $lines[] = '  Connection:   ' . $fmt($tr['connection_types'] ?? ['WiFi', 'Cellular']);
                $lines[] = '';
                $lines[] = 'Inventory';
                $lines[] = '  Environment:  ' . $fmt($tr['environments'] ?? []);
                $lines[] = '';
                $lines[] = 'Schedule';
                $lines[] = '  Days:         ' . $fmt($tr['days'] ?? []);
                $lines[] = '';
                $lines[] = 'Geo Targeting';
                $lines[] = '  Countries:    ' . $fmt($tr['countries'] ?? ['Israel']);
                if (!empty($tr['regions'])) $lines[] = '  Regions:      ' . implode(', ', $tr['regions']);
                if (!empty($cities))        $lines[] = '  Cities:       ' . implode(', ', $cities);
                if ($campaign->locations->isNotEmpty()) {
                    $lines[] = '  Proximity:';
                    foreach ($campaign->locations as $loc) {
                        $km      = round(($loc->radius_meters ?? 1000) / 1000);
                        $lines[] = '    • ' . ($loc->name ?? 'Unnamed')
                            . ' (' . number_format((float)$loc->lat, 4) . ', ' . number_format((float)$loc->lng, 4)
                            . ', ' . $km . 'km)';
                    }
                }

                // ── Audiences ────────────────────────────────────────────
                $lines[] = '';
                $lines[] = str_repeat('─', 34);
                $lines[] = 'AUDIENCES (' . $campaign->audiences->count() . ')';
                $lines[] = str_repeat('─', 34);
                if ($campaign->audiences->isEmpty()) {
                    $lines[] = '  None connected';
                } else {
                    foreach ($campaign->audiences as $audience) {
                        $lines[] = '  • ' . $audience->name . '  [' . $audience->main_category . ']';
                    }
                }

                // ── Creatives ────────────────────────────────────────────
                $lines[] = '';
                $lines[] = str_repeat('─', 34);
                $lines[] = 'CREATIVES (' . $campaign->creatives->count() . ')';
                $lines[] = str_repeat('─', 34);
                if ($campaign->creatives->isEmpty()) {
                    $lines[] = '  None added';
                } else {
                    foreach ($campaign->creatives as $creative) {
                        $statusLabel = $creative->status ? 'Active' : 'Inactive';
                        $lines[] = '';
                        $lines[] = '  ' . $creative->name . '  [' . $statusLabel . ']';
                        if ($creative->landing) $lines[] = '  Landing: ' . $creative->landing;
                        $files = $creative->files;
                        if ($files->isNotEmpty()) {
                            foreach ($files as $file) {
                                $dim  = ($file->width && $file->height) ? $file->width . '×' . $file->height : '—';
                                $type = $file->mime_type ?? '—';
                                $kb   = $file->size ? round($file->size / 1024) . ' KB' : '—';
                                $lines[] = '    ▸ ' . $file->name . '  ' . $dim . '  ' . $type . '  ' . $kb;
                            }
                        } else {
                            $lines[] = '    (no files uploaded)';
                        }
                    }
                }

                $summaryText = implode("\n", $lines);
            @endphp

            <!-- Campaign Summary Modal -->
            <div x-show="summaryOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/40 backdrop-blur-sm"
                 style="z-index:9002"></div>

            <div x-show="summaryOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="fixed inset-0 flex items-center justify-center p-4"
                 style="z-index:9003"
                 @click.self="summaryOpen = false">
                <div x-data="{ copied: false }"
                     class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col border border-gray-100">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h2 class="text-base font-semibold text-gray-900">Campaign Summary</h2>
                        </div>
                        <button type="button" @click="summaryOpen = false"
                                class="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-5 min-h-0">
                        <textarea id="summaryTextarea" readonly
                                  class="w-full min-h-[380px] text-xs font-mono text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-4 resize-none focus:outline-none leading-relaxed">{{ $summaryText }}</textarea>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
                        <button type="button" @click="summaryOpen = false"
                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-all">
                            Close
                        </button>
                        <button type="button"
                                @click="navigator.clipboard.writeText(document.getElementById('summaryTextarea').value); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-hover transition-all">
                            <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <svg x-show="copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="copied ? 'Copied!' : 'Copy to Clipboard'"></span>
                        </button>
                    </div>

                </div>
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
                                                        seen.set(a.main_category, {
                                                                name: a.main_category,
                                                                icon: a.icon
                                                        });
                                                }
                                        });
                                        return Array.from(seen.values());
                                },

                                categoryHasSelected(cat) {
                                        return this.allAudiences.some(a => a.main_category === cat && this.selectedIds.includes(
                                                a.id));
                                },

                                get filteredAudiences() {
                                        return this.allAudiences.filter(a => {
                                                if (this.activeCategory && a.main_category !== this
                                                        .activeCategory) return false;
                                                if (this.filterConnected && !this.selectedIds.includes(a
                                                                .id)) return false;
                                                if (this.search) {
                                                        const q = this.search.toLowerCase();
                                                        return a.name.toLowerCase().includes(q) ||
                                                                a.main_category.toLowerCase().includes(
                                                                        q) ||
                                                                a.sub_category.toLowerCase().includes(
                                                                q);
                                                }
                                                return true;
                                        });
                                },

                                get groupedBySub() {
                                        const groups = {};
                                        this.filteredAudiences.forEach(a => {
                                                if (!groups[a.sub_category]) groups[a
                                        .sub_category] = [];
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
                                                        const res = await fetch(
                                                                `/campaigns/${this.campaignId}/audiences`);
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
                                                const res = await fetch(
                                                `/campaigns/${this.campaignId}/audiences/sync`, {
                                                        method: 'POST',
                                                        headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector(
                                                                        'meta[name="csrf-token"]'
                                                                        ).content,
                                                        },
                                                        body: JSON.stringify({
                                                                audience_ids: this
                                                                        .selectedIds
                                                        }),
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
                                                        'X-CSRF-TOKEN': document.querySelector(
                                                                        'meta[name="csrf-token"]')
                                                                .content,
                                                },
                                                body: JSON.stringify({
                                                        audience_ids: this.selectedIds
                                                }),
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
                                newLocation: {
                                        name: '',
                                        lat: '',
                                        lng: ''
                                },
                                searchQuery: '',
                                searchResults: [],
                                showEditModal: false,
                                editingIndex: null, // null = editing newLocation before adding
                                editingLocation: {
                                        name: '',
                                        lat: '',
                                        lng: '',
                                        radius_km: 1
                                },
                                countryInput: '',
                                regionInput: '',
                                cityInput: '',
                                isAiOpen: false,
                                aiPrompt: '',
                                isAiLoading: false,
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
                                                        radius: 7,
                                                        color: '#F97316',
                                                        fillColor: '#F97316',
                                                        fillOpacity: 0.9,
                                                        weight: 2,
                                                }).addTo(this._map);
                                                // Reverse geocode via Nominatim
                                                fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' +
                                                                lat + '&lon=' + lng + '&accept-language=en')
                                                        .then(r => r.json())
                                                        .then(data => {
                                                                const a = data.address || {};
                                                                this.newLocation.name = a
                                                                        .neighbourhood || a.suburb || a
                                                                        .city_district ||
                                                                        a.town || a.city || a.county ||
                                                                        (data.display_name || '').split(
                                                                                ',')[0];
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
                                                const lat = parseFloat(loc.lat),
                                                        lng = parseFloat(loc.lng);
                                                if (isNaN(lat) || isNaN(lng)) return;
                                                const m = L.marker([lat, lng]);
                                                if (loc.name) {
                                                        m.bindTooltip(loc.name, {
                                                                permanent: true,
                                                                direction: 'top',
                                                                offset: [0, -8]
                                                        });
                                                }
                                                m.on('click', () => this.openEdit(i));
                                                m.addTo(this._map);
                                                const c = L.circle([lat, lng], {
                                                        radius: loc.radius_meters,
                                                        color: '#2563EB',
                                                        weight: 2,
                                                        fillOpacity: 0.1,
                                                }).addTo(this._map);
                                                this._layers.push(m, c);
                                        });
                                },
                                addLocation() {
                                        if (this.newLocation.lat !== '' && this.newLocation.lng !== '') {
                                                this.locations.push({
                                                        name: this.newLocation.name,
                                                        lat: this.newLocation.lat,
                                                        lng: this.newLocation.lng,
                                                        radius_meters: 1000,
                                                });
                                                this.newLocation = {
                                                        name: '',
                                                        lat: '',
                                                        lng: ''
                                                };
                                                if (this._clickMarker) {
                                                        this._clickMarker.remove();
                                                        this._clickMarker = null;
                                                }
                                                this.drawMarkers();
                                        }
                                },
                                removeLocation(index) {
                                        this.locations.splice(index, 1);
                                        this.drawMarkers();
                                },
                                _fmt(arr, total) {
                                        if (!arr || arr.length === 0 || arr.length >= total) return 'All';
                                        return arr.join(', ');
                                },
                                summaryGeo() {
                                        const parts = [];
                                        const broad = [];
                                        if (this.countries.length > 0) broad.push('Countries: ' + this.countries.join(', '));
                                        else broad.push('Countries: All');
                                        if (this.regions.length > 0) broad.push('Regions: ' + this.regions.join(', '));
                                        if (this.cities.length > 0) broad.push('Cities: ' + this.cities.join(', '));
                                        parts.push(...broad);
                                        if (this.locations.length > 0) {
                                                const locs = this.locations.map(l => {
                                                        const lat = parseFloat(l.lat).toFixed(4);
                                                        const lng = parseFloat(l.lng).toFixed(4);
                                                        const km = Math.round((l.radius_meters || 1000) / 1000);
                                                        return `${l.name || 'Unnamed'} (${lat}, ${lng}, ${km}km)`;
                                                }).join('  ·  ');
                                                parts.push('Proximity: ' + locs);
                                        }
                                        return parts.join('  ·  ') || 'All';
                                },
                                addCountry() {
                                        const c = this.countryInput.trim();
                                        if (c && !this.countries.includes(c)) this.countries.push(c);
                                        this.countryInput = '';
                                },
                                removeCountry(index) {
                                        this.countries.splice(index, 1);
                                },
                                addRegion() {
                                        const r = this.regionInput.trim();
                                        if (r && !this.regions.includes(r)) this.regions.push(r);
                                        this.regionInput = '';
                                },
                                removeRegion(index) {
                                        this.regions.splice(index, 1);
                                },
                                addCity() {
                                        const c = this.cityInput.trim();
                                        if (c && !this.cities.includes(c)) this.cities.push(c);
                                        this.cityInput = '';
                                },
                                removeCity(index) {
                                        this.cities.splice(index, 1);
                                },
                                openEdit(index) {
                                        this.editingIndex = index;
                                        const loc = this.locations[index];
                                        this.editingLocation = {
                                                name: loc.name,
                                                lat: loc.lat,
                                                lng: loc.lng,
                                                radius_km: Math.max(1, Math.round((loc.radius_meters || 1000) / 1000)),
                                        };
                                        this.showEditModal = true;
                                },
                                openNewEdit() {
                                        this.editingIndex = null;
                                        this.editingLocation = {
                                                name: this.newLocation.name,
                                                lat: this.newLocation.lat,
                                                lng: this.newLocation.lng,
                                                radius_km: 1,
                                        };
                                        this.showEditModal = true;
                                },
                                saveEdit() {
                                        const radius_meters = (this.editingLocation.radius_km || 1) * 1000;
                                        if (this.editingIndex !== null) {
                                                this.locations[this.editingIndex] = {
                                                        name: this.editingLocation.name,
                                                        lat: this.editingLocation.lat,
                                                        lng: this.editingLocation.lng,
                                                        radius_meters,
                                                };
                                                this.drawMarkers();
                                        } else {
                                                // Saving edits to the pending new location
                                                this.newLocation.name = this.editingLocation.name;
                                                this.newLocation.lat = this.editingLocation.lat;
                                                this.newLocation.lng = this.editingLocation.lng;
                                                this._pendingRadius = radius_meters;
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
                                searchPlace() {
                                        const q = this.searchQuery.trim();
                                        if (q.length < 2) {
                                                this.searchResults = [];
                                                return;
                                        }
                                        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(
                                                        q) + '&limit=6&accept-language=en')
                                                .then(res => res.json())
                                                .then(data => {
                                                        this.searchResults = data;
                                                })
                                                .catch(() => {});
                                },
                                selectResult(result) {
                                        const lat = parseFloat(result.lat);
                                        const lng = parseFloat(result.lon);
                                        this.newLocation.lat = result.lat;
                                        this.newLocation.lng = result.lon;
                                        this.newLocation.name = result.display_name.split(',')[0];
                                        this.searchQuery = '';
                                        this.searchResults = [];
                                        if (this._map) {
                                                this._map.setView([lat, lng], 13);
                                                if (this._clickMarker) this._clickMarker.remove();
                                                this._clickMarker = L.circleMarker([lat, lng], {
                                                        radius: 7,
                                                        color: '#F97316',
                                                        fillColor: '#F97316',
                                                        fillOpacity: 0.9,
                                                        weight: 2,
                                                }).addTo(this._map);
                                        }
                                },
                                async generateAiLocations() {
                                        if (this.isAiLoading || this.aiPrompt.trim() === '') return;
                                        this.isAiLoading = true;
                                        try {
                                                const res = await fetch('/ai/generate-locations', {
                                                        method: 'POST',
                                                        headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector(
                                                                        'meta[name="csrf-token"]'
                                                                        ).getAttribute(
                                                                        'content'),
                                                        },
                                                        body: JSON.stringify({
                                                                prompt: this.aiPrompt
                                                        }),
                                                });
                                                if (!res.ok) throw new Error('Request failed');
                                                const data = await res.json();
                                                data.forEach(loc => {
                                                        this.locations.push({
                                                                name: loc.name,
                                                                lat: loc.lat,
                                                                lng: loc.lng,
                                                                radius_meters: 1000
                                                        });
                                                });
                                                this.drawMarkers();
                                        } catch (e) {
                                                console.error('AI location generation failed:', e);
                                        } finally {
                                                this.aiPrompt = '';
                                                this.isAiOpen = false;
                                                this.isAiLoading = false;
                                        }
                                },
                        };
                }

                function campaignAssistant() {
                        return {
                                isOpen: false,
                                summaryOpen: false,
                                messages: [],
                                inputText: '',
                                isTyping: false,

                                getFormData() {
                                        const val = (name) => (document.querySelector(`[name="${name}"]`)?.value ?? '');
                                        let targeting = {};
                                        const targetingEl = document.querySelector('[x-data^="targetingData"]');
                                        if (targetingEl) {
                                                const td = Alpine.$data(targetingEl);
                                                targeting = {
                                                        genders: td.genders,
                                                        ages: td.ages,
                                                        incomes: td.incomes,
                                                        deviceTypes: td.deviceTypes,
                                                        os: td.os,
                                                        connectionTypes: td.connectionTypes,
                                                        environments: td.environments,
                                                        days: td.days,
                                                        countries: td.countries,
                                                        regions: td.regions,
                                                        cities: td.cities,
                                                };
                                        }
                                        let audience_ids = [];
                                        const audienceEl = document.querySelector('[x-data^="audienceManager"]');
                                        if (audienceEl) {
                                                audience_ids = Alpine.$data(audienceEl).connected.map(a => a.id).filter(Boolean);
                                        }
                                        return {
                                                name: val('name'),
                                                budget: val('budget'),
                                                expected_impressions: val('expected_impressions'),
                                                start_date: val('start_date'),
                                                end_date: val('end_date'),
                                                status: val('status'),
                                                targeting,
                                                audience_ids,
                                        };
                                },

                                applyUpdates(updates) {
                                        if (!updates) return;
                                        // Direct form fields
                                        ['name', 'budget', 'expected_impressions', 'start_date', 'end_date'].forEach(field => {
                                                if (updates[field] !== undefined) {
                                                        const el = document.querySelector(`[name="${field}"]`);
                                                        if (el && !el.disabled) {
                                                                el.value = updates[field];
                                                                el.dispatchEvent(new Event('input', {
                                                                        bubbles: true
                                                                }));
                                                                el.dispatchEvent(new Event('change', {
                                                                        bubbles: true
                                                                }));
                                                        }
                                                }
                                        });
                                        // Status via Alpine
                                        if (updates.status !== undefined) {
                                                const statusInput = document.querySelector('[name="status"]');
                                                if (statusInput) {
                                                        const root = statusInput.closest('[x-data]');
                                                        if (root) Alpine.$data(root).state = updates.status;
                                                }
                                        }
                                        // Targeting fields via Alpine
                                        const targetingEl = document.querySelector('[x-data^="targetingData"]');
                                        if (targetingEl) {
                                                const td = Alpine.$data(targetingEl);
                                                ['genders', 'ages', 'incomes', 'environments', 'days', 'countries', 'regions',
                                                        'cities'
                                                ].forEach(f => {
                                                        if (updates[f] !== undefined) td[f] = updates[f];
                                                });
                                        }
                                        // Audiences - sync AI-selected IDs directly
                                        if (updates.audience_ids !== undefined && Array.isArray(updates.audience_ids)) {
                                                const audienceEl = document.querySelector('[x-data^="audienceManager"]');
                                                if (audienceEl) {
                                                        const am = Alpine.$data(audienceEl);
                                                        fetch(`/campaigns/${am.campaignId}/audiences/sync`, {
                                                                method: 'POST',
                                                                headers: {
                                                                        'Content-Type': 'application/json',
                                                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                                                },
                                                                body: JSON.stringify({ audience_ids: updates.audience_ids }),
                                                        }).then(r => r.json()).then(data => { am.connected = data.connected; });
                                                }
                                        }
                                },

                                async sendMessage() {
                                        const text = this.inputText.trim();
                                        if (!text || this.isTyping) return;

                                        this.messages.push({
                                                role: 'user',
                                                content: text
                                        });
                                        this.inputText = '';
                                        this.isTyping = true;
                                        this.scrollToBottom();

                                        try {
                                                const res = await fetch('/ai/campaign-assistant', {
                                                        method: 'POST',
                                                        headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector(
                                                                        'meta[name="csrf-token"]'
                                                                        ).getAttribute(
                                                                        'content'),
                                                        },
                                                        body: JSON.stringify({
                                                                chatHistory: this
                                                                        .messages,
                                                                currentFormData: this
                                                                        .getFormData(),
                                                        }),
                                                });
                                                if (!res.ok) throw new Error('Request failed');
                                                const data = await res.json();
                                                this.messages.push({
                                                        role: 'ai',
                                                        content: data.reply ??
                                                                'Could not process that request.'
                                                });
                                                console.log("AI Updates Object:", data.updates);
                                                if (data.updates) this.applyUpdates(data.updates);
                                        } catch (e) {
                                                this.messages.push({
                                                        role: 'ai',
                                                        content: 'Something went wrong. Please try again.'
                                                });
                                        } finally {
                                                this.isTyping = false;
                                                this.scrollToBottom();
                                        }
                                },

                                scrollToBottom() {
                                        this.$nextTick(() => {
                                                const el = this.$refs.messagesArea;
                                                if (el) el.scrollTop = el.scrollHeight;
                                        });
                                },
                        };
                }
        </script>

</x-app-layout>
