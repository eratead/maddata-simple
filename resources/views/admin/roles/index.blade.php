<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">Roles</h1>
@endpush

@push('page-actions')
    <a href="{{ route('admin.roles.create') }}">
        <x-primary-button>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Role
        </x-primary-button>
    </a>
@endpush

    <x-flash-messages />

    <x-page-box class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="w-10 px-4 py-3"></th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Users</th>
                        <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody id="roles-table-body" class="divide-y divide-gray-100 bg-white text-sm">
                    @forelse($roles as $role)
                        <tr data-id="{{ $role->id }}" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-center text-gray-300 hover:text-gray-500 transition-colors cursor-move">
                                <svg class="w-4 h-4 inline handle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                </svg>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                <a href="{{ route('admin.roles.edit', $role->id) }}"
                                   class="font-semibold text-[#F97316] hover:text-[#EA580C] transition-colors">
                                    {{ $role->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                @if($role->users_count > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                        {{ $role->users_count }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400 border border-gray-200">
                                        0
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    {{-- Edit slot --}}
                                    <a href="{{ route('admin.roles.edit', $role->id) }}"
                                       class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-[#F97316] transition-colors px-2 py-1 rounded-md hover:bg-[#F97316]/5 w-14 justify-center">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/>
                                        </svg>
                                        Edit
                                    </a>
                                    {{-- Delete slot (fixed width so rows stay aligned) --}}
                                    <div class="w-16 flex items-center justify-center">
                                        @if($role->users_count == 0)
                                            <form id="delete-role-{{ $role->id }}"
                                                  action="{{ route('admin.roles.destroy', $role->id) }}"
                                                  method="POST" class="inline m-0">
                                                @csrf @method('DELETE')
                                                <button type="button"
                                                        @click="$dispatch('confirm-action', {
                                                            title:        'Delete role?',
                                                            message:      '{{ addslashes($role->name) }} will be permanently removed.',
                                                            confirmLabel: 'Delete',
                                                            form:         $el.closest('form')
                                                        })"
                                                        class="inline-flex items-center gap-1 text-xs font-medium text-red-400 hover:text-red-600 transition-colors px-2 py-1 rounded-md hover:bg-red-50 cursor-pointer">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-300 px-2 py-1 cursor-not-allowed" title="Cannot delete a role with assigned users">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/>
                                                </svg>
                                                Delete
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-sm text-gray-400">
                                No roles have been configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page-box>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Sortable.create(document.getElementById('roles-table-body'), {
                handle: '.handle',
                animation: 150,
                ghostClass: 'opacity-50',
                onEnd: function () {
                    var order = [];
                    document.querySelectorAll('#roles-table-body tr[data-id]').forEach(function(row) {
                        order.push(row.getAttribute('data-id'));
                    });
                    fetch('{{ route("admin.roles.reorder") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ order: order })
                    });
                }
            });
        });
    </script>
    @endpush

</x-app-layout>
