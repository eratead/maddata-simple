<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Activity Logs') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $log->user->name ?? 'System' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-100 text-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-800">
                                                {{ ucfirst($log->action) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($log->campaign)
                                                <a href="{{ route('campaigns.edit', $log->campaign_id) }}" class="text-blue-600 hover:text-blue-900 hover:underline">
                                                    {{ $log->campaign->name }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($log->subject_type === 'App\Models\Creative' && $log->subject)
                                                <a href="{{ route('creatives.edit', $log->subject_id) }}" class="text-blue-600 hover:text-blue-900 hover:underline">
                                                    {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                                </a>
                                            @elseif($log->subject_type === 'App\Models\CreativeFile' && $log->subject && $log->subject->creative)
                                                <a href="{{ route('creatives.edit', $log->subject->creative_id) }}" class="text-blue-600 hover:text-blue-900 hover:underline">
                                                    {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                                </a>
                                                <span class="text-xs text-gray-400 block">(of Creative #{{ $log->subject->creative_id }})</span>
                                            @else
                                                {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div class="break-words max-w-xs">
                                                {{ $log->description }}
                                                @if($log->subject_type === 'App\Models\CreativeFile' && $log->action !== 'deleted' && $log->subject)
                                                    <div class="mt-2">
                                                        <a href="{{ route('creatives.files.download', $log->subject_id) }}" class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                            <svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                            </svg>
                                                            Download
                                                        </a>
                                                    </div>
                                                @endif
                                                @if($log->changes)
                                                    <details class="mt-1 cursor-pointer">
                                                        <summary class="text-xs text-blue-600 hover:text-blue-800">View Changes</summary>
                                                        <pre class="mt-2 text-xs bg-gray-100 p-2 rounded overflow-x-auto">{{ json_encode($log->changes, JSON_PRETTY_PRINT) }}</pre>
                                                    </details>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                            No activity logs found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
