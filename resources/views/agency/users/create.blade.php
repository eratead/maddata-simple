<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <span class="text-gray-400 whitespace-nowrap">{{ $agency->name }}</span>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <a href="{{ route('agency.users.index', $agency) }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Users</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">Create User</span>
    </div>
@endpush

    <x-flash-messages />

    <form method="POST" action="{{ route('agency.users.store', $agency) }}"
          x-data="{
              accessMode: '{{ old('access_all_clients', '1') }}',
              selectedClients: @js(old('clients', [])),
              clients: @js($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])),
              toggleClient(id) {
                  const idx = this.selectedClients.indexOf(id);
                  if (idx === -1) this.selectedClients.push(id);
                  else this.selectedClients.splice(idx, 1);
              },
              clientFilter: '',
              get filteredClients() {
                  const q = this.clientFilter.toLowerCase();
                  return q ? this.clients.filter(c => c.name.toLowerCase().includes(q)) : this.clients;
              }
          }">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

            {{-- Left: User Details + Client Access --}}
            <div class="md:col-span-8 space-y-6">

                {{-- User Details --}}
                <x-page-box class="p-6">
                    <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <h2 class="text-sm font-semibold text-gray-700">User Details</h2>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" type="text" required autofocus :value="old('name')" />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email Address" />
                            <x-text-input id="email" name="email" type="email" required :value="old('email')" />
                            <x-input-error :messages="$errors->get('email')" />
                        </div>

                        <div>
                            <x-input-label for="password" value="Password" />
                            <x-text-input id="password" name="password" type="password" required />
                            <x-input-error :messages="$errors->get('password')" />
                        </div>
                    </div>
                </x-page-box>

                {{-- Client Access --}}
                <x-page-box class="p-6">
                    <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-100">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <h2 class="text-sm font-semibold text-gray-700">Client Access</h2>
                    </div>

                    {{-- Access mode radio --}}
                    <div class="space-y-3 mb-5">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="radio" name="access_all_clients" value="1"
                                   x-model="accessMode"
                                   class="w-4 h-4 text-[#F97316] border-gray-300 focus:ring-[#F97316]/30">
                            <div>
                                <span class="text-sm font-medium text-gray-700">All Agency Clients</span>
                                <p class="text-xs text-gray-400">User can access all current and future clients in this agency.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="radio" name="access_all_clients" value="0"
                                   x-model="accessMode"
                                   class="w-4 h-4 text-[#F97316] border-gray-300 focus:ring-[#F97316]/30">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Specific Clients Only</span>
                                <p class="text-xs text-gray-400">User can only access the clients selected below.</p>
                            </div>
                        </label>
                    </div>

                    {{-- Client picker (shown when Specific selected) --}}
                    <div x-show="accessMode === '0'" x-cloak class="mt-4 pt-4 border-t border-gray-100">
                        {{-- Hidden inputs for selected clients --}}
                        <template x-for="id in selectedClients" :key="id">
                            <input type="hidden" name="clients[]" :value="id">
                        </template>

                        {{-- Filter --}}
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" x-model="clientFilter" placeholder="Filter clients..."
                                   class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
                        </div>

                        {{-- Client checkboxes --}}
                        <div class="overflow-hidden border border-gray-200 rounded-lg max-h-64 overflow-y-auto">
                            <template x-for="client in filteredClients" :key="client.id">
                                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-[#F97316]/5 cursor-pointer transition-colors border-b border-gray-100 last:border-b-0"
                                       :class="selectedClients.includes(client.id) ? 'bg-[#F97316]/5' : ''">
                                    <input type="checkbox"
                                           :checked="selectedClients.includes(client.id)"
                                           @change="toggleClient(client.id)"
                                           class="w-4 h-4 text-[#F97316] border-gray-300 rounded focus:ring-[#F97316]/30">
                                    <span class="text-sm font-medium text-gray-700" x-text="client.name"></span>
                                </label>
                            </template>
                            <template x-if="filteredClients.length === 0">
                                <div class="px-4 py-6 text-center text-sm text-gray-400">No clients found.</div>
                            </template>
                        </div>

                        <p class="mt-2 text-xs text-gray-400">
                            <span x-text="selectedClients.length"></span> client<span x-show="selectedClients.length !== 1">s</span> selected
                        </p>
                    </div>

                    <x-input-error :messages="$errors->get('clients')" />
                </x-page-box>

            </div>

            {{-- Right: Access Control --}}
            <div class="md:col-span-4 self-start">
                <x-page-box class="p-6 bg-gray-50 sticky top-6">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-200">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <h2 class="text-sm font-semibold text-gray-700">Access Control</h2>
                    </div>

                    <div>
                        <x-input-label for="role_id" value="User Role" />
                        <select id="role_id" name="role_id"
                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" />
                        <p class="mt-2 text-xs text-gray-400">Roles define what this user can do. Management roles are not available for assignment.</p>
                    </div>
                </x-page-box>
            </div>

        </div>

        {{-- Footer --}}
        <div class="mt-6 pt-5 border-t border-gray-200 flex justify-end gap-2">
            <a href="{{ route('agency.users.index', $agency) }}">
                <x-secondary-button>Cancel</x-secondary-button>
            </a>
            <x-primary-button type="submit">
                Create User
            </x-primary-button>
        </div>

    </form>

</x-app-layout>
