<x-app-layout>
    <main class="flex-1 w-full min-w-0 p-2 sm:p-4 md:p-8 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <header class="flex flex-col md:flex-row md:justify-between md:items-end gap-3 mb-4 sm:mb-8">
                <div>
                    <!-- BREADCRUMBS BLOCK (Fixed Height) -->
                    <nav class="flex items-center gap-2 text-sm font-medium h-6 mb-2">
                        <a href="{{ route('campaigns.index') }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">Campaigns</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        <a href="{{ route('campaigns.edit', $creative->campaign) }}" class="text-primary hover:text-primary-hover transition-colors cursor-pointer">{{ $creative->campaign->name }}</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-gray-400" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        <span class="text-gray-600">Edit Creative</span>
                    </nav>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 leading-tight">
                        Edit Creative: {{ $creative->name }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('campaigns.edit', $creative->campaign_id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all cursor-pointer">
                        Cancel
                    </a>
                    <button type="submit" form="editCreativeForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-lg text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                        Save Changes
                    </button>
                </div>
            </header>

            @if ($errors->any())
                <div class="mb-6 bg-red-50/80 border border-red-200 text-red-600 px-4 py-3 rounded-xl flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-start gap-3 shadow-sm text-sm">
                    <svg class="w-5 h-5 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            <!-- Core Details Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  mb-6 group">
                <form id="editCreativeForm" action="{{ route('creatives.update', $creative) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="flex items-center gap-2 mb-4 border-b border-gray-100 pb-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h2 class="text-base font-semibold text-gray-900">Creative Details</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-1.5 md:col-span-2">
                            <label class="text-[0.85rem] font-medium text-gray-700">Creative Name</label>
                            <input type="text" name="name" value="{{ old('name', $creative->name) }}" required
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[0.85rem] font-medium text-gray-700">Landing Page URL</label>
                            <input type="url" name="landing" value="{{ old('landing', $creative->landing) }}" required
                                class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[0.85rem] font-medium text-gray-700">Status</label>
                            <select name="status" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-gray-900 text-sm focus:outline-none focus:bg-white focus:border-primary focus:ring-[3px] focus:ring-primary/20 hover:border-gray-300 transition-all appearance-none">
                                <option value="1" {{ old('status', $creative->status) == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status', $creative->status) == '0' ? 'selected' : '' }}>Paused</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Media Setup Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all  p-4 sm:p-6  mb-6 group">

            <!-- Required Sizes Indicators -->
            @if($creative->campaign->required_sizes)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:border-gray-200 hover:shadow-md transition-all p-4 sm:p-6 mb-6 group">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">Required Sizes Status</h3>
                    </div>

                    <div class="flex flex-wrap gap-2.5">
                        @php
                            $requiredSizes = array_filter(array_map('trim', explode(',', $creative->campaign->required_sizes)));
                            $uploadedSizes = $creative->files->map(fn($f) => $f->width . 'x' . $f->height)->toArray();
                        @endphp
                        
                        @foreach($requiredSizes as $size)
                            @php
                                $isUploaded = in_array($size, $uploadedSizes);
                            @endphp
                            <div class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold border transition-all duration-300 {{ $isUploaded ? 'bg-emerald-500 text-white border-emerald-600 shadow-[0_2px_8px_rgba(16,185,129,0.3)]' : 'bg-gray-50 text-gray-400 border-gray-200' }}">
                                <span>{{ $size }}</span>
                                @if($isUploaded)
                                    <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-4 italic">Sizes with a green background and checkmark have matching files uploaded.</p>
                </div>
            @endif
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Creative Files</h3>
                @if($creative->files->isNotEmpty())
                    <a href="{{ route('creatives.download-all', $creative) }}" class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 rounded-lg text-sm font-semibold transition-all shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download All (Zip)
                    </a>
                @endif
            </div>
            
            @if($creative->files->isEmpty())
                <p class="text-gray-500 text-sm italic mb-4">No files uploaded yet.</p>
            @else
                <div class="border border-gray-200 rounded-md overflow-hidden mb-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preview</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dimensions</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($creative->files as $file)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if(Str::startsWith($file->mime_type, 'image/'))
                                            <img src="{{ route('creatives.files.preview', $file) }}" alt="{{ $file->name }}" class="object-contain rounded" style="max-width: 200px; max-height: 200px; width: auto; height: auto;">
                                        @elseif(Str::startsWith($file->mime_type, 'video/'))
                                            <video src="{{ route('creatives.files.preview', $file) }}" class="rounded" controls style="max-width: 200px; max-height: 200px; width: auto; height: auto;"></video>
                                        @else
                                            <span class="text-xs text-gray-400">No preview</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $file->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $file->width }} x {{ $file->height }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('creatives.files.download', $file) }}" 
                                               class="inline-flex items-center justify-center p-2 text-primary hover:bg-primary/5 rounded-lg transition-colors group"
                                               title="Download">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                            </a>
                                            <button type="button" 
                                                    onclick="if(confirm('Delete this file?')) document.getElementById('delete-file-{{ $file->id }}').submit();"
                                                    class="inline-flex items-center justify-center p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors group"
                                                    title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M3 7h18"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <form id="delete-file-{{ $file->id }}" action="{{ route('creatives.files.delete', $file) }}" method="POST" class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Upload Zone -->
            <div x-data="uploadHandler()" 
                 class="bg-gray-50 p-4 rounded-md border-2 border-dashed transition-colors duration-200"
                 :class="dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)">
                
                <form x-ref="uploadForm" action="{{ route('creatives.upload', $creative) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" style="height: 3rem; width: 3rem;" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="mt-2 flex text-sm text-gray-600 justify-center">
                            <label for="file-upload" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Upload files</span>
                                <input id="file-upload" x-ref="fileInput" name="files[]" type="file" class="sr-only" multiple @change="handleFiles($el.files)" required>
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            Images or Videos up to 50MB
                        </p>
                        
                        <div x-show="fileCount > 0" class="mt-2 text-sm text-green-600 font-medium">
                            <span x-text="fileCount"></span> file(s) selected
                        </div>

                        <div class="mt-2" x-show="processing">
                             <span class="text-xs text-gray-500">Processing files...</span>
                        </div>

                        <div class="mt-2">
                            <button type="button" @click="$refs.uploadForm.submit()" x-show="fileCount > 0 && !processing" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Upload All
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function uploadHandler() {
                return {
                    dragging: false,
                    fileCount: 0,
                    processing: false,
                    uploadedSizes: @json($uploadedSizes ?? []),
                    async handleFiles(files) {
                        this.processing = true;
                        this.fileCount = 0;
                        const validFiles = new DataTransfer();
                        
                        for (let i = 0; i < files.length; i++) {
                            const file = files[i];
                            let shouldUpload = true;
                            
                            if (file.type.startsWith('image/')) {
                                try {
                                    const dimensions = await this.getImageDimensions(file);
                                    const sizeKey = `${dimensions.width}x${dimensions.height}`;
                                    
                                    if (this.uploadedSizes.includes(sizeKey)) {
                                        if (!confirm(`File "${file.name}" has dimensions ${sizeKey} which already exist. Do you want to replace the existing file?`)) {
                                            shouldUpload = false;
                                        }
                                    }
                                } catch (e) {
                                    console.error('Error checking dimensions', e);
                                }
                            }
                            // Video dimension check client-side is complex and slow, skipping for now or can add later if critical.
                            // Currently focusing on images as per common use case. 
                            
                            if (shouldUpload) {
                                validFiles.items.add(file);
                            }
                        }
                        
                        this.$refs.fileInput.files = validFiles.files;
                        this.fileCount = validFiles.files.length;
                        this.processing = false;
                    },
                    getImageDimensions(file) {
                        return new Promise((resolve, reject) => {
                            const img = new Image();
                            img.onload = () => resolve({ width: img.width, height: img.height });
                            img.onerror = reject;
                            img.src = URL.createObjectURL(file);
                        });
                    }
                }
            }
        </script>

                <div class="pt-6 border-t border-gray-200/60 mt-6 flex justify-between items-center">
                    <button type="button" onclick="if(confirm('Are you sure you want to delete this creative?')) document.getElementById('delete-form').submit();" 
                        class="text-red-500 hover:text-red-700 font-medium text-sm flex items-center gap-1.5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete Creative
                    </button>
                    <form id="delete-form" action="{{ route('creatives.destroy', $creative) }}" method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                    
                    <div class="flex items-center gap-3">
                        <a href="{{ route('campaigns.edit', $creative->campaign_id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all">Cancel</a>
                        <button type="submit" form="editCreativeForm" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-br from-primary to-primary-hover text-white rounded-md text-sm font-medium shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.45)] hover:-translate-y-0.5 transition-all">
                            Save Changes
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </main>
</x-app-layout>
