<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2">
        <h1 class="text-sm font-semibold text-gray-800">Audiences</h1>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F97316]/10 text-[#F97316] border border-[#F97316]/20">
            {{ $audiences->total() }}
        </span>
    </div>
@endpush

@push('page-actions')

    {{-- Upload Excel --}}
    <div x-data="{ open: false }">
        <x-secondary-button @click="open = true">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span class="hidden sm:inline">Upload Excel</span>
        </x-secondary-button>

        {{-- Upload modal --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
             @click.self="open = false" style="display:none">
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-md"
                 @keydown.escape.window="open = false" style="display:none">

                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Upload Audiences Excel</h2>
                    <button @click="open = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('admin.audiences.upload') }}" method="POST" enctype="multipart/form-data" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <x-input-label value="Excel File" />
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                               class="w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#F97316]/10 file:text-[#F97316] hover:file:bg-[#F97316]/20 file:transition-colors border border-gray-300 rounded-lg p-1 focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316]">
                        <p class="mt-1.5 text-xs text-gray-400">Supports two formats: (A) Category + Segment Name + Users, or (B) Full path + Users.</p>
                    </div>
                    <div>
                        <x-input-label value="Provider" />
                        <x-autocomplete-input name="provider" placeholder="e.g. Nielsen" :options="$providers->toArray()" />
                        <p class="mt-1 text-xs text-gray-400">Applied to all imported audiences.</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-secondary-button type="button" @click="open = false">Cancel</x-secondary-button>
                        <x-primary-button type="submit">Import</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- New Audience --}}
    <x-primary-button onclick="audienceModal.openCreate()">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Audience</span>
    </x-primary-button>

@endpush

    <x-flash-messages />

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-100 text-red-700 rounded-lg flex items-start gap-3">
            <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm font-medium">{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- Filter bar --}}
    <x-page-box class="p-3 mb-4 flex flex-col sm:flex-row items-start sm:items-center gap-3">
        <form method="GET" action="{{ route('admin.audiences.index') }}"
              class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full">

            <div class="flex items-center gap-2">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 shrink-0">Status</span>
                @php $currentStatus = request('status', ''); @endphp
                <a href="{{ route('admin.audiences.index', array_merge(request()->query(), ['status' => ''])) }}"
                   class="px-3 py-1 text-xs font-semibold rounded-full border transition-all {{ $currentStatus === '' ? 'bg-[#F97316] text-white border-[#F97316] shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">All</a>
                <a href="{{ route('admin.audiences.index', array_merge(request()->query(), ['status' => 'active'])) }}"
                   class="px-3 py-1 text-xs font-semibold rounded-full border transition-all {{ $currentStatus === 'active' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">Active</a>
                <a href="{{ route('admin.audiences.index', array_merge(request()->query(), ['status' => 'inactive'])) }}"
                   class="px-3 py-1 text-xs font-semibold rounded-full border transition-all {{ $currentStatus === 'inactive' ? 'bg-gray-500 text-white border-gray-500 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">Inactive</a>
            </div>

            <div class="sm:border-l sm:border-gray-200 sm:pl-3">
                <select name="category" onchange="this.form.submit()"
                        class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors cursor-pointer">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" {{ request('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                    @endforeach
                </select>
                {{-- Preserve status filter when submitting category --}}
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
            </div>

        </form>
    </x-page-box>

    {{-- Hidden batch-delete form (outside table to avoid nested forms) --}}
    <form id="batch-delete-form" action="{{ route('admin.audiences.batch-delete') }}" method="POST" style="display:none">
        @csrf
    </form>

    {{-- Table --}}
    <div x-data="{ selected: [] }"
         @change="selected = [...document.querySelectorAll('.aud-check:checked')].map(el => el.value)">

        {{-- Batch delete bar --}}
        <div x-show="selected.length > 0" x-cloak
             class="mb-3 px-4 py-2.5 bg-red-50 border border-red-100 rounded-lg flex items-center justify-between">
            <span class="text-sm text-red-700 font-medium" x-text="selected.length + ' audience(s) selected'"></span>
            <button type="button"
                    @click="
                        const form = document.getElementById('batch-delete-form');
                        form.querySelectorAll('input[name=\'ids[]\']').forEach(el => el.remove());
                        selected.forEach(id => { const inp = document.createElement('input'); inp.type='hidden'; inp.name='ids[]'; inp.value=id; form.appendChild(inp); });
                        $dispatch('confirm-action', {
                            title: 'Delete selected audiences?',
                            message: selected.length + ' audiences will be permanently removed.',
                            confirmLabel: 'Delete All',
                            form: form
                        })
                    "
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/></svg>
                Delete Selected
            </button>
        </div>

    <x-page-box class="overflow-hidden">
        <x-ui.datatable table-id="audiences-table">
            <table id="audiences-table" class="min-w-full w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-3 w-10">
                            <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/20 cursor-pointer"
                                   @click="if($el.checked) { document.querySelectorAll('.aud-check').forEach(c => c.checked = true) } else { document.querySelectorAll('.aud-check').forEach(c => c.checked = false) }; selected = [...document.querySelectorAll('.aud-check:checked')].map(el => el.value)">
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Main Category<span class="flex flex-col gap-px ml-auto"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Sub Category<span class="flex flex-col gap-px ml-auto"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Name<span class="flex flex-col gap-px ml-auto"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-right sortable">
                            <div class="flex items-center justify-end gap-2">Est. Users<span class="flex flex-col gap-px ml-auto"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Provider<span class="flex flex-col gap-px ml-auto"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-left sortable">
                            <div class="flex items-center gap-2">Status<span class="flex flex-col gap-px ml-auto"><svg class="w-2.5 h-2.5 sort-icon-asc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0L9.33 6H.67z"/></svg><svg class="w-2.5 h-2.5 sort-icon-desc" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6L.67 0H9.33z"/></svg></span></div>
                        </th>
                        <th class="px-4 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 bg-white">
                    @forelse ($audiences as $audience)
                        <tr class="hover:bg-gray-50 transition-colors"
                            data-active="{{ $audience->is_active ? 'active' : 'inactive' }}"
                            data-category="{{ $audience->main_category }}">
                            <td class="px-3 py-3 w-10">
                                <input type="checkbox" value="{{ $audience->id }}" class="aud-check w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/20 cursor-pointer">
                            </td>
                            <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap">{{ $audience->main_category }}</td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                @if ($audience->sub_category && $audience->sub_category !== $audience->main_category)
                                    {{ $audience->sub_category }}
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $audience->name }}</td>
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
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400 border border-gray-200">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button"
                                            onclick="audienceModal.openEdit({{ $audience->id }}, {{ json_encode($audience->main_category) }}, {{ json_encode($audience->sub_category) }}, {{ json_encode($audience->name) }}, {{ json_encode($audience->estimated_users) }}, {{ json_encode($audience->provider) }}, {{ $audience->is_active ? 'true' : 'false' }})"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-[#F97316] transition-colors px-2 py-1 rounded-md hover:bg-[#F97316]/5 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.293-6.293a1 1 0 011.414 0l1.586 1.586a1 1 0 010 1.414L12 13.5 9 15l.5-2.5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14"/></svg>
                                        Edit
                                    </button>
                                    <form id="del-aud-{{ $audience->id }}" action="{{ route('admin.audiences.destroy', $audience) }}" method="POST" class="inline m-0">
                                        @csrf @method('DELETE')
                                        <button type="button"
                                                @click="$dispatch('confirm-action', {
                                                    title:        'Delete audience?',
                                                    message:      @js($audience->name) + ' will be permanently removed.',
                                                    confirmLabel: 'Delete',
                                                    form:         $el.closest('form')
                                                })"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-red-400 hover:text-red-600 transition-colors px-2 py-1 rounded-md hover:bg-red-50 cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/></svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">
                                No audiences yet. Upload an Excel file or add one manually.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.datatable>
    </x-page-box>
    </div>

    @if ($audiences->hasPages())
        <div class="mt-4 flex justify-end">
            {{ $audiences->links() }}
        </div>
    @endif

    {{-- =========================================================
         Create / Edit Modal
    ========================================================= --}}
    <div x-data="audienceModalData()" x-cloak>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
             @click.self="close()" style="display:none">

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-lg"
                 @keydown.escape.window="close()" style="display:none">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700" x-text="mode === 'create' ? 'New Audience' : 'Edit Audience'"></h2>
                    <button @click="close()" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Form --}}
                <form method="POST" :action="formAction" class="px-6 py-5 space-y-4">
                    @csrf
                    <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">

                    {{-- Main + Sub Category --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Main Category" /><span class="text-red-400 text-xs ml-0.5">*</span>
                            <div class="relative">
                                <input type="text" name="main_category" x-model="form.main_category" required
                                       @focus="showSug = 'main_category'"
                                       @click.away="showSug = showSug === 'main_category' ? null : showSug"
                                       @keydown.escape="showSug = null"
                                       @keydown.enter.prevent="pickFirst('main_category')"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors"
                                       placeholder="e.g. Sports">
                                <ul x-show="showSug === 'main_category' && filteredSugs('main_category', mainCategories).length > 0"
                                    x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute z-[70] bg-white border border-gray-200 shadow-lg mt-1 rounded-lg w-full max-h-44 overflow-y-auto" style="display:none">
                                    <template x-for="opt in filteredSugs('main_category', mainCategories)" :key="opt">
                                        <li @mousedown.prevent="selectSug('main_category', opt)"
                                            class="px-3 py-2 text-sm text-gray-700 hover:bg-[#F97316]/5 hover:text-[#F97316] cursor-pointer transition-colors"
                                            x-text="opt"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <x-input-label value="Sub Category" />
                            <div class="relative">
                                <input type="text" name="sub_category" x-model="form.sub_category"
                                       @focus="showSug = 'sub_category'"
                                       @click.away="showSug = showSug === 'sub_category' ? null : showSug"
                                       @keydown.escape="showSug = null"
                                       @keydown.enter.prevent="pickFirst('sub_category')"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors"
                                       placeholder="Blank = same as main">
                                <ul x-show="showSug === 'sub_category' && filteredSugs('sub_category', subCategories).length > 0"
                                    x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute z-[70] bg-white border border-gray-200 shadow-lg mt-1 rounded-lg w-full max-h-44 overflow-y-auto" style="display:none">
                                    <template x-for="opt in filteredSugs('sub_category', subCategories)" :key="opt">
                                        <li @mousedown.prevent="selectSug('sub_category', opt)"
                                            class="px-3 py-2 text-sm text-gray-700 hover:bg-[#F97316]/5 hover:text-[#F97316] cursor-pointer transition-colors"
                                            x-text="opt"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Name --}}
                    <div>
                        <x-input-label value="Name" /><span class="text-red-400 text-xs ml-0.5">*</span>
                        <input type="text" name="name" x-model="form.name" required
                               class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors mt-1.5"
                               placeholder="e.g. Basketball Fans">
                    </div>

                    {{-- Est. Users + Provider --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Est. Users" />
                            <input type="number" name="estimated_users" x-model="form.estimated_users" min="0"
                                   class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors mt-1.5"
                                   placeholder="e.g. 150000">
                        </div>
                        <div>
                            <x-input-label value="Provider" />
                            <div class="relative mt-1.5">
                                <input type="text" name="provider" x-model="form.provider"
                                       @focus="showSug = 'provider'"
                                       @click.away="showSug = showSug === 'provider' ? null : showSug"
                                       @keydown.escape="showSug = null"
                                       @keydown.enter.prevent="pickFirst('provider')"
                                       class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F97316]/30 focus:border-[#F97316] transition-colors"
                                       placeholder="e.g. Nielsen">
                                <ul x-show="showSug === 'provider' && filteredSugs('provider', providers).length > 0"
                                    x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute z-[70] bg-white border border-gray-200 shadow-lg mt-1 rounded-lg w-full max-h-44 overflow-y-auto" style="display:none">
                                    <template x-for="opt in filteredSugs('provider', providers)" :key="opt">
                                        <li @mousedown.prevent="selectSug('provider', opt)"
                                            class="px-3 py-2 text-sm text-gray-700 hover:bg-[#F97316]/5 hover:text-[#F97316] cursor-pointer transition-colors"
                                            x-text="opt"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Active toggle --}}
                    <div class="flex items-center gap-3">
                        <button type="button" @click="form.is_active = !form.is_active"
                                :class="form.is_active ? 'bg-emerald-500' : 'bg-gray-300'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400/30 cursor-pointer">
                            <span :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"></span>
                        </button>
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <span class="text-sm text-gray-700 cursor-pointer select-none" @click="form.is_active = !form.is_active"
                              x-text="form.is_active ? 'Active' : 'Inactive'"></span>
                    </div>

                    {{-- Footer --}}
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <x-secondary-button type="button" @click="close()">Cancel</x-secondary-button>
                        <x-primary-button type="submit" x-text="mode === 'create' ? 'Create' : 'Save Changes'"></x-primary-button>
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

            init() {
                window.audienceModal = this;
            },
        };
    }

    </script>
    @endpush

</x-app-layout>
