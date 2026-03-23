<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('admin.agencies.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Agencies</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">Create Agency</span>
    </div>
@endpush

    <x-page-box class="p-6">

        {{-- Section heading --}}
        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
            {{-- Flowbite: general/briefcase --}}
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 0 0-2 2v4m5-6h8M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m0 0h3a2 2 0 0 1 2 2v4m0 0v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6m18 0s-4 2-9 2-9-2-9-2m9-2h.01"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-700">Agency Details</h2>
        </div>

        <form id="createAgencyForm" method="POST" action="{{ route('admin.agencies.store') }}" class="space-y-5"
              x-data="{ showManager: {{ old('manager_name') ? 'true' : 'false' }} }">
            @csrf

            {{-- Agency Name --}}
            <div>
                <x-input-label for="name" value="Agency Name" />
                <x-text-input id="name" name="name" type="text" required autofocus
                              :value="old('name')" placeholder="e.g. Publicis Media" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            {{-- Initial Agency Manager (optional, collapsible) --}}
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <button type="button"
                        @click="showManager = !showManager"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer">
                    <div class="flex items-center gap-2">
                        {{-- Flowbite: user/user-plus --}}
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-700">Initial Agency Manager</span>
                        <span class="text-xs text-gray-400">(optional)</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 shrink-0"
                         :class="showManager ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="showManager" x-collapse x-cloak class="px-4 py-4 space-y-4 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        Create an initial manager account that will have full access to this agency's users and clients.
                    </p>

                    {{-- Manager Name --}}
                    <div>
                        <x-input-label for="manager_name" value="Manager Name" />
                        <x-text-input id="manager_name" name="manager_name" type="text"
                                      :value="old('manager_name')" placeholder="e.g. John Smith" />
                        <x-input-error :messages="$errors->get('manager_name')" />
                    </div>

                    {{-- Manager Email --}}
                    <div>
                        <x-input-label for="manager_email" value="Manager Email" />
                        <x-text-input id="manager_email" name="manager_email" type="email"
                                      :value="old('manager_email')" placeholder="e.g. john@agency.com" />
                        <x-input-error :messages="$errors->get('manager_email')" />
                    </div>

                    {{-- Manager Password --}}
                    <div>
                        <x-input-label for="manager_password" value="Manager Password" />
                        <x-text-input id="manager_password" name="manager_password" type="password"
                                      placeholder="Min 8 chars, mixed case + number" />
                        <x-input-error :messages="$errors->get('manager_password')" />
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="pt-5 mt-2 border-t border-gray-100 flex justify-end gap-2">
                <a href="{{ route('admin.agencies.index') }}">
                    <x-secondary-button>Cancel</x-secondary-button>
                </a>
                <x-primary-button type="submit">
                    Create Agency
                </x-primary-button>
            </div>

        </form>

    </x-page-box>

</x-app-layout>
