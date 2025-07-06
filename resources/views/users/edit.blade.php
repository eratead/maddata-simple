<x-app-layout>
        <x-title>Edit User</x-title>
        <x-page-box>
                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('name')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" id="email" name="email"
                                        value="{{ old('email', $user->email) }}"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('email')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password
                                        <small>(leave blank to keep current)</small></label>
                                <input type="password" id="password" name="password"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                @error('password')
                                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                        </div>

                        @php
                                $attachedClientIds = $user->clients->pluck('id')->toArray();
                        @endphp

                        <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 cursor-pointer"
                                        onclick="toggleClientsPanel()">Clients</label>
                                <div id="attached-clients-label" class="mt-1 text-sm text-gray-900"></div>
                        </div>

                        <div id="clients-panel" style="display: none;">
                                <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Attach/Detach
                                                Clients</label>
                                        <div id="attached-clients-hidden-inputs"></div>
                                        <input type="text" id="client-filter" placeholder="Filter clients..."
                                                class="mb-2 w-full px-3 py-2 border rounded text-sm shadow-sm"
                                                oninput="filterClients(this.value)">
                                        <table id="clients-table"
                                                class="min-w-full divide-y divide-gray-200 border rounded text-sm">
                                                <thead class="bg-gray-50">
                                                        <tr>
                                                                <th class="px-4 py-2 text-left">Agency</th>
                                                                <th class="px-4 py-2 text-left">Client</th>
                                                        </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                        @foreach ($clients as $client)
                                                                <tr data-id="{{ $client->id }}"
                                                                        class="cursor-pointer hover:bg-blue-50"
                                                                        onclick="toggleClient({{ $client->id }})">
                                                                        <td class="px-4 py-2">{{ $client->agency }}</td>
                                                                        <td class="px-4 py-2">{{ $client->name }}</td>
                                                                </tr>
                                                        @endforeach
                                                </tbody>
                                        </table>
                                </div>
                        </div>

                        <div>
                                <label class="inline-flex items-center">
                                        <input type="checkbox" name="is_admin" {{ $user->is_admin ? 'checked' : '' }}
                                                class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Administrator</span>
                                </label>
                        </div>
                        <div>
                                <label class="inline-flex items-center">
                                        <input type="checkbox" name="is_report" {{ $user->is_report ? 'checked' : '' }}
                                                class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Reports upload</span>
                                </label>
                        </div>
                        <div>
                                <label class="inline-flex items-center">
                                        <input type="checkbox" name="can_view_budget"
                                                {{ $user->can_view_budget ? 'checked' : '' }}
                                                class="rounded border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Can View Budget</span>
                                </label>
                        </div>

                        <div>
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        Save Changes
                                </button>
                                <a href="{{ route('users.index') }}"
                                        class="ml-3 text-gray-600 hover:underline">Cancel</a>
                        </div>

                        @php
                                $clientNames = $clients->pluck('name', 'id');
                        @endphp
                        <script>
                                const allClients = @json($clientNames);
                                let attachedClientIds = @json($attachedClientIds);

                                function updateClientUI() {
                                        // Update label
                                        const label = document.getElementById('attached-clients-label');
                                        label.textContent = attachedClientIds.map(id => allClients[id]).filter(Boolean).join(', ');

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

                                        // Update row highlighting
                                        document.querySelectorAll('#clients-table tbody tr').forEach(row => {
                                                const id = parseInt(row.dataset.id);
                                                row.classList.toggle('bg-blue-100', attachedClientIds.includes(id));
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
                </form>
        </x-page-box>
</x-app-layout>
