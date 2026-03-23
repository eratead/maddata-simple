<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('admin.roles.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Roles</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">{{ $role->name }}</span>
    </div>
@endpush

    <x-flash-messages />

    <form id="editRoleForm" action="{{ route('admin.roles.update', $role->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

            {{-- Left: Permissions --}}
            <div class="md:col-span-8 lg:col-span-9">
                <x-page-box class="p-6">
                    <div class="flex items-center gap-2 mb-4 pb-4 border-b border-gray-100">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <h2 class="text-sm font-semibold text-gray-700">Global Permissions</h2>
                    </div>

                    <p class="text-sm text-gray-400 mb-6">Select the privileges this role should grant. Users assigned to this role will inherit these capabilities across the platform.</p>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach($permissions as $key => $label)
                            @php
                                $hasPermission = old("permissions.{$key}", isset($role->permissions[$key]) && $role->permissions[$key]);
                            @endphp
                            <label class="flex items-start p-4 border border-gray-100 rounded-lg hover:border-[#F97316]/30 hover:bg-[#F97316]/5 transition-colors cursor-pointer">
                                <div class="relative inline-flex items-center shrink-0">
                                    <input type="checkbox" name="permissions[{{ $key }}]" value="1" class="sr-only peer"
                                           {{ $hasPermission ? 'checked' : '' }}>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#F97316]/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#F97316] transition-colors"></div>
                                </div>
                                <div class="ml-4 flex flex-col justify-center">
                                    <span class="text-sm font-semibold text-gray-700">{{ $label }}</span>
                                    <span class="text-xs text-gray-400 mt-0.5">Allow users in this role to {{ strtolower($label) }}.</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </x-page-box>
            </div>

            {{-- Right: Role Details --}}
            <div class="md:col-span-4 lg:col-span-3 self-start">
                <div class="sticky top-6">
                    <x-page-box class="p-6 bg-gray-50">
                        <div class="flex items-center gap-2 mb-4 pb-3 border-b border-gray-200">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h2 class="text-sm font-semibold text-gray-700">Role Details</h2>
                        </div>

                        <div>
                            <x-input-label for="name" value="Role Name" />
                            <x-text-input id="name" name="name" type="text" required autofocus
                                          :value="old('name', $role->name)" placeholder="e.g. Account Manager" />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>

                        @if($role->users_count > 0)
                            <p class="mt-4 text-xs text-gray-400">
                                <span class="font-semibold text-gray-500">{{ $role->users_count }}</span>
                                {{ Str::plural('user', $role->users_count) }} assigned to this role.
                            </p>
                        @endif
                    </x-page-box>
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="mt-6 pt-5 border-t border-gray-200 flex justify-between items-center">
            {{-- Left: delete (only if no users assigned) --}}
            <div>
                @if($role->users_count == 0)
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                title:        'Delete role?',
                                message:      @js($role->name) + ' will be permanently removed.',
                                confirmLabel: 'Delete',
                                form:         document.getElementById('delete-role-form')
                            })"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-400 hover:text-red-600 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete Role
                    </button>
                @else
                    <span></span>
                @endif
            </div>

            {{-- Right: save / cancel --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.roles.index') }}">
                    <x-secondary-button>Cancel</x-secondary-button>
                </a>
                <x-primary-button type="submit">
                    Save Changes
                </x-primary-button>
            </div>
        </div>

    </form>

    @if($role->users_count == 0)
        <form id="delete-role-form" action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endif

</x-app-layout>
