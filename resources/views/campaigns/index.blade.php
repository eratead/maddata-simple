<x-app-layout>
        <x-title>
                Campaigns
                @isset($clientName)
                        for {{ $clientName }}
                @endisset
        </x-title>
        <x-page-box>
                @if (session('success'))
                        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                                {{ session('success') }}
                        </div>
                @endif
                @if (auth()->user()?->is_admin)
                        <div class="flex justify-end mb-4">
                                <a href="{{ route('campaigns.create') }}"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                                        + New Campaign
                                </a>
                        </div>
                @endif
                {{-- <x-scripts.datatables table-id="campaigns-table" /> --}}
                @if (auth()->user()?->is_admin)
                        <x-scripts.datatables table-id="campaigns-table" :column-defs="[
                            ['targets' => 2, 'width' => '8rem'],
                            ['targets' => 3, 'orderable' => false, 'width' => '8rem'],
                        ]" :order="[[2, 'desc']]" />
                @else
                        <x-scripts.datatables table-id="campaigns-table" :column-defs="[['targets' => 2, 'width' => '8rem']]" :order="[[2, 'desc']]" />
                @endif
                <table id="campaigns-table" class="min-w-full bg-white shadow rounded">
                        <thead class="bg-gray-100 text-sm text-gray-600">
                                <tr>
                                        <th class="text-left px-4 py-2">Campaign</th>
                                        <th class="text-left px-4 py-2">Client</th>
                                        <th class="text-left px-4 py-2">Create Date</th>
                                        @if (auth()->user()?->is_admin)
                                                <th class="text-left px-4 py-2">Actions</th>
                                        @endif
                                </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                                @foreach ($campaigns as $campaign)
                                        <tr>
                                                <td class="px-4 py-2">
                                                        <a href="{{ route('dashboard.campaign', $campaign->id) }}"
                                                                class="text-blue-600 hover:underline">
                                                                {{ $campaign->name }}
                                                        </a>
                                                        @auth
                                                                @if (auth()->user()->is_admin)
                                                                        <form id="upload-form-{{ $campaign->id }}"
                                                                                action="{{ route('campaigns.upload', $campaign->id) }}"
                                                                                method="POST" enctype="multipart/form-data"
                                                                                class="mt-2 flex items-center gap-2">
                                                                                @csrf
                                                                                <label
                                                                                        class="inline-flex items-center cursor-pointer text-white bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded text-sm">
                                                                                        Upload Report
                                                                                        <input type="file" name="report"
                                                                                                required
                                                                                                onchange="this.form.submit();"
                                                                                                class="hidden" />
                                                                                </label>
                                                                        </form>
                                                                @endif
                                                        @endauth
                                                </td>
                                                <td class="px-4 py-2">{{ $campaign->client->name ?? '—' }}</td>
                                                <td class="px-4 py-2">{{ $campaign->created_at->format('Y-m-d') }}</td>
                                                @if (auth()->user()?->is_admin)
                                                        <td class="px-4 py-2">
                                                                <a href="{{ route('campaigns.edit', $campaign->id) }}"
                                                                        class="text-blue-600 hover:underline mr-2">Edit</a>
                                                                <form action="{{ route('campaigns.destroy', $campaign->id) }}"
                                                                        method="POST" class="inline-block"
                                                                        onsubmit="return confirm('Are you sure?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit"
                                                                                class="text-red-600 hover:underline">Delete</button>
                                                                </form>
                                                        </td>
                                                @endif
                                        </tr>
                                @endforeach
                        </tbody>
                </table>
        </x-page-box>
</x-app-layout>
