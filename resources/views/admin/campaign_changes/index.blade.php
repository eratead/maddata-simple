<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Campaign Changes</h1>
@endpush

    <x-flash-messages />

    <x-page-box class="overflow-hidden">
        @if($campaigns->isEmpty())
            <div class="px-4 py-12 text-center text-sm text-gray-400">
                No campaigns with pending changes.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Campaign</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Client</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-500">Pending</th>
                            <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-sm">
                        @foreach($campaigns as $campaign)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $campaign->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $campaign->client->name }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-100">
                                        {{ $campaign->activity_logs_count }} pending
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.campaign_changes.show', $campaign) }}"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-[#F97316] hover:text-[#EA580C] transition-colors px-2 py-1 rounded-md hover:bg-[#F97316]/5">
                                        Review
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-page-box>

    @if ($campaigns->hasPages())
        <div class="mt-4 flex justify-end">
            {{ $campaigns->links() }}
        </div>
    @endif

</x-app-layout>
