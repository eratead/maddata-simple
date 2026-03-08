<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">

            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight flex items-center gap-3">
                        Audiences
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                            {{ $audiences->count() }}
                        </span>
                    </h1>
                </div>
                <div class="flex items-center gap-3">

                    <!-- Upload Excel (Alpine modal) -->
                    <div x-data="{ open: false }">
                        <button @click="open = true"
                            class="inline-flex items-center justify-center px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg text-sm font-medium shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">
                            <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Upload Excel
                        </button>

                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                             @click.self="open = false" style="display: none;">
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md border border-gray-100">
                                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                                    <h2 class="text-base font-semibold text-gray-900">Upload Audiences Excel</h2>
                                    <button @click="open = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <form action="{{ route('admin.audiences.upload') }}" method="POST" enctype="multipart/form-data" class="px-6 py-5 space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Excel File <span class="text-red-500">*</span></label>
                                        <input type="file" name="file" accept=".xlsx,.xls" required
                                            class="w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary/10 file:text-primary hover:file:bg-primary/20 file:transition-colors border border-gray-200 rounded-lg p-1 focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary">
                                        <p class="mt-1.5 text-xs text-gray-400">Col A = Audience path, Col B = Active Unique Users.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Provider</label>
                                        <x-autocomplete-input
                                            name="provider"
                                            placeholder="e.g. Nielsen"
                                            :options="$providers->toArray()" />
                                        <p class="mt-1 text-xs text-gray-400">Applied to all imported audiences.</p>
                                    </div>
                                    <div class="flex justify-end gap-3 pt-2">
                                        <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-hover transition-colors shadow-sm">Import</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- New Audience -->
                    <button onclick="audienceModal.openCreate()"
                        class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Audience
                    </button>

                </div>
            </header>

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-100 text-green-700 rounded-xl flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-100 text-red-700 rounded-xl flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-medium">{{ $errors->first() }}</span>
                </div>
            @endif

            <!-- Filter Bar -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wide mr-1 shrink-0">Status</span>
                    <button onclick="audienceFilter.setStatus('all')" id="af-btn-all"
                        class="px-3 py-1 text-xs font-medium rounded-full border transition-all bg-primary text-white border-primary shadow-sm">All</button>
                    <button onclick="audienceFilter.setStatus('active')" id="af-btn-active"
                        class="px-3 py-1 text-xs font-medium rounded-full border transition-all bg-white text-gray-600 border-gray-200 hover:border-gray-300">Active</button>
                    <button onclick="audienceFilter.setStatus('inactive')" id="af-btn-inactive"
                        class="px-3 py-1 text-xs font-medium rounded-full border transition-all bg-white text-gray-600 border-gray-200 hover:border-gray-300">Inactive</button>
                </div>
                <div class="sm:border-l sm:border-gray-200 sm:pl-3">
                    <select id="af-category" onchange="audienceFilter.setCategory(this.value)"
                        class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-700 bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all appearance-none cursor-pointer">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Main Listing Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                <div class="p-4 sm:p-6">
                    <x-ui.datatable table-id="audiences-table">
                        <table id="audiences-table" class="min-w-full w-full">
                            <thead class="bg-gray-50/80 border-b border-gray-100">
                                <tr>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left"><div class="flex items-center justify-between gap-2">Main Category<span class="flex flex-col gap-px ml-auto" aria-hidden="true"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left"><div class="flex items-center justify-between gap-2">Sub Category<span class="flex flex-col gap-px ml-auto" aria-hidden="true"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left"><div class="flex items-center justify-between gap-2">Name<span class="flex flex-col gap-px ml-auto" aria-hidden="true"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-right"><div class="flex items-center justify-end gap-2">Est. Users<span class="flex flex-col gap-px ml-auto" aria-hidden="true"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left"><div class="flex items-center justify-between gap-2">Provider<span class="flex flex-col gap-px ml-auto" aria-hidden="true"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider sortable text-left"><div class="flex items-center justify-between gap-2">Status<span class="flex flex-col gap-px ml-auto" aria-hidden="true"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div></th>
                                    <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100 bg-white">
                                @forelse ($audiences as $audience)
                                    <tr class="hover:bg-gray-50/50 transition-colors group"
                                        data-active="{{ $audience->is_active ? 'active' : 'inactive' }}"
                                        data-category="{{ $audience->main_category }}">
                                        <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap">{{ $audience->main_category }}</td>
                                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                            @if ($audience->sub_category && $audience->sub_category !== $audience->main_category)
                                                {{ $audience->sub_category }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-900">{{ $audience->name }}</td>
                                        <td class="px-4 py-3 text-gray-500 text-right whitespace-nowrap font-mono text-xs">
                                            @if ($audience->estimated_users !== null)
                                                {{ number_format($audience->estimated_users) }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $audience->provider ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($audience->is_active)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-3">
                                                <button type="button"
                                                    onclick="audienceModal.openEdit({{ $audience->id }}, {{ json_encode($audience->main_category) }}, {{ json_encode($audience->sub_category) }}, {{ json_encode($audience->name) }}, {{ json_encode($audience->estimated_users) }}, {{ json_encode($audience->provider) }}, {{ $audience->is_active ? 'true' : 'false' }})"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary-hover transition-colors px-2 py-1 rounded-lg hover:bg-primary/5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/></svg>
                                                    Edit
                                                </button>
                                                <form action="{{ route('admin.audiences.destroy', $audience) }}" method="POST"
                                                    onsubmit="return confirm('Delete this audience?')" class="inline m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/></svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <svg class="w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                <p class="text-gray-500 font-medium">No audiences yet</p>
                                                <p class="text-sm text-gray-400 mt-1">Upload an Excel file or add one manually.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-ui.datatable>
                </div>
            </div>

        </div>
    </main>

    <!-- =========================================================
         Create / Edit Modal  — single Alpine component
         init() exposes this as window.audienceModal so plain-JS
         onclick handlers on the table rows can call it directly.
    ========================================================= -->
    <div x-data="audienceModalData()" x-cloak>

        <!-- Backdrop -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
             @click.self="close()">

            <!-- Panel -->
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-gray-100"
                 @keydown.escape.window="close()">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900" x-text="mode === 'create' ? 'New Audience' : 'Edit Audience'"></h2>
                    <button @click="close()" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Form -->
                <form method="POST" :action="formAction" class="px-6 py-5 space-y-4">
                    @csrf
                    <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">

                    <!-- Main Category + Sub Category -->
                    <div class="grid grid-cols-2 gap-4">

                        <!-- Main Category with autocomplete -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Main Category <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="main_category" x-model="form.main_category" required
                                    @focus="showSug = 'main_category'"
                                    @click.away="showSug = showSug === 'main_category' ? null : showSug"
                                    @keydown.escape="showSug = null"
                                    @keydown.enter.prevent="pickFirst('main_category')"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-gray-50/50 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all"
                                    placeholder="e.g. Sports">
                                <ul x-show="showSug === 'main_category' && filteredSugs('main_category', mainCategories).length > 0"
                                    x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute z-[70] bg-white border border-gray-200 shadow-elevated mt-1 rounded-md w-full max-h-44 overflow-y-auto">
                                    <template x-for="opt in filteredSugs('main_category', mainCategories)" :key="opt">
                                        <li @click="selectSug('main_category', opt)"
                                            class="px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-primary cursor-pointer transition-colors"
                                            x-text="opt"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <!-- Sub Category with autocomplete -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sub Category</label>
                            <div class="relative">
                                <input type="text" name="sub_category" x-model="form.sub_category"
                                    @focus="showSug = 'sub_category'"
                                    @click.away="showSug = showSug === 'sub_category' ? null : showSug"
                                    @keydown.escape="showSug = null"
                                    @keydown.enter.prevent="pickFirst('sub_category')"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-gray-50/50 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all"
                                    placeholder="Blank = same as main">
                                <ul x-show="showSug === 'sub_category' && filteredSugs('sub_category', subCategories).length > 0"
                                    x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute z-[70] bg-white border border-gray-200 shadow-elevated mt-1 rounded-md w-full max-h-44 overflow-y-auto">
                                    <template x-for="opt in filteredSugs('sub_category', subCategories)" :key="opt">
                                        <li @click="selectSug('sub_category', opt)"
                                            class="px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-primary cursor-pointer transition-colors"
                                            x-text="opt"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-gray-50/50 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all"
                            placeholder="e.g. Basketball Fans">
                    </div>

                    <!-- Est. Users + Provider -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Est. Users</label>
                            <input type="number" name="estimated_users" x-model="form.estimated_users" min="0"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-gray-50/50 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all"
                                placeholder="e.g. 150000">
                        </div>

                        <!-- Provider with autocomplete -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Provider</label>
                            <div class="relative">
                                <input type="text" name="provider" x-model="form.provider"
                                    @focus="showSug = 'provider'"
                                    @click.away="showSug = showSug === 'provider' ? null : showSug"
                                    @keydown.escape="showSug = null"
                                    @keydown.enter.prevent="pickFirst('provider')"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-gray-50/50 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all"
                                    placeholder="e.g. Nielsen">
                                <ul x-show="showSug === 'provider' && filteredSugs('provider', providers).length > 0"
                                    x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute z-[70] bg-white border border-gray-200 shadow-elevated mt-1 rounded-md w-full max-h-44 overflow-y-auto">
                                    <template x-for="opt in filteredSugs('provider', providers)" :key="opt">
                                        <li @click="selectSug('provider', opt)"
                                            class="px-3 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-primary cursor-pointer transition-colors"
                                            x-text="opt"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Active toggle -->
                    <div class="flex items-center gap-3">
                        <button type="button" @click="form.is_active = !form.is_active"
                            :class="form.is_active ? 'bg-emerald-500' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30">
                            <span :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"></span>
                        </button>
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <label class="text-sm font-medium text-gray-700 cursor-pointer select-none" @click="form.is_active = !form.is_active">
                            <span x-text="form.is_active ? 'Active' : 'Inactive'"></span>
                        </label>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="close()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-hover transition-colors shadow-sm"
                            x-text="mode === 'create' ? 'Create' : 'Save Changes'"></button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function audienceModalData() {
        return {
            open: false,
            mode: 'create',
            audienceId: null,
            showSug: null,
            form: {
                main_category: '',
                sub_category: '',
                name: '',
                estimated_users: '',
                provider: '',
                is_active: true,
            },
            mainCategories: @json($categories),
            subCategories: @json($subCategories),
            providers: @json($providers),

            get formAction() {
                const base = '{{ url('admin/audiences') }}';
                return this.mode === 'create' ? base : base + '/' + this.audienceId;
            },

            filteredSugs(field, list) {
                const q = (this.form[field] || '').toLowerCase();
                return list.filter(o => !q || o.toLowerCase().includes(q));
            },

            selectSug(field, val) {
                this.form[field] = val;
                this.showSug = null;
            },

            pickFirst(field) {
                const list = field === 'main_category' ? this.mainCategories
                           : field === 'sub_category'  ? this.subCategories
                           : this.providers;
                const matches = this.filteredSugs(field, list);
                if (matches.length > 0) this.selectSug(field, matches[0]);
            },

            openCreate() {
                this.mode = 'create';
                this.audienceId = null;
                this.form = { main_category: '', sub_category: '', name: '', estimated_users: '', provider: '', is_active: true };
                this.showSug = null;
                this.open = true;
                this.$nextTick(() => this.$el.querySelector('input[name="main_category"]')?.focus());
            },

            openEdit(id, mc, sc, n, eu, p, active) {
                this.mode = 'edit';
                this.audienceId = id;
                this.form = {
                    main_category:   mc || '',
                    sub_category:    (sc && sc !== mc) ? sc : '',
                    name:            n  || '',
                    estimated_users: eu ?? '',
                    provider:        p  || '',
                    is_active:       active,
                };
                this.showSug = null;
                this.open = true;
                this.$nextTick(() => this.$el.querySelector('input[name="main_category"]')?.focus());
            },

            close() {
                this.open = false;
                this.showSug = null;
            },

            // Expose to plain-JS onclick handlers on table rows
            init() {
                window.audienceModal = this;
            },
        };
    }

    // Datatable external filter (status pills + category dropdown)
    const audienceFilter = (() => {
        let statusFilter = 'all';
        let categoryFilter = '';
        let masterRows = null;

        function init() {
            const dt = window.MadDataTables?.['audiences-table'];
            if (!dt) return;
            masterRows = dt.originalRows.map(r => ({ ...r }));
        }

        function apply() {
            const dt = window.MadDataTables?.['audiences-table'];
            if (!dt || !masterRows) return;
            dt.originalRows = masterRows.filter(row => {
                const tr = row.tr;
                const activeMatch = statusFilter === 'all' || tr.dataset.active === statusFilter;
                const catMatch = !categoryFilter || tr.dataset.category === categoryFilter;
                return activeMatch && catMatch;
            });
            dt.state.query = dt.searchInput.value.trim().toLowerCase();
            dt.state.page = 1;
            dt.updateData();
        }

        function setStatus(val) {
            statusFilter = val;
            const styles = {
                all:      'bg-primary text-white border-primary shadow-sm',
                active:   'bg-emerald-600 text-white border-emerald-600 shadow-sm',
                inactive: 'bg-gray-500 text-white border-gray-500 shadow-sm',
            };
            const off = 'bg-white text-gray-600 border-gray-200 hover:border-gray-300';
            ['all', 'active', 'inactive'].forEach(s => {
                const btn = document.getElementById('af-btn-' + s);
                if (btn) btn.className = `px-3 py-1 text-xs font-medium rounded-full border transition-all ${s === val ? styles[s] : off}`;
            });
            apply();
        }

        function setCategory(val) {
            categoryFilter = val;
            apply();
        }

        document.addEventListener('DOMContentLoaded', () => setTimeout(init, 100));
        return { setStatus, setCategory };
    })();
    </script>
    @endpush
</x-app-layout>
