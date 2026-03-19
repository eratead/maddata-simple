<x-app-layout>
    @push('page-title')
        <nav class="flex items-center gap-1.5 text-sm font-medium">
            <a href="{{ route('campaigns.index') }}" class="text-gray-400 hover:text-gray-700 transition-colors">Campaigns</a>
            <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
            <a href="{{ route('campaigns.edit', $campaign) }}" class="text-gray-400 hover:text-gray-700 transition-colors">{{ $campaign->name }}</a>
            <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
            <span class="text-gray-900 font-semibold">New Creative</span>
        </nav>
    @endpush

    @push('page-actions')
        <a href="{{ route('campaigns.edit', $campaign) }}"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all">
            Cancel
        </a>
        <x-primary-button form="createCreativeForm">Create Creative</x-primary-button>
    @endpush

    <x-flash-messages />

    @if ($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <ul class="list-disc list-inside text-sm space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="createCreativeForm" action="{{ route('creatives.store', $campaign) }}" method="POST">
        @csrf

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-5">Creative Details</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-gray-600">Creative Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-gray-600">Landing Page URL</label>
                    <input type="url" name="landing" value="{{ old('landing') }}" required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-gray-600">Status</label>
                    <select name="status"
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all appearance-none cursor-pointer">
                        <option value="1" {{ old('status') == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Paused</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex items-start gap-3 bg-orange-50 border border-orange-100 rounded-xl px-4 py-3.5">
                <svg class="w-4 h-4 text-[#F97316] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-gray-600">
                    First save this creative. You will be able to upload media files directly after it is created.
                </p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ route('campaigns.edit', $campaign) }}"
                class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all">
                Cancel
            </a>
            <x-primary-button>Create Creative</x-primary-button>
        </div>
    </form>
</x-app-layout>
