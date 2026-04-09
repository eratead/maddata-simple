<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800 truncate">Clients</h1>
@endpush

@push('page-actions')
    @can('create', App\Models\Client::class)
        <a href="{{ route('admin.clients.create') }}">
            <x-primary-button>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Client
            </x-primary-button>
        </a>
    @endcan
@endpush

    <x-flash-messages />

    <x-page-box>
        <x-ui.datatable table-id="clients-table">
            <x-slot:filters>
                <select @change="window.location.href = $el.value ? '{{ route('admin.clients.index') }}?agency=' + $el.value : '{{ route('admin.clients.index') }}'"
                        class="pl-3 pr-8 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer appearance-none"
                        style="background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E');background-position:right 0.25rem center;background-repeat:no-repeat;background-size:20px;">
                    <option value="">All Agencies</option>
                    @foreach($agencies as $agency)
                        <option value="{{ $agency->id }}" {{ $currentAgency?->id == $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
                    @endforeach
                </select>
                @if($currentAgency)
                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $clients->count() }} {{ Str::plural('client', $clients->count()) }}</span>
                    <a href="{{ route('admin.clients.index') }}" class="text-xs text-[#F97316] hover:text-[#EA580C] font-medium whitespace-nowrap">Clear</a>
                @endif
            </x-slot:filters>
            <table id="clients-table" class="min-w-full w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Agency
                                <span class="flex flex-col gap-px ml-auto">
                                    <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Client Name
                                <span class="flex flex-col gap-px ml-auto">
                                    <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Active Campaigns
                                <span class="flex flex-col gap-px ml-auto">
                                    <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-sm">
                    @foreach ($clients as $client)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $client->agency?->name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ url('/campaigns/client/' . $client->id) }}"
                                   class="font-semibold text-gray-900 hover:text-[#F97316] transition-colors">
                                    {{ $client->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (($client->campaigns_count ?? 0) > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ $client->campaigns_count }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400 border border-gray-200">
                                        0
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.clients.edit', $client->id) }}"
                                       class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-[#F97316] transition-colors px-2 py-1 rounded-md hover:bg-[#F97316]/5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/>
                                        </svg>
                                        Edit
                                    </a>
                                    @can('delete', $client)
                                        <form id="delete-client-{{ $client->id }}"
                                              action="{{ route('admin.clients.destroy', $client->id) }}"
                                              method="POST" class="inline m-0">
                                            @csrf @method('DELETE')
                                            <button type="button"
                                                    @click="$dispatch('confirm-action', {
                                                        title:        'Delete client?',
                                                        message:      @js($client->name) + ' will be permanently removed.',
                                                        confirmLabel: 'Delete',
                                                        form:         $el.closest('form')
                                                    })"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-red-400 hover:text-red-600 transition-colors px-2 py-1 rounded-md hover:bg-red-50 cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.datatable>
    </x-page-box>

</x-app-layout>
