<x-app-layout>
    <x-title>
        <div class="flex items-center gap-4">
            <span>Add Creative</span>
            <a href="{{ route('campaigns.edit', $campaign) }}" class="flex items-center gap-1 hover:underline" style="color: #E85E26">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span class="text-lg">{{ $campaign->name }}</span>
            </a>
        </div>
    </x-title>
    <x-page-box>
        <form action="{{ route('creatives.store', $campaign) }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Landing Page URL</label>
                <input type="url" name="landing" value="{{ old('landing') }}" required
                    class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                    <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Paused</option>
                </select>
            </div>
            
            <div class="mb-6">
                <p class="text-sm text-gray-500 italic">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    You can add files after creating the creative.
                </p>
            </div>

            <div class="text-right">
                <a href="{{ route('campaigns.edit', $campaign) }}" class="text-gray-600 hover:text-gray-800 mr-4">Cancel</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                    Create Creative
                </button>
            </div>
        </form>
    </x-page-box>
</x-app-layout>
