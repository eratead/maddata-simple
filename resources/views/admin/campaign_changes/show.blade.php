<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto flex flex-col h-full">

            <!-- Split Header -->
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <!-- Breadcrumbs -->
                    <nav class="flex items-center text-[0.8rem] text-gray-400 mb-2 mt-4 md:mt-0 font-medium tracking-wide">
                        <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <a href="{{ route('admin.campaign_changes.index') }}" class="hover:text-primary transition-colors">Campaign Changes</a>
                        <span class="mx-2 text-gray-300">/</span>
                        <span class="text-gray-600">Review</span>
                    </nav>

                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Changes for {{ $campaign->name }}</h1>
                    <p class="text-sm text-gray-500 mt-2">Client: {{ $campaign->client->name }}</p>
                </div>

                <!-- Global Actions -->
                <div class="flex flex-wrap items-center gap-3">
                    <form action="{{ route('admin.campaign_changes.download_all', $campaign) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center h-10 px-4 bg-white border border-gray-200 hover:border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all focus:outline-none focus:ring-2 focus:ring-gray-200">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download All Selected
                        </button>
                    </form>

                    <form action="{{ route('admin.campaign_changes.handle', $campaign) }}" method="POST" onsubmit="return confirm('Are you sure you want to mark all changes as handled?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center h-10 px-4 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-green-500/50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Mark All Handled
                        </button>
                    </form>
                </div>
            </div>

            <!-- Alerts -->
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50/80 border border-red-200 text-red-700 rounded-xl flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-3 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            <!-- Main Interactive Card -->
            <form id="bulk-handle-form" action="{{ route('admin.campaign_changes.handle', $campaign) }}" method="POST">
                @csrf
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all duration-300 group overflow-hidden">
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-center w-12">
                                        <input type="checkbox" onclick="toggleAll(this)" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/20 transition-all cursor-pointer">
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Context</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">User</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                                    <th scope="col" class="px-6 py-4 text-left text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="px-6 py-4 text-right text-[0.75rem] font-semibold text-gray-500 uppercase tracking-wider">Download</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($logs as $log)
                                    <tr class="hover:bg-indigo-50/30 transition-colors group/row">
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <input type="checkbox" name="log_ids[]" value="{{ $log->id }}" class="log-checkbox w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary/20 transition-all cursor-pointer">
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                            {{ $log->created_at->format('M j, Y g:i A') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            @if($log->subject_type === 'App\Models\CreativeFile' && $log->subject && $log->subject->creative)
                                                <a href="{{ route('creatives.edit', $log->subject->creative) }}" class="text-primary hover:text-primary-hover hover:underline transition-colors flex items-center gap-1.5 break-all">
                                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    {{ $log->subject->creative->name }}
                                                </a>
                                            @elseif($log->subject_type === 'App\Models\Creative' && $log->subject)
                                                <a href="{{ route('creatives.edit', $log->subject) }}" class="text-primary hover:text-primary-hover hover:underline transition-colors flex items-center gap-1.5 break-all">
                                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                                    {{ $log->subject->name }}
                                                </a>
                                            @elseif($log->subject_type === 'App\Models\Campaign' && $log->subject)
                                                <a href="{{ route('campaigns.edit', $log->subject) }}" class="text-primary hover:text-primary-hover hover:underline transition-colors flex items-center gap-1.5 break-all">
                                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                                                    {{ $log->subject->name }}
                                                </a>
                                            @elseif(is_array($log->changes) && isset($log->changes['creative_id']) && ($creative = \App\Models\Creative::find($log->changes['creative_id'])))
                                                <a href="{{ route('creatives.edit', $creative) }}" class="text-primary hover:text-primary-hover hover:underline transition-colors flex items-center gap-1.5 break-all">
                                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                                    {{ $creative->name }}
                                                </a>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $log->user->name ?? 'System' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-50 text-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-700 border border-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-200 shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                                                {{ ucfirst($log->action) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 min-w-[250px] break-all">
                                            @php
                                                $description = $log->description;
                                                // Try to get dims from changes
                                                $width = $log->changes['width'] ?? null;
                                                $height = $log->changes['height'] ?? null;

                                                // Fallback: Try to get dims from the subject (CreativeFile) if it exists
                                                if ((!$width || !$height) && $log->subject_type === 'App\Models\CreativeFile' && $log->subject) {
                                                    $width = $log->subject->width;
                                                    $height = $log->subject->height;

                                                    // Last Resort: Check file on disk if DB is 0
                                                    if (!$width || !$height) {
                                                        try {
                                                             if (\Illuminate\Support\Facades\Storage::disk('creatives')->exists($log->subject->path)) {
                                                                 $path = \Illuminate\Support\Facades\Storage::disk('creatives')->path($log->subject->path);
                                                                 $fileDims = @getimagesize($path);
                                                                 if ($fileDims) {
                                                                     $width = $fileDims[0];
                                                                     $height = $fileDims[1];
                                                                 }
                                                             }
                                                        } catch (\Exception $e) { /* ignore */ }
                                                    }
                                                }

                                                if ($width && $height) {
                                                    $dims = '[' . $width . 'x' . $height . ']';
                                                    // Check if description already contains dimensions to avoid duplication
                                                    if (!str_contains($description, $dims)) {
                                                        // Insert dims after "file"
                                                        $description = preg_replace('/(file)/', '$1 ' . $dims, $description, 1);
                                                    }
                                                }
                                            @endphp
                                            {{ $description }}
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            @if($log->subject_type === 'App\Models\CreativeFile' && $log->action !== 'deleted')
                                                <a href="{{ route('admin.campaign_changes.download', $log->id) }}" class="inline-flex items-center text-xs font-semibold text-gray-500 hover:text-blue-600 transition-colors group/dl">
                                                    <svg class="w-4 h-4 mr-1.5 group-hover/dl:-translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                    Download
                                                </a>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Sticky Bulk Action Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 transition-all duration-300 transform translate-y-full opacity-0 absolute bottom-0 left-0 right-0 z-10" id="bulk-actions" style="display: none;">
                        <button type="submit" class="inline-flex items-center h-10 px-4 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-semibold rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Mark Selected as Handled
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </main>

    <script>
        function toggleAll(source) {
            checkboxes = document.getElementsByClassName('log-checkbox');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
            toggleBulkActions();
        }

        document.querySelectorAll('.log-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', toggleBulkActions);
        });

        function toggleBulkActions() {
            const checkboxes = document.querySelectorAll('.log-checkbox:checked');
            const bulkActions = document.getElementById('bulk-actions');
            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
                // Small delay to allow display block to apply before animating transform
                setTimeout(() => {
                    bulkActions.classList.remove('translate-y-full', 'opacity-0');
                    bulkActions.classList.add('translate-y-0', 'opacity-100');
                }, 10);
            } else {
                bulkActions.classList.remove('translate-y-0', 'opacity-100');
                bulkActions.classList.add('translate-y-full', 'opacity-0');
                // Wait for animation to finish before hiding completely
                setTimeout(() => {
                    if (document.querySelectorAll('.log-checkbox:checked').length === 0) {
                        bulkActions.style.display = 'none';
                    }
                }, 300);
            }
        }
        
        // Ensure card container is relative so absolute footer sticks to bottom of *card*
        document.querySelector('form#bulk-handle-form > div').classList.add('relative', 'pb-0');
        // Add padding bottom dynamically when bulk actions show to not obscure last row
        const originalToggle = toggleBulkActions;
        toggleBulkActions = function() {
            originalToggle();
            const cardInner = document.querySelector('form#bulk-handle-form > div');
            if (document.querySelectorAll('.log-checkbox:checked').length > 0) {
                cardInner.style.paddingBottom = '72px'; // Height of footer + some
            } else {
                 cardInner.style.paddingBottom = '0px';
            }
        }
    </script>
</x-app-layout>
