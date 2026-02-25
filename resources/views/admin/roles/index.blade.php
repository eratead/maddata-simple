<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Roles Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-page-box>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">All Roles</h3>
                    <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                        Create New Role
                    </a>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <div class="overflow-x-auto bg-white rounded-lg shadow overflow-y-auto relative">
                    <table class="border-collapse table-auto w-full whitespace-no-wrap bg-white table-striped relative">
                        <thead>
                            <tr class="text-left">
                                <th class="bg-gray-100 sticky top-0 border-b border-gray-200 px-6 py-3 text-gray-600 font-bold tracking-wider uppercase text-xs w-10"></th>
                                <th class="bg-gray-100 sticky top-0 border-b border-gray-200 px-6 py-3 text-gray-600 font-bold tracking-wider uppercase text-xs">ID</th>
                                <th class="bg-gray-100 sticky top-0 border-b border-gray-200 px-6 py-3 text-gray-600 font-bold tracking-wider uppercase text-xs">Name</th>
                                <th class="bg-gray-100 sticky top-0 border-b border-gray-200 px-6 py-3 text-gray-600 font-bold tracking-wider uppercase text-xs">Users Count</th>
                                <th class="bg-gray-100 sticky top-0 border-b border-gray-200 px-6 py-3 text-gray-600 font-bold tracking-wider uppercase text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="roles-table-body">
                            @forelse($roles as $role)
                                <tr data-id="{{ $role->id }}" class="hover:bg-gray-50">
                                    <td class="border-dashed border-t border-gray-200 px-6 py-4 cursor-move text-gray-400 hover:text-gray-600 text-center">
                                        <svg class="w-5 h-5 inline handle" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                    </td>
                                    <td class="border-dashed border-t border-gray-200 px-6 py-4">{{ $role->id }}</td>
                                    <td class="border-dashed border-t border-gray-200 px-6 py-4 font-medium">{{ $role->name }}</td>
                                    <td class="border-dashed border-t border-gray-200 px-6 py-4">{{ $role->users_count }}</td>
                                    <td class="border-dashed border-t border-gray-200 px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('admin.roles.edit', $role->id) }}" class="text-blue-600 hover:text-blue-900">Edit</a>
                                            
                                            @if($role->users_count == 0)
                                                <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this role?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            @else
                                                <span class="text-gray-400 text-sm cursor-not-allowed" title="Cannot delete role with assigned users">Delete</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="border-dashed border-t border-gray-200 px-6 py-4 text-center text-gray-500">No roles found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
             </x-page-box>
        </div>
    </div>

    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('roles-table-body');
            var sortable = Sortable.create(el, {
                handle: '.handle',
                animation: 150,
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
                        if (data.success) {
                            // Optional: show a small toast notification here
                            console.log('Roles reordered successfully.');
                        } else {
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
