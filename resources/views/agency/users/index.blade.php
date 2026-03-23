<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <span class="text-gray-400 whitespace-nowrap">{{ $agency->name }}</span>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">Users</span>
    </div>
@endpush

@push('page-actions')
    <a href="{{ route('agency.users.create', $agency) }}">
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
        search: '',
        filterStatus: '',
        users: @js($users->map(fn($u) => [
            'id'                 => $u->id,
            'name'               => $u->name,
            'email'              => $u->email,
            'role_name'          => $u->userRole?->name,
            'access_all_clients' => $u->pivot->access_all_clients,
            'is_active'          => $u->is_active,
            'edit_url'           => route('agency.users.edit', [$agency, $u]),
            'disable_url'        => route('agency.users.destroy', [$agency, $u]),
            'is_current'         => $u->id === auth()->id(),
        ])),
        get filtered() {
            return this.users.filter(u => {
                const q = this.search.toLowerCase();
                const matchSearch = !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
                const matchStatus = !this.filterStatus
                    || (this.filterStatus === 'active' && u.is_active)
                    || (this.filterStatus === 'disabled' && !u.is_active);
                return matchSearch && matchStatus;
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
                <input type="text" x-model="search" placeholder="Search name or email..."
                       class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
            </div>

            {{-- Status filter --}}
            <select x-model="filterStatus"
                    class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
            </select>

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
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Client Access</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-sm">
                        <template x-for="user in filtered" :key="user.id">
                            <tr class="hover:bg-gray-50 transition-colors" :class="!user.is_active ? 'opacity-50' : ''">
                                <td class="px-4 py-3">
                                    <a :href="user.edit_url"
                                       class="font-semibold text-gray-900 hover:text-[#F97316] transition-colors"
                                       x-text="user.name"></a>
                                </td>
                                <td class="px-4 py-3 text-gray-500" x-text="user.email"></td>
                                <td class="px-4 py-3">
                                    <template x-if="user.role_name">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100"
                                              x-text="user.role_name"></span>
                                    </template>
                                    <template x-if="!user.role_name">
                                        <span class="text-gray-300 text-xs">&mdash;</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3">
                                    <template x-if="user.access_all_clients">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">All</span>
                                    </template>
                                    <template x-if="!user.access_all_clients">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">Specific</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3">
                                    <template x-if="user.is_active">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">Active</span>
                                    </template>
                                    <template x-if="!user.is_active">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-600 border border-red-200">Disabled</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end">
                                        <a :href="user.edit_url"
                                           class="inline-flex items-center justify-center gap-1 text-xs font-medium text-gray-500 hover:text-[#F97316] transition-colors px-2 py-1 rounded-md hover:bg-[#F97316]/5 w-16">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/>
                                            </svg>
                                            Edit
                                        </a>
                                        <div class="w-20 flex justify-center">
                                            <template x-if="!user.is_current && user.is_active">
                                                <form :action="user.disable_url" method="POST" class="inline m-0">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <button type="button"
                                                            @click="$dispatch('confirm-action', {
                                                                title:        'Disable user?',
                                                                message:      user.name + ' will be disabled and unable to log in.',
                                                                confirmLabel: 'Disable',
                                                                form:         $el.closest('form')
                                                            })"
                                                            class="inline-flex items-center gap-1 text-xs font-medium text-red-400 hover:text-red-600 transition-colors px-2 py-1 rounded-md hover:bg-red-50 cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                        </svg>
                                                        Disable
                                                    </button>
                                                </form>
                                            </template>
                                            <template x-if="user.is_current">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">You</span>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty state --}}
                        <template x-if="filtered.length === 0">
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">
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
