<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- BREADCRUMBS BLOCK -->
                    <nav class="flex items-center gap-2 text-sm font-medium h-6 mb-2">
                        <a href="{{ route('users.index') }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">Users</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        <span class="text-gray-600 truncate max-w-[200px]">{{ $user->name }}</span>
                    </nav>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Edit User
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all cursor-pointer">
                        Cancel
                    </a>
                    <button type="submit" form="editUserForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Save Changes
                    </button>
                </div>
            </header>

            <form id="editUserForm" method="POST" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <!-- Left Column: User Details -->
                    <div class="md:col-span-8 space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  group">
                            <div class="flex items-center gap-2 mb-6 border-b border-gray-100 pb-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <h2 class="text-base font-semibold text-gray-900">User Details</h2>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex flex-col gap-1.5 md:col-span-2">
                                    <label for="name" class="text-[0.85rem] font-medium text-gray-700">Name</label>
                                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                    @error('name')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-1.5 md:col-span-2">
                                    <label for="email" class="text-[0.85rem] font-medium text-gray-700">Email Address</label>
                                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                                    @error('email')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-1.5 md:col-span-2">
                                    <label for="password" class="text-[0.85rem] font-medium text-gray-700 flex justify-between">
                                        Password
                                        <span class="text-gray-400 font-normal italic">Leave blank to keep current</span>
                                    </label>
                                    <input type="password" id="password" name="password" placeholder="••••••••••••"
                                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all placeholder:text-gray-300">
                                    @error('password')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div class="flex flex-col gap-1.5 md:col-span-2 mt-2 pt-4 border-t border-gray-100">
                                    <label class="relative inline-flex items-center cursor-pointer group">
                                        <input type="checkbox" name="receive_activity_notifications" class="sr-only peer" {{ $user->receive_activity_notifications ? 'checked' : '' }}>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary transition-colors"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 group-hover:text-gray-900 transition-colors">Receive Activity Notifications</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Client Assignments Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  group">
                            <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <h2 class="text-base font-semibold text-gray-900">Client Assignments</h2>
                                </div>
                                <button type="button" onclick="toggleClientsPanel()" class="text-xs font-medium text-primary hover:text-primary-hover bg-primary/5 hover:bg-primary/10 px-3 py-1.5 rounded-md transition-colors border border-primary/20">
                                    Manage Specific Clients
                                </button>
                            </div>

                            @php
                                $attachedClientIds = $user->clients->pluck('id')->toArray();
                            @endphp

                            <!-- Currently Assigned Summary -->
                            <div class="mb-4">
                                <p class="text-sm text-gray-700 mb-2">Attached Clients:</p>
                                <div id="attached-clients-label" class="text-sm text-gray-500 font-medium py-2 px-3 bg-gray-50 border border-gray-200 rounded-md min-h-[42px] leading-relaxed">
                                    None
                                </div>
                            </div>

                            <!-- Interactive Assign Panel -->
                            <div id="clients-panel" style="display: none;" class="mt-4 pt-4 border-t border-gray-100">
                                <div id="attached-clients-hidden-inputs"></div>
                                
                                <div class="relative mb-4">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input type="text" id="client-filter" placeholder="Filter clients by name or agency..."
                                        class="w-full pl-10 pr-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all shadow-sm"
                                        oninput="filterClients(this.value)">
                                </div>
                                
                                <div class="overflow-hidden border border-gray-200 rounded-lg max-h-80 overflow-y-auto shadow-inner bg-gray-50/50">
                                    <table id="clients-table" class="min-w-full divide-y divide-gray-200 text-sm bg-white">
                                        <thead class="bg-gray-50/80 sticky top-0 z-10">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Agency</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($clients as $client)
                                                <tr data-id="{{ $client->id }}" class="cursor-pointer hover:bg-indigo-50/60 transition-colors group" onclick="toggleClient({{ $client->id }})">
                                                    <td class="px-4 py-3 text-gray-500 group-hover:text-primary transition-colors border-l-2 border-transparent">{{ $client->agency }}</td>
                                                    <td class="px-4 py-3 font-medium text-gray-900 group-hover:text-primary transition-colors">{{ $client->name }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Role & Rules -->
                    <div class="md:col-span-4 space-y-6">
                        <div class="bg-gray-50 rounded-xl border border-gray-200  p-4 sm:p-6  sticky top-8">
                            <div class="flex items-center gap-2 mb-4 border-b border-gray-200 pb-3">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <h2 class="text-base font-semibold text-gray-900">Access Control</h2>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="role_id" class="text-[0.85rem] font-medium text-gray-700">User Role</label>
                                <select id="role_id" name="role_id" class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all shadow-sm">
                                    <option value="">No Role (Unassigned)</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('role_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-500 mt-2">Roles define standard operating permissions like modifying campaigns or downloading reports. Unassigned users only see specifically attached Clients.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="mt-8 pt-6 border-t border-gray-200/60 flex flex-col-reverse md:flex-row justify-between items-center gap-4">
                    @unless (auth()->id() === $user->id)
                        <button type="button" onclick="if(confirm('Are you sure you want to completely delete this user? This cannot be undone.')) document.getElementById('delete-form').submit();" class="text-sm font-medium text-red-500 hover:text-red-700 transition-colors w-full md:w-auto text-left md:text-center px-4 md:px-0 py-2 md:py-0 rounded-md hover:bg-red-50 md:hover:bg-transparent">
                            Delete User
                        </button>
                    @else
                        <span class="text-sm font-medium text-gray-400 px-4 py-2 border border-gray-200 rounded-md bg-gray-50 cursor-not-allowed w-full md:w-auto text-center" title="You cannot delete yourself">
                            You cannot delete your own account
                        </span>
                    @endunless

                    <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                        <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                            Save Changes
                        </button>
                    </div>
                </div>

            </form>
            
            @unless (auth()->id() === $user->id)
                <form id="delete-form" action="{{ route('users.destroy', $user) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endunless
        </div>
    </main>

    @php
        $clientNames = $clients->pluck('name', 'id');
    @endphp
    <script>
        const allClients = @json($clientNames);
        let attachedClientIds = @json($attachedClientIds);

        function updateClientUI() {
            // Update label
            const label = document.getElementById('attached-clients-label');
            const displayText = attachedClientIds.map(id => allClients[id]).filter(Boolean).join(', ');
            label.textContent = displayText || 'None Selected. Click "Manage Specific Clients" to assign.';
            if(displayText) {
                label.classList.add('bg-blue-50', 'text-blue-800', 'border-blue-200');
                label.classList.remove('bg-gray-50', 'text-gray-500', 'border-gray-200');
            } else {
                label.classList.remove('bg-blue-50', 'text-blue-800', 'border-blue-200');
                label.classList.add('bg-gray-50', 'text-gray-500', 'border-gray-200');
            }

            // Update hidden inputs
            const inputContainer = document.getElementById('attached-clients-hidden-inputs');
            inputContainer.innerHTML = '';
            attachedClientIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'clients[]';
                input.value = id;
                inputContainer.appendChild(input);
            });

            // Update row highlighting entirely dynamically without overriding tailwind hover classes
            document.querySelectorAll('#clients-table tbody tr').forEach(row => {
                const id = parseInt(row.dataset.id);
                const firstTd = row.querySelector('td:first-child');
                
                if(attachedClientIds.includes(id)) {
                    // Selected state
                    row.classList.add('bg-blue-50/80');
                    firstTd.classList.replace('border-transparent', 'border-primary');
                } else {
                    // Unselected state
                    row.classList.remove('bg-blue-50/80');
                    firstTd.classList.replace('border-primary', 'border-transparent');
                }
            });
        }

        function toggleClient(id) {
            const index = attachedClientIds.indexOf(id);
            if (index === -1) {
                attachedClientIds.push(id);
            } else {
                attachedClientIds.splice(index, 1);
            }
            updateClientUI();
        }

        function filterClients(query) {
            query = query.toLowerCase();
            document.querySelectorAll('#clients-table tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function toggleClientsPanel() {
            const panel = document.getElementById('clients-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', updateClientUI);
    </script>
</x-app-layout>
