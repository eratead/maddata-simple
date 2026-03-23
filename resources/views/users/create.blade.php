<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Users</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">Create User</span>
    </div>
@endpush

    <x-flash-messages />

    <form id="createUserForm" method="POST" action="{{ route('admin.users.store') }}"
          x-data="agencyAssignments()"
          @submit.prevent="submitForm($el)">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

            {{-- Left: User Details + Agency Assignments --}}
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

                        <div class="pt-4 border-t border-gray-100">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="receive_activity_notifications" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#F97316]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#F97316] transition-colors"></div>
                                <span class="ml-3 text-sm text-gray-700">Receive Activity Notifications</span>
                            </label>
                        </div>
                    </div>
                </x-page-box>

                {{-- Agency Assignments --}}
                <x-page-box class="p-6">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 0 0-2 2v4m5-6h8M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m0 0h3a2 2 0 0 1 2 2v4m0 0v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6m18 0s-4 2-9 2-9-2-9-2m9-2h.01"/>
                            </svg>
                            <h2 class="text-sm font-semibold text-gray-700">Agency Assignments</h2>
                        </div>
                        <div x-show="unassignedAgencies.length > 0" x-transition>
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open"
                                        class="text-xs font-semibold text-[#F97316] hover:text-[#EA580C] bg-[#F97316]/5 hover:bg-[#F97316]/10 px-3 py-1.5 rounded-md transition-colors border border-[#F97316]/20 cursor-pointer inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
                                    </svg>
                                    Add Agency
                                </button>
                                <div x-show="open" @click.away="open = false" x-transition
                                     class="absolute right-0 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-1 max-h-60 overflow-y-auto">
                                    <template x-for="agency in unassignedAgencies" :key="agency.id">
                                        <button type="button"
                                                @click="addAgency(agency.id); open = false"
                                                class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-[#F97316]/5 hover:text-[#F97316] transition-colors cursor-pointer"
                                                x-text="agency.name">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-input-error :messages="$errors->get('agencies')" />

                    {{-- Empty state --}}
                    <div x-show="agencies.length === 0" class="text-center py-8">
                        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7H5a2 2 0 0 0-2 2v4m5-6h8M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m0 0h3a2 2 0 0 1 2 2v4m0 0v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6m18 0s-4 2-9 2-9-2-9-2"/>
                        </svg>
                        <p class="text-sm text-gray-400">No agencies assigned.</p>
                        <p class="text-xs text-gray-400 mt-1">Click "Add Agency" to assign this user to an agency.</p>
                    </div>

                    {{-- Agency cards --}}
                    <div class="space-y-4">
                        <template x-for="(assignment, index) in agencies" :key="assignment.agency_id">
                            <div class="border border-gray-200 rounded-lg bg-gray-50 overflow-hidden transition-all"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0">

                                {{-- Agency header --}}
                                <div class="flex items-center justify-between px-4 py-3 bg-white border-b border-gray-100">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-md bg-[#F97316]/10 flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 text-[#F97316]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-800" x-text="assignment.name"></span>
                                    </div>
                                    <button type="button" @click="removeAgency(index)"
                                            class="text-xs font-medium text-red-400 hover:text-red-600 hover:bg-red-50 px-2 py-1 rounded transition-colors cursor-pointer inline-flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Remove
                                    </button>
                                </div>

                                {{-- Client access controls --}}
                                <div class="px-4 py-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-3">Client Access</p>

                                    <div class="flex items-center gap-6">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" :name="'agency_access_' + assignment.agency_id"
                                                   :checked="assignment.access_all_clients"
                                                   @change="assignment.access_all_clients = true"
                                                   class="w-4 h-4 text-[#F97316] border-gray-300 focus:ring-[#F97316]/30">
                                            <span class="text-sm text-gray-700">All agency clients</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" :name="'agency_access_' + assignment.agency_id"
                                                   :checked="!assignment.access_all_clients"
                                                   @change="assignment.access_all_clients = false"
                                                   class="w-4 h-4 text-[#F97316] border-gray-300 focus:ring-[#F97316]/30">
                                            <span class="text-sm text-gray-700">Specific clients</span>
                                        </label>
                                    </div>

                                    {{-- Client checkboxes (shown when "Specific clients" is selected) --}}
                                    <div x-show="!assignment.access_all_clients" x-transition class="mt-3">
                                        <div class="border border-gray-200 rounded-lg bg-white max-h-48 overflow-y-auto divide-y divide-gray-100"
                                             x-show="getClientsForAgency(assignment.agency_id).length > 0">
                                            <template x-for="client in getClientsForAgency(assignment.agency_id)" :key="client.id">
                                                <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-[#F97316]/5 transition-colors cursor-pointer">
                                                    <input type="checkbox"
                                                           :checked="assignment.clients.includes(client.id)"
                                                           @change="toggleClient(index, client.id)"
                                                           class="w-4 h-4 text-[#F97316] border-gray-300 rounded focus:ring-[#F97316]/30">
                                                    <span class="text-sm text-gray-700" x-text="client.name"></span>
                                                </label>
                                            </template>
                                        </div>
                                        <p x-show="getClientsForAgency(assignment.agency_id).length === 0"
                                           class="text-xs text-gray-400 italic mt-1">No clients in this agency yet.</p>
                                    </div>
                                </div>

                                {{-- Hidden inputs for form submission --}}
                                <input type="hidden" :name="'agencies[' + index + '][agency_id]'" :value="assignment.agency_id">
                                <input type="hidden" :name="'agencies[' + index + '][access_all_clients]'" :value="assignment.access_all_clients ? 1 : 0">
                                <template x-for="clientId in assignment.clients" :key="clientId">
                                    <input type="hidden" :name="'agencies[' + index + '][clients][]'" :value="clientId">
                                </template>
                            </div>
                        </template>
                    </div>
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
                            <option value="">No Role (Unassigned)</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" />
                        <p class="mt-2 text-xs text-gray-400">Roles define standard operating permissions. Unassigned users only see specifically attached Clients.</p>
                    </div>
                </x-page-box>
            </div>

        </div>

        {{-- Footer --}}
        <div class="mt-6 pt-5 border-t border-gray-200 flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}">
                <x-secondary-button>Cancel</x-secondary-button>
            </a>
            <x-primary-button type="submit">
                Create User
            </x-primary-button>
        </div>

    </form>

    <script>
        function agencyAssignments() {
            return {
                agencies: [],
                availableAgencies: @js($agencies->map(fn($a) => ['id' => $a->id, 'name' => $a->name])),
                allClientsByAgency: @js($clientsByAgency),

                get unassignedAgencies() {
                    return this.availableAgencies.filter(a => !this.agencies.find(aa => aa.agency_id == a.id));
                },

                addAgency(agencyId) {
                    const agency = this.availableAgencies.find(a => a.id == agencyId);
                    if (!agency) return;
                    this.agencies.push({
                        agency_id: agency.id,
                        name: agency.name,
                        access_all_clients: true,
                        clients: [],
                    });
                },

                removeAgency(index) {
                    this.agencies.splice(index, 1);
                },

                toggleClient(agencyIndex, clientId) {
                    const assignment = this.agencies[agencyIndex];
                    const idx = assignment.clients.indexOf(clientId);
                    if (idx === -1) {
                        assignment.clients.push(clientId);
                    } else {
                        assignment.clients.splice(idx, 1);
                    }
                },

                getClientsForAgency(agencyId) {
                    return this.allClientsByAgency[agencyId] || [];
                },

                submitForm(formEl) {
                    formEl.submit();
                },
            };
        }
    </script>

</x-app-layout>
