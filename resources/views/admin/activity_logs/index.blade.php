<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- RESERVED HEIGHT FOR BREADCRUMBS OR SPACER -->
                    <div class="h-6 mb-2"></div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Activity Logs
                    </h1>
                </div>
            </header>

            <!-- Filters Bar (Inline Card) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 transition-all hover:border-gray-200 hover:shadow-md group">
                <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="flex flex-col lg:flex-row gap-4 lg:items-end">
                    
                    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-5 gap-3 flex-1">
                        <!-- Action -->
                        <div class="col-span-1">
                            <label for="action" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Action</label>
                            <select name="action" id="action" class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)] appearance-none cursor-pointer">
                                <option value="">All Actions</option>
                                <option value="created" {{ request('action') === 'created' ? 'selected' : '' }}>Created</option>
                                <option value="updated" {{ request('action') === 'updated' ? 'selected' : '' }}>Updated</option>
                                <option value="deleted" {{ request('action') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                            </select>
                        </div>

                        <!-- User -->
                        <div class="col-span-1">
                            <label for="user_id" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">User</label>
                            <select name="user_id" id="user_id" class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)] appearance-none cursor-pointer">
                                <option value="">All Users</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Campaign -->
                        <div class="col-span-2 lg:col-span-1">
                            <label for="campaign" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Campaign</label>
                            <input type="text" name="campaign" id="campaign" value="{{ request('campaign') }}" placeholder="Search name..."
                                class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                        </div>

                        <!-- Date Range -->
                        <div class="col-span-2 md:col-span-2 lg:col-span-2 grid grid-cols-2 gap-3 relative">
                            <!-- Linkage design for dates -->
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 border-t border-gray-300 pointer-events-none hidden md:block mt-3"></div>
                            
                            <div>
                                <label for="date_start" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">From Date</label>
                                <input type="date" name="date_start" id="date_start" value="{{ request('date_start') }}"
                                    class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            </div>
                            <div>
                                <label for="date_end" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide">To Date</label>
                                <input type="date" name="date_end" id="date_end" value="{{ request('date_end') }}"
                                    class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            </div>
                        </div>
                    </div>

                    <!-- Search and Actions Wrapper -->
                    <div class="flex flex-col sm:flex-row gap-3 lg:w-auto w-full border-t border-gray-100 pt-3 mt-3 lg:border-t-0 lg:pt-0 lg:mt-0 shrink-0">
                        <div class="flex-1 sm:w-48 lg:w-44 shrink-0">
                            <label for="search" class="block text-[0.8rem] font-medium text-gray-500 mb-1.5 uppercase tracking-wide lg:hidden">Global Search</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                </span>
                                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search details..."
                                    class="w-full pl-9 pr-3 py-2 bg-gray-50/50 border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:bg-white focus:outline-none focus:ring-[3px] focus:ring-primary/20 focus:border-primary transition-all shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('admin.activity-logs.index', ['clear' => 1]) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-200 bg-white rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary/20 h-9 shrink-0">
                                Reset
                            </a>
                            <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium shadow-[0_2px_8px_0_rgba(79,70,229,0.3)] hover:bg-primary-hover hover:shadow-[0_4px_12px_rgba(79,70,229,0.4)] transition-all h-9 shrink-0">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                Filter
                            </button>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Main Interactive Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all overflow-hidden group">
                <div class="w-full relative overflow-x-auto">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Date</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">User</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Action</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Campaign</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Subject</th>
                                <th class="bg-gray-50/80 sticky top-0 px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($logs as $log)
                                <tr class="hover:bg-indigo-50/30 transition-colors group/row">
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $log->created_at->format('M j, Y g:i A') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $log->user->name ?? 'System' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-50 text-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-700 border border-{{ $log->action === 'deleted' ? 'red' : ($log->action === 'created' ? 'green' : 'blue') }}-200 shadow-sm">
                                            {{ ucfirst($log->action) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if($log->campaign)
                                            <a href="{{ route('campaigns.edit', $log->campaign_id) }}" class="text-primary hover:text-primary-hover hover:underline transition-colors">
                                                {{ Str::limit($log->campaign->name, 25) }}
                                            </a>
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        @if($log->subject_type === 'App\Models\Creative' && $log->subject)
                                            <a href="{{ route('creatives.edit', $log->subject_id) }}" class="text-primary hover:text-primary-hover hover:underline transition-colors">
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
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-sm truncate" title="{{ $log->description }}">
                                            @if($log->changes)
                                                <div class="text-[0.8rem] space-y-1">
                                                    @foreach($log->changes as $field => $change)
                                                        @if($field !== 'updated_at')
                                                            <div class="flex items-center gap-1.5 w-full">
                                                                <span class="font-medium text-gray-700 capitalize w-20 shrink-0 truncate">{{ str_replace('_', ' ', $field) }}:</span>
                                                                
                                                                @php
                                                                    // Extract 'old' safely
                                                                    $hasOld = is_array($change) && array_key_exists('old', $change);
                                                                    $oldRaw = $hasOld ? $change['old'] : null;
                                                                    $oldIsArray = is_array($oldRaw);
                                                                    $oldString = $oldIsArray ? json_encode($oldRaw) : (string)$oldRaw;
                                                                    $oldDisplay = $oldIsArray ? '...' : Str::limit($oldString, 15);

                                                                    // Extract 'new' safely 
                                                                    $hasNew = is_array($change) && array_key_exists('new', $change);
                                                                    // If the payload itself isn't a keyed array of old/new, assume the payload *is* the new value
                                                                    $newRaw = $hasNew ? $change['new'] : $change; 
                                                                    $newIsArray = is_array($newRaw);
                                                                    $newString = $newIsArray ? json_encode($newRaw) : (string)$newRaw;
                                                                    $newDisplay = $newIsArray ? 'array details...' : Str::limit($newString, 25);
                                                                @endphp

                                                                @if($hasOld && $oldRaw !== null)
                                                                    <span class="line-through text-gray-400 truncate max-w-[80px]" title="{{ $oldString }}">
                                                                        {{ $oldDisplay }}
                                                                    </span>
                                                                    <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                                                @endif

                                                                <span class="text-green-600 truncate max-w-[120px]" title="{{ $newString }}">
                                                                    {{ $newDisplay }}
                                                                </span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                {{ $log->description }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <svg class="w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                <p class="text-gray-500 font-medium tracking-wide">No activity logs found matching your criteria</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Block -->
            <div class="mt-6 flex justify-end">
                {{ $logs->links() }}
            </div>

        </div>
    </main>
</x-app-layout>
