<x-app-layout>
        <x-title>Edit Client</x-title>
        <x-page-box>

                <form action="{{ route('clients.update', $client) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Client Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $client->name) }}"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('name')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                        </div>

                        <div>
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
                                <a href="{{ route('clients.index') }}"
                                        class="ml-3 text-gray-600 hover:text-gray-800 underline">Cancel</a>
                        </div>
                </form>
        </x-page-box>
</x-app-layout>
