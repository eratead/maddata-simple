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

                        <div>
                                <label class="block text-sm font-medium text-gray-700">Budget</label>
                                <input type="number" name="budget" min="0"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

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

                        <div class="flex justify-end">
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                        Create
                                </button>
                        </div>
                </form>
        </x-page-box>
</x-app-layout>
