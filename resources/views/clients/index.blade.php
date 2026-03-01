<x-app-layout>

    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- EMPTY BREADCRUMBS SPACER (Fixed Height) -->
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Clients
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('clients.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Client
                    </a>
                </div>
            </header>

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-100 text-green-700 rounded-xl flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            @endif

            <!-- Main Listing Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                <div class="p-4 sm:p-6">
                    <x-ui.datatable table-id="clients-table">
                        <table id="clients-table" class="min-w-full w-full">
                            <thead class="bg-gray-50/80 border-b border-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Agency
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Client Name
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            Active Campaigns
                                            <span class="flex flex-col gap-px ml-auto" aria-hidden="true">
                                                <svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z" /></svg>
                                                <svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z" /></svg>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100 bg-white">
                                @foreach ($clients as $client)
                                    <tr class="hover:bg-gray-50/50 transition-colors group">
                                        <td class="px-4 py-4 text-gray-700 font-medium whitespace-nowrap">{{ $client->agency }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <a href="{{ url('/campaigns/client/' . $client->id) }}"
                                                class="text-primary hover:text-primary-hover font-bold hover:underline transition-colors">{{ $client->name }}</a>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @if(($client->campaigns_count ?? 0) > 0)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    {{ $client->campaigns_count }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                    0
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-3 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity duration-150">
                                                <a href="{{ route('clients.edit', $client->id) }}"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary-hover transition-colors px-2 py-1 rounded-lg hover:bg-primary/5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/></svg>
                                                    Edit
                                                </a>
                                                <form action="{{ route('clients.destroy', $client->id) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this client?')"
                                                    class="inline m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/></svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-ui.datatable>
                </div>
            </div>

        </div>
    </main>

</x-app-layout>
