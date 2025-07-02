<x-app-layout>
        <x-title>Create New User</x-title>
        <x-page-box>
                <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
                        @csrf

                        <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name') }}"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('name')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('email')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <input type="password" id="password" name="password"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('password')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div>
                                <label for="clients" class="block text-sm font-medium text-gray-700">Assign to
                                        Clients</label>
                                <select name="clients[]" id="clients" multiple
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                        @foreach ($clients as $client)
                                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                </select>
                                @error('clients')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div>
                                <label class="inline-flex items-center">
                                        <input type="checkbox" name="is_admin" class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Administrator</span>
                                </label>
                        </div>
                        <div>
                                <label class="inline-flex items-center">
                                        <input type="checkbox" name="is_report" class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Reports upload</span>
                                </label>
                        </div>
                        <div>
                                <label class="inline-flex items-center">
                                        <input type="checkbox" name="can_view_budget" class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Can View Budget</span>
                                </label>
                        </div>

                        <div>
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        Create User
                                </button>
                                <a href="{{ route('users.index') }}"
                                        class="ml-3 text-gray-600 hover:underline">Cancel</a>
                        </div>
                </form>
        </x-page-box>
</x-app-layout>
