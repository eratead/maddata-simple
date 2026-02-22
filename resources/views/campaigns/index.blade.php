<x-app-layout>
        <x-title>
                Campaigns for client :
                <select name="client_id" id="client_id" class="text-sm border rounded w-64 px-4 py-1"
                        onchange="window.location.href = '/campaigns/client/' + this.value;">
                        <option value="">All clients</option>
                        @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @if (request('client_id') == $client->id) selected @endif>
                                        {{ $client->name }}
                                </option>
                        @endforeach
                </select>
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
                {{-- @if (auth()->user()?->is_admin)
                        <x-scripts.datatables table-id="campaigns-table" :column-defs="[
                            ['targets' => 2, 'visible' => false, 'searchable' => true],
                            ['targets' => 3, 'orderable' => true, 'width' => '8rem'],
                            ['targets' => 4, 'orderable' => false, 'searchable' => false, 'width' => '8rem'],
                        ]" :order="[3, 'desc']" />
                @else
                        <x-scripts.datatables table-id="campaigns-table" :column-defs="[]" :order="[3, 'desc']" />
                @endif --}}
                <x-scripts.datatables table-id="campaigns-table" :column-defs="[['targets' => 2, 'visible' => false, 'searchable' => true]]" :order="[3, 'desc']" />
                <table id="campaigns-table" class="min-w-full bg-white shadow rounded">
                        <thead class="bg-gray-100 text-sm text-gray-600">
                                <tr>
                                        <th class="text-left px-4 py-2">Campaign</th>
                                        <th class="text-left px-4 py-2">Client</th>
                                        <th class="text-left px-4 py-2 hidden">Agency</th>
                                        <th class="text-left px-4 py-2">Start Date</th>
                                        <th class="text-left px-4 py-2">End Date</th>
                                        <th class="text-left px-4 py-2">Pacing</th>
                                        <th class="text-left px-4 py-2 ">Actions</th>
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
                                                                @if (auth()->user()->is_admin || (auth()->user()->is_report && auth()->user()->clients->contains($campaign->client_id)))
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
                                                <td class="px-4 py-2 hidden">{{ $campaign->client->agency ?? '' }}
                                                </td>
                                                <td class="px-4 py-2"
                                                        data-order="{{ \Carbon\Carbon::parse($campaign->start_date ?: $campaign->created_at)->format('Y-m-d') }}">
                                                        @php
                                                                $start = $campaign->start_date ?: $campaign->created_at;
                                                        @endphp
                                                        {{ \Carbon\Carbon::parse($start)->format('d M Y') }}
                                                </td>
                                                <td class="px-4 py-2">
                                                        {{ $campaign->end_date ? \Carbon\Carbon::parse($campaign->end_date)->format('d M Y') : '' }}
                                                </td>
                                                <td class="px-4 py-2">
                                                        @php
                                                                $p = $pacingData[$campaign->id] ?? null;
                                                        @endphp
                                                        @if ($p && !is_null($p['percent_raw']))
                                                                <div
                                                                        class="relative w-20 h-3 bg-gray-200 rounded overflow-hidden">
                                                                        <div class="absolute top-0 left-0 h-full bg-green-500"
                                                                                style="width: {{ min(100, $p['percent_raw']) }}%">
                                                                        </div>
                                                                        <span
                                                                                class="absolute inset-0 flex items-center justify-center text-[10px]  text-black">
                                                                                {{ number_format($p['percent_raw'], 0) }}%
                                                                        </span>
                                                                </div>
                                                        @else
                                                                —
                                                        @endif
                                                </td>
                                                <td class="px-4 py-2">
                                                        @can('update', $campaign)
                                                                <a href="{{ route('campaigns.edit', $campaign->id) }}"
                                                                        class="text-blue-600 hover:underline mr-2">Edit</a>
                                                        @endcan
                                                        @can('delete', $campaign)
                                                                <form action="{{ route('campaigns.destroy', $campaign->id) }}"
                                                                        method="POST" class="inline-block"
                                                                        onsubmit="return confirm('Are you sure?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit"
                                                                                class="text-red-600 hover:underline">Delete</button>
                                                                </form>
                                                        @endcan
                                                </td>
                                        </tr>
                                @endforeach
                        </tbody>
                </table>
        </x-page-box>

</x-app-layout>
