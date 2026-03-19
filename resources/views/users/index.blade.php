<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Users</h1>
@endpush

@push('page-actions')
    <a href="{{ route('users.create') }}">
        <x-primary-button>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New User
        </x-primary-button>
    </a>
@endpush

    <x-flash-messages />

    <div x-data="{
        users: @js($users->map(fn($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'role_id'    => $u->role_id,
            'role_name'  => $u->userRole?->name,
            'clients'    => $u->clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'edit_url'   => route('users.edit', $u),
            'delete_url' => route('users.destroy', $u),
            'is_current' => $u->id === auth()->id(),
        ])),
        search: '',
        filterRole: '',
        filterClient: '',
        clientSearch: '',
        showClientSug: false,
        clientsList: @js($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()),
        get filteredClientSug() {
            const q = this.clientSearch.toLowerCase();
            return q ? this.clientsList.filter(c => c.name.toLowerCase().includes(q)) : this.clientsList;
        },
        selectClient(c) {
            this.filterClient = String(c.id);
            this.clientSearch = c.name;
            this.showClientSug = false;
        },
        clearClient() {
            this.filterClient = '';
            this.clientSearch = '';
        },
        get filtered() {
            return this.users.filter(u => {
                const q = this.search.toLowerCase();
                const matchSearch = !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
                const matchRole   = !this.filterRole   || String(u.role_id) === this.filterRole;
                const matchClient = !this.filterClient || u.clients.some(c => String(c.id) === this.filterClient);
                return matchSearch && matchRole && matchClient;
            });
        }
    }">

        {{-- Filters --}}
        <div class="flex flex-col sm:flex-row gap-2 mb-4">

            {{-- Search --}}
            <div class="relative flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Search name or email…"
                       class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
            </div>

            {{-- Role filter --}}
            <select x-model="filterRole"
                    class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
                <option value="null">No role</option>
            </select>

            {{-- Client filter --}}
            <div class="relative">
                <input type="text"
                       x-model="clientSearch"
                       @focus="showClientSug = true"
                       @input="showClientSug = true; if (!clientSearch) clearClient()"
                       @keydown.escape="showClientSug = false"
                       @blur="setTimeout(() => showClientSug = false, 150)"
                       placeholder="Filter by client…"
                       class="w-48 pl-3 pr-7 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
                <button x-show="filterClient" @click="clearClient()" type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                        style="display:none">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <ul x-show="showClientSug && filteredClientSug.length"
                    class="absolute z-50 w-full bg-white border border-gray-200 shadow-lg rounded-lg mt-1 max-h-48 overflow-y-auto"
                    style="display:none">
                    <template x-for="c in filteredClientSug" :key="c.id">
                        <li @mousedown.prevent="selectClient(c)"
                            class="px-3 py-2 text-sm text-gray-700 hover:bg-[#F97316]/5 hover:text-[#F97316] cursor-pointer transition-colors"
                            x-text="c.name"></li>
                    </template>
                </ul>
            </div>

            {{-- Count --}}
            <div class="flex items-center text-xs font-medium text-gray-400 whitespace-nowrap px-1">
                <span x-text="filtered.length"></span>&nbsp;user<span x-show="filtered.length !== 1">s</span>
            </div>
        </div>

        {{-- Table --}}
        <x-page-box class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Email</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Role</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Clients</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-sm">
                        <template x-for="user in filtered" :key="user.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    <a :href="user.edit_url"
                                       class="font-semibold text-[#F97316] hover:text-[#EA580C] transition-colors"
                                       x-text="user.name"></a>
                                </td>
                                <td class="px-4 py-3 text-gray-500" x-text="user.email"></td>
                                <td class="px-4 py-3">
                                    <template x-if="user.role_name">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100"
                                              x-text="user.role_name"></span>
                                    </template>
                                    <template x-if="!user.role_name">
                                        <span class="text-gray-300 text-xs">—</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs"
                                    :title="user.clients.map(c => c.name).join(', ')"
                                    x-text="user.clients.map(c => c.name).join(', ').substring(0, 30) + (user.clients.map(c => c.name).join(', ').length > 30 ? '…' : '')">
                                </td>
                                <td class="px-4 py-3">
                                    <template x-if="!user.is_current">
                                        <form :action="user.delete_url" method="POST" class="inline-block">
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button type="button"
                                                    @click="$dispatch('confirm-action', {
                                                        title:        'Delete user?',
                                                        message:      user.name + ' will be permanently removed.',
                                                        confirmLabel: 'Delete',
                                                        form:         $el.closest('form')
                                                    })"
                                                    class="text-xs font-medium text-red-400 hover:text-red-600 transition-colors cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    </template>
                                    <template x-if="user.is_current">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">You</span>
                                    </template>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty state --}}
                        <template x-if="filtered.length === 0">
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">
                                    No users match your filters.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </x-page-box>

    </div>

</x-app-layout>
