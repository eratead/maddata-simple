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
                        <a href="{{ route('campaigns.edit', $campaign) }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">{{ $campaign->name }}</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        <span class="text-gray-600">New Creative</span>
                    </nav>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Add Creative
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('campaigns.edit', $campaign) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all cursor-pointer">
                        Cancel
                    </a>
                    <button type="submit" form="createCreativeForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Create Creative
                    </button>
                </div>
            </header>

            @if ($errors->any())
                <div class="mb-6 bg-red-50/80 border border-red-200 text-red-600 px-4 py-3 rounded-xl flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="createCreativeForm" action="{{ route('creatives.store', $campaign) }}" method="POST">
                @csrf
                
                <!-- Core Details Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  mb-6 group">
                    <div class="flex items-center gap-2 mb-4 border-b border-gray-100 pb-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h2 class="text-base font-semibold text-gray-900">Creative Details</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-1.5 md:col-span-2">
                            <label class="text-[0.85rem] font-medium text-gray-700">Creative Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[0.85rem] font-medium text-gray-700">Landing Page URL</label>
                            <input type="url" name="landing" value="{{ old('landing') }}" required
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[0.85rem] font-medium text-gray-700">Status</label>
                            <select name="status" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all appearance-none">
                                <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Paused</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-8 bg-blue-50/50 p-4 rounded-lg flex items-start gap-3 border border-blue-100">
                        <svg class="w-5 h-5 text-primary mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-sm text-gray-600 font-medium">
                            First save this creative core. You will be able to upload media files directly after it is created.
                        </p>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-3 mt-4 pt-4 mb-8 border-t border-gray-200/60">
                    <a href="{{ route('campaigns.edit', $campaign) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Create Creative
                    </button>
                </div>

            </form>
        </div>
    </main>
</x-app-layout>
