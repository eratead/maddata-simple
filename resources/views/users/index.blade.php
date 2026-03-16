<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">

            @if (session('success'))
                <div class="mb-6 px-4 py-3 bg-green-50 text-green-700 border border-green-200 rounded-lg flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 px-4 py-3 bg-red-50 text-red-700 border border-red-200 rounded-lg flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">Users</h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New User
                    </a>
                </div>
            </header>

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

                <!-- Filters -->
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <!-- Search -->
                    <div class="relative flex-1 max-w-xs">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input type="text" x-model="search" placeholder="Search name or email…"
                            class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                    </div>

                    <!-- Role filter -->
                    <select x-model="filterRole"
                        class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                        <option value="null">No role</option>
                    </select>

                    <!-- Client filter -->
                    <div class="relative">
                        <input type="text"
                            x-model="clientSearch"
                            @focus="showClientSug = true"
                            @input="showClientSug = true; if (!clientSearch) clearClient()"
                            @keydown.escape="showClientSug = false"
                            @blur="setTimeout(() => showClientSug = false, 150)"
                            placeholder="Filter by client…"
                            class="w-48 pl-3 pr-7 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                        <button x-show="filterClient" @click="clearClient()"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                            style="display:none" type="button">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <ul x-show="showClientSug && filteredClientSug.length"
                            class="absolute z-50 w-full bg-white border border-gray-200 shadow-lg rounded-md mt-1 max-h-48 overflow-y-auto"
                            style="display:none">
                            <template x-for="c in filteredClientSug" :key="c.id">
                                <li @mousedown.prevent="selectClient(c)"
                                    class="px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-primary cursor-pointer transition-colors"
                                    x-text="c.name"></li>
                            </template>
                        </ul>
                    </div>

                    <!-- Count badge -->
                    <div class="flex items-center text-sm text-gray-500 whitespace-nowrap">
                        <span x-text="filtered.length"></span>&nbsp;user<span x-show="filtered.length !== 1">s</span>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                    <div class="overflow-x-auto">
                        <table class="min-w-full w-full">
                            <thead class="bg-gray-50/80 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Clients</th>
                                    <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100 bg-white">
                                <template x-for="user in filtered" :key="user.id">
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <a :href="user.edit_url" class="text-primary hover:text-primary-hover font-medium hover:underline transition-colors" x-text="user.name"></a>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600" x-text="user.email"></td>
                                        <td class="px-6 py-4">
                                            <template x-if="user.role_name">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100" x-text="user.role_name"></span>
                                            </template>
                                            <template x-if="!user.role_name">
                                                <span class="text-gray-400 italic font-medium">None</span>
                                            </template>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 font-medium" :title="user.clients.map(c => c.name).join(', ')"
                                            x-text="user.clients.map(c => c.name).join(', ').substring(0, 25) + (user.clients.map(c => c.name).join(', ').length > 25 ? '…' : '')">
                                        </td>
                                        <td class="px-6 py-4">
                                            <template x-if="!user.is_current">
                                                <form :action="user.delete_url" method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this user?')"
                                                    class="inline-block">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <button type="submit" class="text-sm font-medium text-red-500 hover:text-red-700 transition-colors">Delete</button>
                                                </form>
                                            </template>
                                            <template x-if="user.is_current">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">You</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                                <!-- Empty state -->
                                <template x-if="filtered.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">No users match your filters.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>
</x-app-layout>
