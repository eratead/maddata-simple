<x-app-layout>
    @push('page-title')
        <nav class="flex items-center gap-1.5 text-sm font-medium">
            <a href="{{ route('campaigns.index') }}" class="text-gray-400 hover:text-gray-700 transition-colors">Campaigns</a>
            <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
            <a href="{{ route('campaigns.edit', $creative->campaign) }}" class="text-gray-400 hover:text-gray-700 transition-colors">{{ $creative->campaign->name }}</a>
            <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
            <span class="text-gray-900 font-semibold">{{ $creative->name }}</span>
        </nav>
    @endpush

    @push('page-actions')
        <a href="{{ route('campaigns.edit', $creative->campaign_id) }}"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all">
            Cancel
        </a>
        <x-primary-button form="editCreativeForm">Save Changes</x-primary-button>
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

    {{-- Core Details --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-5">
        <form id="editCreativeForm" action="{{ route('creatives.update', $creative) }}" method="POST">
            @csrf
            @method('PUT')

            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-5">Creative Details</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-gray-600">Creative Name</label>
                    <input type="text" name="name" value="{{ old('name', $creative->name) }}" required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-gray-600">Landing Page URL</label>
                    <input type="url" name="landing" value="{{ old('landing', $creative->landing) }}" required
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-gray-600">Status</label>
                    <select name="status"
                        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-[#F97316] focus:ring-2 focus:ring-[#F97316]/20 hover:border-gray-300 transition-all appearance-none cursor-pointer">
                        <option value="1" {{ old('status', $creative->status) == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status', $creative->status) == '0' ? 'selected' : '' }}>Paused</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- Required Sizes Status --}}
    @if($creative->campaign->required_sizes)
        @php
            $requiredSizes = array_filter(array_map('trim', explode(',', $creative->campaign->required_sizes)));
            $uploadedSizes = $creative->files->map(fn($f) => $f->width . 'x' . $f->height)->toArray();
        @endphp
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-5">
            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-4">Required Sizes</p>
            <div class="flex flex-wrap gap-2">
                @foreach($requiredSizes as $size)
                    @php $isUploaded = in_array($size, $uploadedSizes); @endphp
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all
                        {{ $isUploaded
                            ? 'bg-emerald-500 text-white border-emerald-600 shadow-sm'
                            : 'bg-gray-50 text-gray-400 border-gray-200' }}">
                        @if($isUploaded)
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                        {{ $size }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Creative Files --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-5">
        <div class="flex items-center justify-between mb-5">
            <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Creative Files</p>
            @if($creative->files->isNotEmpty())
                <a href="{{ route('creatives.download-all', $creative) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download All
                </a>
            @endif
        </div>

        @if($creative->files->isEmpty())
            <p class="text-sm text-gray-400 italic">No files uploaded yet.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-2">
                @foreach($creative->files as $file)
                    <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
                        {{-- Preview --}}
                        <div class="aspect-square flex items-center justify-center p-2 bg-gray-100">
                            @if(Str::startsWith($file->mime_type, 'image/'))
                                <img src="{{ route('creatives.files.preview', $file) }}" alt="{{ $file->name }}"
                                    class="max-w-full max-h-full object-contain rounded">
                            @elseif(Str::startsWith($file->mime_type, 'video/'))
                                <video src="{{ route('creatives.files.preview', $file) }}"
                                    class="max-w-full max-h-full rounded" controls></video>
                            @else
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            @endif
                        </div>
                        {{-- Info + Actions --}}
                        <div class="p-2.5">
                            <p class="text-xs font-medium text-gray-700 truncate" title="{{ $file->name }}">{{ $file->name }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ $file->width }} × {{ $file->height }}</p>
                            <div class="flex items-center gap-1.5 mt-2">
                                <a href="{{ route('creatives.files.download', $file) }}"
                                    class="flex-1 inline-flex items-center justify-center gap-1 py-1 bg-gray-100 hover:bg-gray-200 rounded-md text-xs font-medium text-gray-600 transition-colors"
                                    title="Download">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                                <button type="button"
                                    @click="if(confirm('Delete this file?')) document.getElementById('delete-file-{{ $file->id }}').submit();"
                                    class="flex-1 inline-flex items-center justify-center gap-1 py-1 bg-red-50 hover:bg-red-100 rounded-md text-xs font-medium text-red-500 transition-colors"
                                    title="Delete">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/>
                                    </svg>
                                </button>
                                <form id="delete-file-{{ $file->id }}" action="{{ route('creatives.files.delete', $file) }}" method="POST" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Upload Zone --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-5" x-data="uploadHandler()">
        <p class="text-[10px] uppercase tracking-wider font-semibold text-gray-400 mb-4">Upload Files</p>

        <form x-ref="uploadForm" action="{{ route('creatives.upload', $creative) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="border-2 border-dashed rounded-xl transition-colors duration-200"
                :class="dragging ? 'border-[#F97316] bg-orange-50' : (fileList.length > 0 ? 'border-[#F97316]/40 bg-orange-50/30' : 'border-gray-200 bg-gray-50 hover:border-gray-300')"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)">

                {{-- Empty state / compact add-more strip --}}
                <div class="cursor-pointer" @click="$refs.fileInput.click()">
                    <div x-show="fileList.length === 0" class="px-6 py-10 text-center">
                        <svg class="mx-auto w-10 h-10 mb-3 transition-colors"
                            :class="dragging ? 'text-[#F97316]' : 'text-gray-300'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-sm font-medium text-gray-600 mb-1">
                            Drop files here or <span class="text-[#F97316]">browse</span>
                        </p>
                        <p class="text-xs text-gray-400">Images or videos up to 50MB</p>
                    </div>
                    <div x-show="fileList.length > 0" x-cloak class="px-4 py-2.5 text-center border-b border-dashed border-[#F97316]/20">
                        <p class="text-xs text-gray-500">
                            Drop more files or <span class="text-[#F97316] font-medium">browse</span>
                        </p>
                    </div>
                </div>

                {{-- File list inside drop zone --}}
                <div x-show="fileList.length > 0 && !processing" x-cloak class="px-4 py-3 space-y-1.5">
                    <template x-for="(file, idx) in fileList" :key="idx">
                        <div class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg">
                            <svg class="w-4 h-4 text-[#F97316] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm text-gray-700 font-medium truncate" x-text="file.name"></span>
                            <span x-show="file.dimensions" class="text-[10px] font-semibold text-[#F97316] bg-orange-50 px-1.5 py-0.5 rounded" x-text="file.dimensions"></span>
                            <span class="text-xs text-gray-400 ml-auto shrink-0" x-text="file.size + ' MB'"></span>
                            <button type="button" @click.stop="removeFile(idx)"
                                class="ml-1 p-0.5 text-gray-300 hover:text-red-500 transition-colors shrink-0"
                                title="Remove file">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Processing spinner inside drop zone --}}
                <div x-show="processing" class="px-6 py-6 flex items-center justify-center gap-2 text-sm text-gray-500">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Processing files...
                </div>

                <input id="file-upload" x-ref="fileInput" name="files[]" type="file" class="hidden" multiple
                    @change="handleFiles($el.files)" required>
            </div>

            <div x-show="fileCount > 0 && !processing" class="mt-4 flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-[#F97316]" x-text="fileCount"></span> file(s) ready to upload
                </p>
                <button type="button" @click="$refs.uploadForm.submit()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#F97316] hover:bg-[#EA6A0A] text-white rounded-lg text-sm font-semibold transition-all hover:-translate-y-0.5 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Upload
                </button>
            </div>
        </form>
    </div>

    {{-- Footer Actions --}}
    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
        <button type="button"
            @click="if(confirm('Are you sure you want to delete this creative?')) document.getElementById('delete-creative-form').submit();"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-500 hover:text-red-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Delete Creative
        </button>
        <form id="delete-creative-form" action="{{ route('creatives.destroy', $creative) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>

        <div class="flex items-center gap-3">
            <a href="{{ route('campaigns.edit', $creative->campaign_id) }}"
                class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all">
                Cancel
            </a>
            <x-primary-button form="editCreativeForm">Save Changes</x-primary-button>
        </div>
    </div>

    @push('scripts')
    <script>
        function uploadHandler() {
            return {
                dragging: false,
                fileCount: 0,
                fileList: [],
                processing: false,
                uploadedSizes: @json($uploadedSizes ?? []),
                async handleFiles(files) {
                    this.processing = true;
                    this.fileCount = 0;
                    this.fileList = [];
                    const validFiles = new DataTransfer();

                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        let shouldUpload = true;
                        let sizeKey = '';

                        if (file.type.startsWith('image/')) {
                            try {
                                const dimensions = await this.getImageDimensions(file);
                                sizeKey = `${dimensions.width}x${dimensions.height}`;
                                if (this.uploadedSizes.includes(sizeKey)) {
                                    if (!confirm(`File "${file.name}" has dimensions ${sizeKey} which already exist. Replace?`)) {
                                        shouldUpload = false;
                                    }
                                }
                            } catch (e) {
                                console.error('Error checking dimensions', e);
                            }
                        }

                        if (shouldUpload) {
                            validFiles.items.add(file);
                            const sizeMB = (file.size / 1024 / 1024).toFixed(1);
                            this.fileList.push({ name: file.name, size: sizeMB, dimensions: sizeKey });
                        }
                    }

                    this.$refs.fileInput.files = validFiles.files;
                    this.fileCount = validFiles.files.length;
                    this.processing = false;
                },
                removeFile(index) {
                    this.fileList.splice(index, 1);
                    const dt = new DataTransfer();
                    const files = this.$refs.fileInput.files;
                    for (let i = 0; i < files.length; i++) {
                        if (i !== index) dt.items.add(files[i]);
                    }
                    this.$refs.fileInput.files = dt.files;
                    this.fileCount = dt.files.length;
                },
                getImageDimensions(file) {
                    return new Promise((resolve, reject) => {
                        const img = new Image();
                        const url = URL.createObjectURL(file);
                        img.onload = () => { URL.revokeObjectURL(url); resolve({ width: img.width, height: img.height }); };
                        img.onerror = () => { URL.revokeObjectURL(url); reject(); };
                        img.src = url;
                    });
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
