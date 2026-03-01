<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto flex flex-col h-full">
            
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <!-- Breadcrumbs -->
                    <nav class="flex items-center text-[0.8rem] text-gray-400 mb-2 mt-4 md:mt-0 font-medium tracking-wide">
                        <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <a href="{{ route('admin.campaign_changes.index') }}" class="hover:text-primary transition-colors">Campaign Changes</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <span class="text-gray-600">Overview</span>
                    </nav>

                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Campaign Changes</h1>
                    <p class="text-sm text-gray-500 mt-2">Manage pending alterations waiting for approval across tracked campaigns.</p>
                </div>
            </div>

            <!-- Alerts -->
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50/80 border border-green-200 text-green-700 rounded-xl flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            <!-- Main Content Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all duration-300 group overflow-hidden">
                @if($campaigns->isEmpty())
                    <div class="p-12 text-center flex flex-col items-center justify-center">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">No Pending Changes</h3>
                        <p class="text-sm text-gray-500 max-w-sm">There are no outstanding modifications logged against any tracked campaigns at this time.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Campaign Name</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                                    <th scope="col" class="px-6 py-4 text-center text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Pending Changes</th>
                                    <th scope="col" class="px-6 py-4 text-right text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($campaigns as $campaign)
                                    <tr class="hover:bg-indigo-50/30 transition-colors group/row">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $campaign->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ $campaign->client->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                                {{ $campaign->activity_logs_count }} pending
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('admin.campaign_changes.show', $campaign) }}" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow-md transition-all group/btn focus:ring-2 focus:ring-primary/20">
                                                <span class="group-hover/btn:-translate-x-0.5 transition-transform">Review</span>
                                                <svg class="w-4 h-4 ml-2 group-hover/btn:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            
        </div>
    </main>
</x-app-layout>
