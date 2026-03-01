<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- BREADCRUMBS BLOCK (Fixed Height) -->
                    <nav class="flex items-center gap-2 text-sm font-medium h-6 mb-2">
                        <a href="{{ route('clients.index') }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">Clients</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        <span class="text-gray-600">Edit Client</span>
                    </nav>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Edit Client: {{ $client->name }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('clients.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all cursor-pointer">
                        Cancel
                    </a>
                    <button type="submit" form="editClientForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Save Changes
                    </button>
                </div>
            </header>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  mb-6 group">
                <form id="editClientForm" action="{{ route('clients.update', $client) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="flex items-center gap-2 mb-6 border-b border-gray-100 pb-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <h2 class="text-base font-semibold text-gray-900">Client Details</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-1.5 md:col-span-2">
                            <label for="name" class="text-[0.85rem] font-medium text-gray-700">Client Name</label>
                            <input type="text" name="name" id="name" required
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all"
                                value="{{ old('name', $client->name) }}">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5 md:col-span-2">
                            <label for="agency" class="text-[0.85rem] font-medium text-gray-700">Agency</label>
                            <div class="w-full">
                                <x-autocomplete-input name="agency" :options="$agencies" placeholder="Select or type agency..." :value="old('agency', $client->agency)" />
                            </div>
                            @error('agency')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 mt-1">Start typing to search existing agencies or enter a new one.</p>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200/60 mt-6 flex justify-between items-center">
                        <button type="button" onclick="if(confirm('Are you sure you want to delete this client?')) document.getElementById('delete-form').submit();" 
                            class="text-red-500 hover:text-red-700 font-medium text-sm flex items-center gap-1.5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete Client
                        </button>
                    
                        <div class="flex items-center gap-3">
                            <a href="{{ route('clients.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                <form id="delete-form" action="{{ route('clients.destroy', $client) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </main>
</x-app-layout>
