<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Users</h1>
@endpush

@push('page-actions')
    <a href="{{ route('admin.users.create') }}">
        <x-primary-button>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New User
        </x-primary-button>
    </a>
@endpush

    <x-flash-messages />

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.users.index') }}"
          class="flex flex-col sm:flex-row gap-2 mb-4"
          x-data="{
              clientSearch: '{{ addslashes(request('client_name', '')) }}',
              showClientSug: false,
              clientsList: @js($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()),
              get filteredClientSug() {
                  const q = this.clientSearch.toLowerCase();
                  return q ? this.clientsList.filter(c => c.name.toLowerCase().includes(q)) : this.clientsList;
              },
              selectClient(c) {
                  this.clientSearch = c.name;
                  this.$refs.clientIdInput.value = c.id;
                  this.showClientSug = false;
              },
              clearClient() {
                  this.clientSearch = '';
                  this.$refs.clientIdInput.value = '';
              },
          }">

        {{-- Search --}}
        <div class="relative flex-1 max-w-xs">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email…"
                   class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
        </div>

        {{-- Role filter --}}
        <select name="role"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer">
            <option value="">All roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" {{ request('role') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
            <option value="null" {{ request('role') === 'null' ? 'selected' : '' }}>No role</option>
        </select>

        {{-- Agency filter --}}
        <select name="agency"
                class="pl-3 pr-8 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors cursor-pointer appearance-none"
                style="background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%208l5%205%205-5%22%20stroke%3D%22%236b7280%22%20stroke-width%3D%221.5%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E');background-position:right 0.25rem center;background-repeat:no-repeat;background-size:20px;">
            <option value="">All Agencies</option>
            @foreach ($agencies as $agency)
                <option value="{{ $agency->id }}" {{ request('agency') == $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
            @endforeach
        </select>

        {{-- Client filter (autocomplete) --}}
        <div class="relative">
            <input type="hidden" name="client" value="{{ request('client') }}" x-ref="clientIdInput">
            <input type="text"
                   x-model="clientSearch"
                   @focus="showClientSug = true"
                   @input="showClientSug = true; if (!clientSearch) clearClient()"
                   @keydown.escape="showClientSug = false"
                   @blur="setTimeout(() => showClientSug = false, 150)"
                   placeholder="Filter by client…"
                   class="w-48 pl-3 pr-7 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 placeholder-gray-400 shadow-sm focus:outline-none focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/30 transition-colors">
            <button x-show="clientSearch" @click="clearClient()" type="button"
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

        {{-- Submit / Reset --}}
        <button type="submit"
                class="px-4 py-2 bg-[#F97316] text-white text-sm font-semibold rounded-lg hover:bg-[#EA580C] transition-colors shadow-sm">
            Filter
        </button>
        @if(request()->hasAny(['search', 'role', 'agency', 'client']))
            <a href="{{ route('admin.users.index') }}"
               class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm whitespace-nowrap">
                Clear
            </a>
        @endif

        {{-- Count --}}
        <div class="flex items-center text-xs font-medium text-gray-400 whitespace-nowrap px-1">
            {{ $users->total() }}&nbsp;user{{ $users->total() !== 1 ? 's' : '' }}
        </div>

    </form>

    {{-- Table --}}
    <x-page-box class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Role</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Agency</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Clients</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-sm">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="font-semibold text-gray-900 hover:text-[#F97316] transition-colors">
                                    {{ $user->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @if ($user->userRole)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                        {{ $user->userRole->name }}
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ $user->agencies->pluck('name')->join(', ') ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs"
                                title="{{ $user->clients->pluck('name')->join(', ') }}">
                                {{ Str::limit($user->clients->pluck('name')->join(', '), 30) ?: '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->id !== auth()->id())
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline-block">
                                        @csrf @method('DELETE')
                                        <button type="button"
                                                @click="$dispatch('confirm-action', {
                                                    title:        'Delete user?',
                                                    message:      @js($user->name) + ' will be permanently removed.',
                                                    confirmLabel: 'Delete',
                                                    form:         $el.closest('form')
                                                })"
                                                class="text-xs font-medium text-red-400 hover:text-red-600 transition-colors cursor-pointer">
                                            Delete
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">You</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">
                                No users match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page-box>

    @if ($users->hasPages())
        <div class="mt-4 flex justify-end">
            {{ $users->links() }}
        </div>
    @endif

</x-app-layout>
