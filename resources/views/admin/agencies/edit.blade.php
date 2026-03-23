<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('admin.agencies.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Agencies</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">{{ $agency->name }}</span>
    </div>
@endpush

    <x-flash-messages />

    <x-page-box class="p-6">

        {{-- Section heading --}}
        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Agency Details</h2>
        </div>

        <form id="editAgencyForm" action="{{ route('admin.agencies.update', $agency) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            {{-- Agency Name --}}
            <div>
                <x-input-label for="name" value="Agency Name" />
                <x-text-input id="name" name="name" type="text" required autofocus
                              :value="old('name', $agency->name)" placeholder="e.g. Publicis Media" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            {{-- Clients count (read-only info) --}}
            @if ($agency->clients_count > 0)
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span>{{ $agency->clients_count }} {{ Str::plural('client', $agency->clients_count) }} assigned to this agency</span>
                </div>
            @endif

            {{-- Footer --}}
            <div class="pt-5 mt-2 border-t border-gray-100 flex justify-between items-center">

                {{-- Delete (left) --}}
                @if ($agency->clients_count === 0)
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                title:        'Delete agency?',
                                message:      @js($agency->name) + ' will be permanently removed.',
                                confirmLabel: 'Delete',
                                form:         document.getElementById('delete-agency-form')
                            })"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-400 hover:text-red-600 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete Agency
                    </button>
                @else
                    <span></span>
                @endif

                {{-- Save / Cancel (right) --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.agencies.index') }}">
                        <x-secondary-button>Cancel</x-secondary-button>
                    </a>
                    <x-primary-button type="submit">
                        Save Changes
                    </x-primary-button>
                </div>

            </div>
        </form>

        {{-- Hidden delete form --}}
        @if ($agency->clients_count === 0)
            <form id="delete-agency-form" action="{{ route('admin.agencies.destroy', $agency) }}" method="POST" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endif

    </x-page-box>

</x-app-layout>
