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
                    <!-- EMPTY BREADCRUMBS SPACER (Fixed Height) -->
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Users
                    </h1>
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
                                @foreach ($users as $user)
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="px-6 py-4">
                                                        <a href="{{ route('users.edit', $user) }}" class="text-primary hover:text-primary-hover font-medium hover:underline transition-colors">
                                                                {{ $user->name }}
                                                        </a>
                                                </td>
                                                <td class="px-6 py-4 text-gray-600">{{ $user->email }}</td>
                                                <td class="px-6 py-4">
                                                        @if($user->userRole)
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                                                {{ $user->userRole->name }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400 italic font-medium">None</span>
                                                        @endif
                                                </td>
                                                <td class="px-6 py-4 text-gray-600 font-medium"
                                                        title="{{ $user->clients->pluck('name')->join(', ') }}">
                                                        {{ \Illuminate\Support\Str::limit($user->clients->pluck('name')->join(', '), 25) }}
                                                </td>
                                                <td class="px-6 py-4">
                                                        @unless (auth()->id() === $user->id)
                                                                <form action="{{ route('users.destroy', $user) }}"
                                                                        method="POST"
                                                                        onsubmit="return confirm('Are you sure you want to delete this user?')"
                                                                        class="inline-block">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit"
                                                                                class="text-sm font-medium text-red-500 hover:text-red-700 transition-colors">
                                                                                Delete
                                                                        </button>
                                                                </form>
                                                        @else
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                                    You
                                                                </span>
                                                        @endunless
                                                </td>
                                        </tr>
                                @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</x-app-layout>
