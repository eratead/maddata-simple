<x-app-layout>
    <x-title>
        Changes for: {{ $campaign->name }}
        <div class="text-sm font-normal mt-1 text-gray-500">
            Client: {{ $campaign->client->name }}
        </div>
    </x-title>

    <x-page-box>
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex justify-between items-center mb-6">
            <a href="{{ route('admin.campaign_changes.index') }}" class="text-gray-600 hover:text-gray-900 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
            
            <div class="flex space-x-3">
                <form action="{{ route('admin.campaign_changes.download_all', $campaign) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded inline-flex items-center transition duration-300">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download All Files
                    </button>
                </form>

                <form action="{{ route('admin.campaign_changes.handle', $campaign) }}" method="POST" onsubmit="return confirm('Are you sure you want to mark all changes as handled?');">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center transition duration-300">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Mark All Handled
                    </button>
                </form>
            </div>
        </div>

        <form id="bulk-handle-form" action="{{ route('admin.campaign_changes.handle', $campaign) }}" method="POST">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="py-3 px-6 text-center w-10">
                                <input type="checkbox" onclick="toggleAll(this)" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="py-3 px-6 text-left">Date</th>
                            <th class="py-3 px-6 text-left">Context</th>
                            <th class="py-3 px-6 text-left">User</th>
                            <th class="py-3 px-6 text-left">Action</th>
                            <th class="py-3 px-6 text-left">Description</th>
                            <th class="py-3 px-6 text-center">Download</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        @foreach($logs as $log)
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-6 text-center">
                                    <input type="checkbox" name="log_ids[]" value="{{ $log->id }}" class="log-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                <td class="py-3 px-6 text-left whitespace-nowrap">
                                    {{ $log->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="py-3 px-6 text-left">
                                    @if($log->subject_type === 'App\Models\CreativeFile' && $log->subject && $log->subject->creative)
                                        <a href="{{ route('creatives.edit', $log->subject->creative) }}" class="text-blue-600 hover:underline">
                                            Creative: {{ $log->subject->creative->name }}
                                        </a>
                                    @elseif($log->subject_type === 'App\Models\Creative' && $log->subject)
                                        <a href="{{ route('creatives.edit', $log->subject) }}" class="text-blue-600 hover:underline">
                                            Creative: {{ $log->subject->name }}
                                        </a>
                                    @elseif($log->subject_type === 'App\Models\Campaign' && $log->subject)
                                        <a href="{{ route('campaigns.edit', $log->subject) }}" class="text-blue-600 hover:underline">
                                            Campaign: {{ $log->subject->name }}
                                        </a>
                                    @elseif(is_array($log->changes) && isset($log->changes['creative_id']) && ($creative = \App\Models\Creative::find($log->changes['creative_id'])))
                                        <a href="{{ route('creatives.edit', $creative) }}" class="text-blue-600 hover:underline">
                                           Creative: {{ $creative->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-6 text-left">
                                    {{ $log->user->name ?? 'Unknown' }}
                                </td>
                                <td class="py-3 px-6 text-left">
                                    <span class="px-2 py-1 rounded text-xs font-semibold
                                        @if($log->action == 'created') bg-green-100 text-green-800
                                        @elseif($log->action == 'updated') bg-blue-100 text-blue-800
                                        @elseif($log->action == 'deleted') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                </td>
                                <td class="py-3 px-6 text-left">
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
                                <td class="py-3 px-6 text-center">
                                    @if($log->subject_type === 'App\Models\CreativeFile' && $log->action !== 'deleted')
                                        <a href="{{ route('admin.campaign_changes.download', $log->id) }}" class="text-blue-600 hover:text-blue-900 hover:underline">
                                            Download
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200" id="bulk-actions" style="display: none;">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                    Mark Selected as Handled
                </button>
            </div>
        </form>
    </x-page-box>

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
            } else {
                bulkActions.style.display = 'none';
            }
        }
    </script>
</x-app-layout>
