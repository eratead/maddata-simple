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
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Budget</label>
                                <input type="number" name="budget" min="0"
                                        value="{{ old('budget', $campaign->budget) }}"
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                        </div>

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
