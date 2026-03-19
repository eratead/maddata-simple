<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('users.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Users</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">{{ $user->name }}</span>
    </div>
@endpush

    <x-flash-messages />

    <form id="editUserForm" method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

            {{-- Left: User Details + Client Assignments --}}
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
                            <x-text-input id="name" name="name" type="text" required autofocus :value="old('name', $user->name)" />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email Address" />
                            <x-text-input id="email" name="email" type="email" required :value="old('email', $user->email)" />
                            <x-input-error :messages="$errors->get('email')" />
                        </div>

                        <div>
                            <div class="flex justify-between mb-1.5">
                                <x-input-label for="password" value="Password" />
                                <span class="text-xs text-gray-400 italic">Leave blank to keep current</span>
                            </div>
                            <x-text-input id="password" name="password" type="password" placeholder="••••••••••••" />
                            <x-input-error :messages="$errors->get('password')" />
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="receive_activity_notifications" class="sr-only peer" {{ $user->receive_activity_notifications ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#F97316]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#F97316] transition-colors"></div>
                                <span class="ml-3 text-sm text-gray-700">Receive Activity Notifications</span>
                            </label>
                        </div>
                    </div>
                </x-page-box>

                {{-- Client Assignments --}}
                <x-page-box class="p-6">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <h2 class="text-sm font-semibold text-gray-700">Client Assignments</h2>
                        </div>
                        <button type="button" onclick="toggleClientsPanel()"
                                class="text-xs font-semibold text-[#F97316] hover:text-[#EA580C] bg-[#F97316]/5 hover:bg-[#F97316]/10 px-3 py-1.5 rounded-md transition-colors border border-[#F97316]/20 cursor-pointer">
                            Manage Specific Clients
                        </button>
                    </div>

                    @php $attachedClientIds = $user->clients->pluck('id')->toArray(); @endphp

                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Attached Clients</p>
                        <div id="attached-clients-label"
                             class="text-sm text-gray-500 font-medium py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg min-h-[42px] leading-relaxed">
                            None
                        </div>
                    </div>

                    <div id="clients-panel" style="display: none;" class="mt-4 pt-4 border-t border-gray-100">
                        <div id="attached-clients-hidden-inputs"></div>

                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="client-filter" placeholder="Filter clients by name or agency…"
                                   class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors"
                                   oninput="filterClients(this.value)">
                        </div>

                        <div class="overflow-hidden border border-gray-200 rounded-lg max-h-80 overflow-y-auto">
                            <table id="clients-table" class="min-w-full divide-y divide-gray-100 text-sm bg-white">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Agency</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Client</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach ($clients as $client)
                                        <tr data-id="{{ $client->id }}"
                                            class="cursor-pointer hover:bg-[#F97316]/5 transition-colors group"
                                            onclick="toggleClient({{ $client->id }})">
                                            <td class="px-4 py-3 text-gray-500 group-hover:text-[#F97316] transition-colors border-l-2 border-transparent">{{ $client->agency }}</td>
                                            <td class="px-4 py-3 font-medium text-gray-800 group-hover:text-[#F97316] transition-colors">{{ $client->name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
                                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" />
                        <p class="mt-2 text-xs text-gray-400">Roles define standard operating permissions. Unassigned users only see specifically attached Clients.</p>
                    </div>
                </x-page-box>
            </div>

        </div>

        {{-- Footer --}}
        <div class="mt-6 pt-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">

            {{-- Left: destructive actions --}}
            <div class="flex items-center gap-3 w-full sm:w-auto">
                @unless (auth()->id() === $user->id)
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                title:        'Delete user?',
                                message:      '{{ addslashes($user->name) }} will be permanently removed.',
                                confirmLabel: 'Delete',
                                form:         document.getElementById('delete-form')
                            })"
                            class="text-xs font-medium text-red-400 hover:text-red-600 transition-colors cursor-pointer px-3 py-2 rounded-lg hover:bg-red-50">
                        Delete User
                    </button>
                @else
                    <span class="text-xs font-medium text-gray-400 px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 cursor-not-allowed">
                        You cannot delete your own account
                    </span>
                @endunless

                @if(auth()->user()->hasPermission('is_admin') && $user->google2fa_secret)
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                title:        'Reset 2FA?',
                                message:      '{{ addslashes($user->name) }} will be forced to re-enrol on their next login.',
                                confirmLabel: 'Reset',
                                form:         document.getElementById('reset-2fa-form')
                            })"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600 hover:text-amber-700 px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 hover:bg-amber-100 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.5 11.5 11 13l4-3.5M12 20a16.405 16.405 0 0 1-5.092-5.804A16.694 16.694 0 0 1 5 6.666L12 4l7 2.667a16.695 16.695 0 0 1-1.908 7.529A16.406 16.406 0 0 1 12 20Z"/>
                        </svg>
                        Reset 2FA
                    </button>
                @endif
            </div>

            {{-- Right: save / cancel --}}
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <a href="{{ route('users.index') }}">
                    <x-secondary-button>Cancel</x-secondary-button>
                </a>
                <x-primary-button type="submit">
                    Save Changes
                </x-primary-button>
            </div>

        </div>

    </form>

    {{-- Hidden forms --}}
    @unless (auth()->id() === $user->id)
        <form id="delete-form" action="{{ route('users.destroy', $user) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endunless

    @if(auth()->user()->hasPermission('is_admin'))
        <form id="reset-2fa-form" action="{{ route('users.reset-2fa', $user) }}" method="POST" class="hidden">
            @csrf
        </form>
    @endif

    @php $clientNames = $clients->pluck('name', 'id'); @endphp
    <script>
        const allClients = @json($clientNames);
        let attachedClientIds = @json($attachedClientIds);

        function updateClientUI() {
            const label = document.getElementById('attached-clients-label');
            const displayText = attachedClientIds.map(id => allClients[id]).filter(Boolean).join(', ');
            label.textContent = displayText || 'None Selected. Click "Manage Specific Clients" to assign.';
            if (displayText) {
                label.classList.add('bg-[#F97316]/5', 'text-[#EA580C]', 'border-[#F97316]/20');
                label.classList.remove('bg-gray-50', 'text-gray-500', 'border-gray-200');
            } else {
                label.classList.remove('bg-[#F97316]/5', 'text-[#EA580C]', 'border-[#F97316]/20');
                label.classList.add('bg-gray-50', 'text-gray-500', 'border-gray-200');
            }

            const inputContainer = document.getElementById('attached-clients-hidden-inputs');
            inputContainer.innerHTML = '';
            attachedClientIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'clients[]';
                input.value = id;
                inputContainer.appendChild(input);
            });

            document.querySelectorAll('#clients-table tbody tr').forEach(row => {
                const id = parseInt(row.dataset.id);
                const firstTd = row.querySelector('td:first-child');
                if (attachedClientIds.includes(id)) {
                    row.classList.add('bg-[#F97316]/10');
                    firstTd.classList.replace('border-transparent', 'border-[#F97316]');
                } else {
                    row.classList.remove('bg-[#F97316]/10');
                    firstTd.classList.replace('border-[#F97316]', 'border-transparent');
                }
            });
        }

        function toggleClient(id) {
            const index = attachedClientIds.indexOf(id);
            if (index === -1) attachedClientIds.push(id);
            else attachedClientIds.splice(index, 1);
            updateClientUI();
        }

        function filterClients(query) {
            query = query.toLowerCase();
            document.querySelectorAll('#clients-table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        }

        function toggleClientsPanel() {
            const panel = document.getElementById('clients-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', updateClientUI);
    </script>

</x-app-layout>
