<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('clients.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Clients</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">Create Client</span>
    </div>
@endpush


    <x-page-box class="p-6">

        {{-- Section heading --}}
        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Client Details</h2>
        </div>

        <form id="createClientForm" method="POST" action="{{ route('clients.store') }}" class="space-y-5">
            @csrf

            {{-- Client Name --}}
            <div>
                <x-input-label for="name" value="Client Name" />
                <x-text-input id="name" name="name" type="text" required autofocus
                              :value="old('name')" placeholder="e.g. Bank Leumi" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            {{-- Agency --}}
            <div>
                <x-input-label for="agency" value="Agency" />
                <x-autocomplete-input name="agency" :options="$agencies"
                                      placeholder="Select or type agency…"
                                      :value="old('agency', '')" />
                <x-input-error :messages="$errors->get('agency')" />
                <p class="mt-1.5 text-xs text-gray-400">Start typing to search existing agencies or enter a new one.</p>
            </div>

            {{-- Footer --}}
            <div class="pt-5 mt-2 border-t border-gray-100 flex justify-end gap-2">
                <a href="{{ route('clients.index') }}">
                    <x-secondary-button>Cancel</x-secondary-button>
                </a>
                <x-primary-button type="submit">
                    Create Client
                </x-primary-button>
            </div>

        </form>

    </x-page-box>

</x-app-layout>
