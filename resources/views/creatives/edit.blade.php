<x-app-layout>
    <x-title>
        <div class="flex items-center gap-4">
            <span>Edit Creative: {{ $creative->name }}</span>
            <a href="{{ route('campaigns.edit', $creative->campaign) }}" class="flex items-center gap-1 hover:underline" style="color: #E85E26">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span class="text-lg">{{ $creative->campaign->name }}</span>
            </a>
        </div>
    </x-title>
    <x-page-box>
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded text-sm">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('creatives.update', $creative) }}" method="POST" class="mb-8">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" value="{{ old('name', $creative->name) }}" required
                    class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Landing Page URL</label>
                <input type="url" name="landing" value="{{ old('landing', $creative->landing) }}" required
                    class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="mt-1 block w-full border border-gray-300 rounded px-3 py-2 shadow-sm focus:outline-none focus:ring focus:border-blue-300">
                    <option value="1" {{ old('status', $creative->status) == '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('status', $creative->status) == '0' ? 'selected' : '' }}>Paused</option>
                </select>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('campaigns.edit', $creative->campaign_id) }}" class="text-gray-600 hover:text-gray-800 mr-4 self-center">Cancel</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </div>
        </form>

        <!-- Files Section -->
        <div class="mb-8 border-t border-gray-200 pt-6">

            @if($creative->campaign->required_sizes)
                <div class="mb-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Required Sizes</h4>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $requiredSizes = explode(',', $creative->campaign->required_sizes);
                            $uploadedFiles = $creative->files;
                            $uploadedSizes = [];
                            foreach($uploadedFiles as $file) {
                                $uploadedSizes[] = $file->width . 'x' . $file->height;
                            }
                        @endphp
                        
                        @foreach($requiredSizes as $size)
                            @php
                                $size = trim($size);
                                $isUploaded = in_array($size, $uploadedSizes);
                            @endphp
                            <div class="flex items-center px-3 py-1 rounded-full text-sm border {{ $isUploaded ? 'bg-green-100 text-green-800 border-green-200' : 'bg-gray-50 text-gray-600 border-gray-200' }}">
                                <span class="font-medium mr-1">{{ $size }}</span>
                                @if($isUploaded)
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Creative Files</h3>
                @if($creative->files->isNotEmpty())
                    <a href="{{ route('creatives.download-all', $creative) }}" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('creatives.files.download', $file) }}" class="text-blue-600 hover:text-blue-900 mr-3">Download</a>
                                        <button type="button" onclick="if(confirm('Delete this file?')) document.getElementById('delete-file-{{ $file->id }}').submit();"
                                            class="text-red-600 hover:text-red-900">Delete</button>
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

        <!-- Footer Actions -->
        <div class="pt-6 border-t border-gray-200">
            <button type="button" onclick="if(confirm('Are you sure you want to delete this creative?')) document.getElementById('delete-form').submit();" 
                class="text-red-600 hover:text-red-800 text-sm font-medium">
                Delete Creative
            </button>
            <form id="delete-form" action="{{ route('creatives.destroy', $creative) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </x-page-box>
</x-app-layout>
