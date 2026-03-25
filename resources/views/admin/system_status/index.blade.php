<x-app-layout>

@push('page-title')
    <h1 class="text-sm font-semibold text-gray-800">System Status</h1>
@endpush

    <x-flash-messages />

    {{-- System Mode Card --}}
    <x-page-box>
        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-black text-gray-900">System Mode</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Control who can access the system during maintenance or emergencies.</p>
                </div>
            </div>

            @if($adminOnlyMode)
                <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                        </svg>
                        <p class="text-sm font-semibold text-amber-800">Admin-Only Mode is active. Non-admin users cannot log in.</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('admin.system-status.toggle-admin-only') }}" method="POST">
                @csrf
                <label class="flex items-center justify-between gap-4 cursor-pointer group">
                    <div>
                        <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-500">Admin-Only Login Mode</span>
                        <p class="text-xs text-gray-400 mt-0.5 max-w-md">When enabled, only administrators can log in. Existing non-admin sessions will continue until terminated.</p>
                    </div>
                    <div class="relative shrink-0">
                        <input type="hidden" name="admin_only" value="0">
                        <input type="checkbox"
                               name="admin_only"
                               value="1"
                               @checked($adminOnlyMode)
                               @change="$el.closest('form').submit()"
                               class="peer sr-only">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer-checked:bg-[#F97316] transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                </label>
            </form>
        </div>
    </x-page-box>

    {{-- Active Sessions Card --}}
    <x-page-box class="overflow-hidden mt-6">
        {{-- Header --}}
        <div class="px-5 py-4 sm:px-6 border-b border-gray-200 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-black text-gray-900">Active Sessions</h2>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                    {{ $users->count() }}
                </span>
            </div>

            @if($users->where('is_admin', false)->count() > 0)
                <form id="terminate-all-form" action="{{ route('admin.system-status.terminate-all') }}" method="POST" class="inline">
                    @csrf
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                title:        'Terminate all non-admin sessions?',
                                message:      'All non-admin users will be logged out immediately.',
                                confirmLabel: 'Terminate All',
                                form:         document.getElementById('terminate-all-form')
                            })"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        Terminate All Non-Admin
                    </button>
                </form>
            @endif
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">User</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Browser</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">IP Address</th>
                        <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Last Activity</th>
                        <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-sm">
                    @forelse($users as $sessionUser)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span>{{ $sessionUser->name }}</span>
                                    @if($sessionUser->session_count > 1)
                                        <span class="text-xs text-gray-400">({{ $sessionUser->session_count }} sessions)</span>
                                    @endif
                                    @if($sessionUser->is_admin)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#F97316]/10 text-[#F97316] border border-[#F97316]/20">
                                            Admin
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $sessionUser->email }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $sessionUser->browser ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $sessionUser->ip_address ?? '—' }}</code>
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $sessionUser->last_activity_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($sessionUser->user_id === auth()->id())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        Current Session
                                    </span>
                                @else
                                    <form id="terminate-user-{{ $sessionUser->user_id }}"
                                          action="{{ route('admin.system-status.terminate-user', $sessionUser->user_id) }}"
                                          method="POST"
                                          class="inline">
                                        @csrf
                                        <button type="button"
                                                @click="$dispatch('confirm-action', {
                                                    title:        'Terminate session?',
                                                    message:      @js($sessionUser->name) + ' will be logged out immediately.',
                                                    confirmLabel: 'Terminate',
                                                    form:         document.getElementById('terminate-user-{{ $sessionUser->user_id }}')
                                                })"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-red-400 hover:text-red-600 transition-colors px-2 py-1 rounded-md hover:bg-red-50 cursor-pointer">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/>
                                            </svg>
                                            Terminate
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                                    </svg>
                                    <p class="text-sm text-gray-400">No active sessions found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-page-box>

</x-app-layout>
