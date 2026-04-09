<x-app-layout>

@push('page-title')
    <div class="flex items-center gap-2 text-sm min-w-0">
        <a href="{{ route('admin.campaign_changes.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap">Campaign Changes</a>
        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-gray-700 truncate">{{ $campaign->name }}</span>
    </div>
@endpush

@push('page-actions')
    <form action="{{ route('admin.campaign_changes.download_all', $campaign) }}" method="POST" class="inline">
        @csrf
        <x-secondary-button type="submit">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span class="hidden sm:inline">Download All</span>
        </x-secondary-button>
    </form>

    <button type="button"
            @click="$dispatch('confirm-action', {
                title:        'Mark all as handled?',
                message:      'All pending changes for ' + @js($campaign->name) + ' will be marked as handled.',
                confirmLabel: 'Mark Handled',
                form:         document.getElementById('mark-all-handled-form')
            })"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg transition-colors cursor-pointer">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="hidden sm:inline">Mark All Handled</span>
    </button>
@endpush

    <x-flash-messages />

    <form id="bulk-handle-form" action="{{ route('admin.campaign_changes.handle', $campaign) }}" method="POST">
        @csrf
        <x-page-box class="overflow-hidden relative">
            <div class="overflow-x-auto">
                <table class="min-w-full w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="w-10 px-4 py-3 text-center">
                                <input type="checkbox" @click="toggleAll($el)"
                                       class="w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/20 cursor-pointer">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500 whitespace-nowrap">Date</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Context</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">User</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Action</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-gray-500">Description</th>
                            <th class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500">Download</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-sm">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <input type="checkbox" name="log_ids[]" value="{{ $log->id }}"
                                           class="log-checkbox w-4 h-4 rounded border-gray-300 text-[#F97316] focus:ring-[#F97316]/20 cursor-pointer">
                                </td>
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                    {{ $log->created_at->timezone(config('app.display_timezone'))->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    @if($log->subject_type === 'App\Models\CreativeFile' && $log->subject && $log->subject->creative)
                                        <a href="{{ route('creatives.edit', $log->subject->creative) }}" class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors flex items-center gap-1.5 break-all">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            {{ $log->subject->creative->name }}
                                        </a>
                                    @elseif($log->subject_type === 'App\Models\Creative' && $log->subject)
                                        <a href="{{ route('creatives.edit', $log->subject) }}" class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors flex items-center gap-1.5 break-all">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            {{ $log->subject->name }}
                                        </a>
                                    @elseif($log->subject_type === 'App\Models\Campaign' && $log->subject)
                                        <a href="{{ route('campaigns.edit', $log->subject) }}" class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors flex items-center gap-1.5 break-all">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                                            {{ $log->subject->name }}
                                        </a>
                                    @elseif(is_array($log->changes) && isset($log->changes['creative_id']) && ($creative = \App\Models\Creative::find($log->changes['creative_id'])))
                                        <a href="{{ route('creatives.edit', $creative) }}" class="text-[#F97316] hover:text-[#EA580C] hover:underline transition-colors flex items-center gap-1.5 break-all">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            {{ $creative->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">
                                    {{ $log->user->name ?? 'System' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $badgeClass = match($log->action) {
                                            'deleted' => 'bg-red-50 text-red-700 border-red-100',
                                            'created' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                            default   => 'bg-blue-50 text-blue-700 border-blue-100',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 min-w-[250px] break-all">
                                    @php
                                        $description = $log->description;
                                        $width  = $log->changes['width']  ?? null;
                                        $height = $log->changes['height'] ?? null;

                                        if ((!$width || !$height) && $log->subject_type === 'App\Models\CreativeFile' && $log->subject) {
                                            $width  = $log->subject->width;
                                            $height = $log->subject->height;

                                            if (!$width || !$height) {
                                                try {
                                                    if (\Illuminate\Support\Facades\Storage::disk('creatives')->exists($log->subject->path)) {
                                                        $path     = \Illuminate\Support\Facades\Storage::disk('creatives')->path($log->subject->path);
                                                        $fileDims = @getimagesize($path);
                                                        if ($fileDims) {
                                                            $width  = $fileDims[0];
                                                            $height = $fileDims[1];
                                                        }
                                                    }
                                                } catch (\Exception $e) { /* ignore */ }
                                            }
                                        }

                                        if ($width && $height) {
                                            $dims = '[' . $width . 'x' . $height . ']';
                                            if (!str_contains($description, $dims)) {
                                                $description = preg_replace('/(file)/', '$1 ' . $dims, $description, 1);
                                            }
                                        }
                                    @endphp
                                    {{ $description }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($log->subject_type === 'App\Models\CreativeFile' && $log->action !== 'deleted')
                                        <a href="{{ route('admin.campaign_changes.download', $log->id) }}"
                                           class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-[#F97316] transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Download
                                        </a>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Sticky Bulk Action Footer --}}
            <div id="bulk-actions"
                 class="px-4 py-3 bg-white border-t border-gray-200 transition-all duration-300 transform translate-y-full opacity-0 absolute bottom-0 left-0 right-0 z-10"
                 style="display:none">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#F97316] hover:bg-[#EA580C] text-white text-sm font-semibold rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Mark Selected as Handled
                </button>
            </div>

        </x-page-box>
    </form>

    {{-- Hidden form for Mark All Handled (triggered from page-actions) --}}
    <form id="mark-all-handled-form" action="{{ route('admin.campaign_changes.handle', $campaign) }}" method="POST" class="hidden">
        @csrf
    </form>

    @push('scripts')
    <script>
        function toggleAll(source) {
            var checkboxes = document.getElementsByClassName('log-checkbox');
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
            toggleBulkActions();
        }

        document.querySelectorAll('.log-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', toggleBulkActions);
        });

        function toggleBulkActions() {
            var checked    = document.querySelectorAll('.log-checkbox:checked');
            var bulkActions = document.getElementById('bulk-actions');
            var cardInner  = document.querySelector('form#bulk-handle-form > div');

            if (checked.length > 0) {
                bulkActions.style.display = 'block';
                setTimeout(function() {
                    bulkActions.classList.remove('translate-y-full', 'opacity-0');
                    bulkActions.classList.add('translate-y-0', 'opacity-100');
                    if (cardInner) cardInner.style.paddingBottom = '72px';
                }, 10);
            } else {
                bulkActions.classList.remove('translate-y-0', 'opacity-100');
                bulkActions.classList.add('translate-y-full', 'opacity-0');
                if (cardInner) cardInner.style.paddingBottom = '0px';
                setTimeout(function() {
                    if (document.querySelectorAll('.log-checkbox:checked').length === 0) {
                        bulkActions.style.display = 'none';
                    }
                }, 300);
            }
        }
    </script>
    @endpush

</x-app-layout>
