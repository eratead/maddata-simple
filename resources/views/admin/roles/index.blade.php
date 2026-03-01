<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- RESERVED HEIGHT FOR BREADCRUMBS OR SPACER -->
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Roles Management
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Role
                    </a>
                </div>
            </header>

            <!-- Alerts -->
            @if(session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 flex items-start shadow-sm" role="alert">
                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div class="text-sm text-green-800">{{ session('success') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 flex items-start shadow-sm" role="alert">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div class="text-sm text-red-800">{{ session('error') }}</div>
                </div>
            @endif

            <!-- Main Interactive Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                <div class="w-full relative overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="w-10 bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"></th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Name</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Total Users</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100 w-24 border-l">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="roles-table-body" class="divide-y divide-gray-50">
                            @forelse($roles as $role)
                                <tr data-id="{{ $role->id }}" class="hover:bg-indigo-50/30 transition-colors group/row">
                                    <td class="px-6 py-4 text-center cursor-move text-gray-300 hover:text-gray-500 transition-colors">
                                        <svg class="w-5 h-5 inline handle" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $role->name }}</td>
                                    <td class="px-6 py-4">
                                        @if($role->users_count > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                                {{ $role->users_count }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                0
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 border-l border-gray-50">
                                        <div class="flex items-center justify-end gap-3 opacity-100 md:opacity-0 group-hover/row:opacity-100 transition-opacity">
                                            <a href="{{ route('admin.roles.edit', $role->id) }}" class="text-sm font-medium text-primary hover:text-primary-hover transition-colors">Edit</a>
                                            <div class="h-4 w-px bg-gray-200"></div>
                                            @if($role->users_count == 0)
                                                <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="inline m-0 p-0" onsubmit="return confirm('Are you sure you want to completely delete this role?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm font-medium text-red-500 hover:text-red-700 transition-colors">Delete</button>
                                                </form>
                                            @else
                                                <span class="text-sm font-medium text-gray-300 cursor-not-allowed" title="Cannot delete role with assigned users">Delete</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                            <p>No permission roles have been configured yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('roles-table-body');
            var sortable = Sortable.create(el, {
                handle: '.handle',
                animation: 150,
                ghostClass: 'bg-blue-50',
                onEnd: function (evt) {
                    // Collect new order
                    var order = [];
                    document.querySelectorAll('#roles-table-body tr[data-id]').forEach(function(row) {
                        order.push(row.getAttribute('data-id'));
                    });

                    // Send to backend
                    fetch('{{ route("admin.roles.reorder") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ order: order })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('An error occurred while reordering.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Could not save the new order.');
                    });
                }
            });
        });
    </script>
</x-app-layout>
