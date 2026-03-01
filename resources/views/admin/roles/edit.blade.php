<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- BREADCRUMBS BLOCK -->
                    <nav class="flex items-center gap-2 text-sm font-medium h-6 mb-2">
                        <a href="{{ route('admin.roles.index') }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">Roles</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        <span class="text-gray-600 truncate max-w-[200px]">{{ $role->name }}</span>
                    </nav>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Edit Role
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all cursor-pointer">
                        Cancel
                    </a>
                    <button type="submit" form="editRoleForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Save Changes
                    </button>
                </div>
            </header>

            <form id="editRoleForm" action="{{ route('admin.roles.update', $role->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                    <!-- Right Column: Settings (Elevated priority on this page since it determines name) -->
                    <div class="md:col-span-4 lg:col-span-3 space-y-6 md:order-last">
                        <div class="bg-gray-50 rounded-xl border border-gray-200  p-4 sm:p-6  sticky top-8">
                            <div class="flex items-center gap-2 mb-4 border-b border-gray-200 pb-3">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <h2 class="text-base font-semibold text-gray-900">Role Details</h2>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="name" class="text-[0.85rem] font-medium text-gray-700">Role Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $role->name) }}" required placeholder="e.g. Account Manager"
                                    class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all shadow-sm">
                                @error('name')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Left Column: Permissions Block -->
                    <div class="md:col-span-8 lg:col-span-9 space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  group">
                            <div class="flex items-center gap-2 mb-6 border-b border-gray-100 pb-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <h2 class="text-base font-semibold text-gray-900">Global Permissions</h2>
                            </div>
                            
                            <p class="text-sm text-gray-500 mb-6">Select the administrative or operational privileges this role should grant. Any user assigned to this role will inherit these capabilities network-wide.</p>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                @foreach($permissions as $key => $label)
                                    @php
                                        $hasPermission = old("permissions.{$key}", isset($role->permissions[$key]) && $role->permissions[$key]);
                                    @endphp
                                    <label class="flex items-start p-4 border border-gray-100 rounded-lg hover:border-gray-200 hover:bg-gray-50/50 transition-colors group cursor-pointer">
                                        <div class="relative inline-flex items-center">
                                            <input type="checkbox" name="permissions[{{ $key }}]" value="1" class="sr-only peer" {{ $hasPermission ? 'checked' : '' }}>
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary transition-colors"></div>
                                        </div>
                                        <div class="ml-4 flex flex-col justify-center">
                                            <span class="text-sm font-medium text-gray-900">{{ $label }}</span>
                                            <span class="text-xs text-gray-500 mt-0.5 pointer-events-none">Allow users in this role to {{ strtolower($label) }}.</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="mt-8 pt-6 border-t border-gray-200/60 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</x-app-layout>
