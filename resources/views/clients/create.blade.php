<x-app-layout>
        <x-title>Create New Client</x-title>
        <x-page-box>
                <form method="POST" action="{{ route('clients.store') }}">
                        @csrf

                        <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-gray-700">Client
                                        Name</label>
                                <input type="text" name="name" id="name" required
                                        class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300"
                                        value="{{ old('name') }}">
                                @error('name')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div class="flex justify-end">
                                <a href="{{ route('clients.index') }}"
                                        class="mr-4 text-sm text-gray-600 hover:underline">Cancel</a>
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                        Create
                                </button>
                        </div>
                </form>
        </x-page-box>
</x-app-layout>
