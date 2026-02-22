<x-app-layout>
    <x-title>Campaign Changes</x-title>

    <x-page-box>
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if($campaigns->isEmpty())
            <div class="text-center py-8 text-gray-500">
                No pending changes found.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs leading-normal">
                        <tr>
                            <th class="py-3 px-6 text-left">Campaign Name</th>
                            <th class="py-3 px-6 text-left">Client</th>
                            <th class="py-3 px-6 text-center">Pending Changes</th>
                            <th class="py-3 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        @foreach($campaigns as $campaign)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left whitespace-nowrap">
                                    <span class="font-medium">{{ $campaign->name }}</span>
                                </td>
                                <td class="py-3 px-6 text-left">
                                    <span>{{ $campaign->client->name }}</span>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="bg-red-100 text-red-600 py-1 px-3 rounded-full text-xs">
                                        {{ $campaign->activity_logs_count }}
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <a href="{{ route('admin.campaign_changes.show', $campaign) }}" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transform hover:scale-105 transition duration-300 ease-in-out">
                                        View Changes
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-page-box>
</x-app-layout>
