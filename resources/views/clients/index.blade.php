<x-app-layout>

        <x-title>Clients</x-title>
        <x-page-box>
                <div class="flex justify-end mb-4">
                        <a href="{{ route('clients.create') }}"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                + New Client
                        </a>
                </div>
                <x-scripts.datatables table-id="clients-table" :column-defs="[['targets' => 1, 'width' => '5rem', 'orderable' => false]]" :order="[[0, 'asc']]" />

                <table id="clients-table" class="min-w-full bg-white shadow rounded">
                        <thead class="bg-gray-100 text-sm text-gray-600">
                                <tr>
                                        <th class="text-left px-4 py-2">Name</th>
                                        <th class="text-left px-4 py-2">Actions</th>
                                </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                                @foreach ($clients as $client)
                                        <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2">
                                                        <a href="{{ url('/campaigns/client/' . $client->id) }}"
                                                                class="text-blue-600 hover:underline">{{ $client->name }}</a>
                                                </td>
                                                <td class="px-4 py-2 space-x-2">
                                                        <a href="{{ route('clients.edit', $client->id) }}"
                                                                class="text-sm text-blue-600 hover:underline">Edit</a>

                                                        <form action="{{ route('clients.destroy', $client->id) }}"
                                                                method="POST"
                                                                onsubmit="return confirm('Are you sure?')"
                                                                class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                        class="text-sm text-red-600 hover:underline">Delete</button>
                                                        </form>
                                                </td>
                                        </tr>
                                @endforeach
                        </tbody>
                </table>
        </x-page-box>


</x-app-layout>
