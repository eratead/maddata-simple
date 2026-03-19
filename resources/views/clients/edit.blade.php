<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('clients.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Clients</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">{{ $client->name }}</span>
    </div>
@endpush


    <x-flash-messages />

    <x-page-box class="p-6">

        {{-- Section heading --}}
        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Client Details</h2>
        </div>

        <form id="editClientForm" action="{{ route('clients.update', $client) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            {{-- Client Name --}}
            <div>
                <x-input-label for="name" value="Client Name" />
                <x-text-input id="name" name="name" type="text" required autofocus
                              :value="old('name', $client->name)" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            {{-- Agency --}}
            <div>
                <x-input-label for="agency" value="Agency" />
                <x-autocomplete-input name="agency" :options="$agencies"
                                      placeholder="Select or type agency…"
                                      :value="old('agency', $client->agency)" />
                <x-input-error :messages="$errors->get('agency')" />
                <p class="mt-1.5 text-xs text-gray-400">Start typing to search existing agencies or enter a new one.</p>
            </div>

            {{-- Footer --}}
            <div class="pt-5 mt-2 border-t border-gray-100 flex justify-between items-center">

                {{-- Delete (left) --}}
                @can('delete', $client)
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                title:        'Delete client?',
                                message:      '{{ addslashes($client->name) }} will be permanently removed.',
                                confirmLabel: 'Delete',
                                form:         document.getElementById('delete-client-form')
                            })"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-400 hover:text-red-600 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete Client
                    </button>
                @else
                    <span></span>
                @endcan

                {{-- Save / Cancel (right) --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('clients.index') }}">
                        <x-secondary-button>Cancel</x-secondary-button>
                    </a>
                    <x-primary-button type="submit">
                        Save Changes
                    </x-primary-button>
                </div>

            </div>
        </form>

        {{-- Hidden delete form --}}
        @can('delete', $client)
            <form id="delete-client-form" action="{{ route('clients.destroy', $client) }}" method="POST" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endcan

    </x-page-box>

</x-app-layout>
