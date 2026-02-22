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
                    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="mb-6 bg-gray-50 p-4 rounded-lg flex flex-wrap gap-4 items-end">
                        <div class="flex-1 min-w-[150px]">
                            <label for="action" class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                            <select name="action" id="action" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Actions</option>
                                <option value="created" {{ request('action') === 'created' ? 'selected' : '' }}>Created</option>
                                <option value="updated" {{ request('action') === 'updated' ? 'selected' : '' }}>Updated</option>
                                <option value="deleted" {{ request('action') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                            </select>
                        </div>

                        <div class="flex-1 min-w-[200px]">
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                            <select name="user_id" id="user_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Users</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex-1 min-w-[200px]">
                            <label for="campaign" class="block text-sm font-medium text-gray-700 mb-1">Campaign</label>
                            <input type="text" name="campaign" id="campaign" value="{{ request('campaign') }}" placeholder="Campaign name..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div class="flex flex-col min-w-[150px]">
                            <label for="date_start" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                            <input type="date" name="date_start" id="date_start" value="{{ request('date_start') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div class="flex flex-col min-w-[150px]">
                            <label for="date_end" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                            <input type="date" name="date_end" id="date_end" value="{{ request('date_end') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div class="flex-1 min-w-[200px]">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Keywords..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Filter
                            </button>
                            <a href="{{ route('admin.activity-logs.index', ['clear' => 1]) }}" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Default View
                            </a>
                        </div>
                    </form>

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
