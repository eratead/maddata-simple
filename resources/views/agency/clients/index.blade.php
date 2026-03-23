<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <span class="font-semibold text-gray-700 truncate">{{ $agency->name }}</span>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800 font-semibold truncate">Clients</span>
    </div>
@endpush

@push('page-actions')
    <a href="{{ route('agency.clients.create', $agency) }}">
        <x-primary-button>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Client
        </x-primary-button>
    </a>
@endpush

    <x-flash-messages />

    <x-page-box>
        <x-ui.datatable table-id="agency-clients-table">
            <table id="agency-clients-table" class="min-w-full w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Client Name
                                <span class="flex flex-col gap-px ml-auto">
                                    <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg>
                                    <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Campaigns
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
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ $client->name }}</span>
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
                                    <a href="{{ route('agency.clients.edit', [$agency, $client]) }}"
                                       class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-[#F97316] transition-colors px-2 py-1 rounded-md hover:bg-[#F97316]/5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/>
                                        </svg>
                                        Edit
                                    </a>
                                    @if (($client->campaigns_count ?? 0) === 0)
                                        <form id="delete-agency-client-{{ $client->id }}"
                                              action="{{ route('agency.clients.destroy', [$agency, $client]) }}"
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
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-300 px-2 py-1 cursor-not-allowed" title="Cannot delete client with campaigns">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/>
                                            </svg>
                                            Delete
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.datatable>
    </x-page-box>

</x-app-layout>
