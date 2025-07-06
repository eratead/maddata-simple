<x-app-layout>
        <x-title>Users</x-title>
        <x-page-box>
                @if (session('success'))
                        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                                {{ session('success') }}
                        </div>
                @endif

                @if (session('error'))
                        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                                {{ session('error') }}
                        </div>
                @endif

                <div class="flex justify-end mb-4">
                        <a href="{{ route('users.create') }}"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                + New User
                        </a>
                </div>

                <table class="min-w-full bg-white shadow rounded">
                        <thead class="bg-gray-100 text-sm text-gray-600">
                                <tr>
                                        <th class="text-left px-4 py-2">Name</th>
                                        <th class="text-left px-4 py-2">Email</th>
                                        <th class="text-left px-4 py-2">Role</th>
                                        <th class="text-left px-4 py-2">Clients</th>
                                        <th class="text-left px-4 py-2">Actions</th>
                                </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                                @foreach ($users as $user)
                                        <tr>
                                                <td class="px-4 py-2">
                                                        <a href="{{ route('users.edit', $user) }}"
                                                                class="text-blue-600 hover:underline">
                                                                {{ $user->name }}
                                                        </a>
                                                </td>
                                                <td class="px-4 py-2">{{ $user->email }}</td>
                                                <td class="px-4 py-2">
                                                        {{ collect([
                                                            $user->is_admin ? 'Admin' : null,
                                                            $user->can_view_budget ? 'Budget' : null,
                                                            $user->is_report ? 'Reports' : null,
                                                        ])->filter()->join(', ') }}
                                                </td>
                                                <td class="px-4 py-2">{{ $user->clients->pluck('name')->join(', ') }}
                                                </td>
                                                <td class="px-4 py-2">
                                                        @unless (auth()->id() === $user->id)
                                                                <form action="{{ route('users.destroy', $user) }}"
                                                                        method="POST"
                                                                        onsubmit="return confirm('Are you sure?')"
                                                                        class="inline-block">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit"
                                                                                class="text-red-600 hover:underline">
                                                                                Delete
                                                                        </button>
                                                                </form>
                                                        @else
                                                                <span class="text-gray-400 italic">You</span>
                                                        @endunless
                                                </td>
                                        </tr>
                                @endforeach
                        </tbody>
                </table>
        </x-page-box>
</x-app-layout>
