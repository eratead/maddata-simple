<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreativeRequest;
use App\Http\Requests\UpdateCreativeRequest;
use App\Models\Campaign;
use App\Models\Creative;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class CreativeController extends Controller
{
    use AuthorizesRequests;

    // Allowed MIME types for creative uploads
    private const ALLOWED_MIMES = 'jpeg,jpg,png,gif,mp4,webm';

    private const ALLOWED_MIME_TYPES = 'image/jpeg,image/png,image/gif,video/mp4,video/webm';

    public function create(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        return view('creatives.create', compact('campaign'));
    }

    public function store(StoreCreativeRequest $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $validated = $request->validated();

        $creative = $campaign->creatives()->create($validated);

        return redirect()->route('creatives.edit', $creative)
            ->with('success', 'Creative created successfully. You can now add files.');
    }

    public function edit(Creative $creative)
    {
        $this->authorize('update', $creative->campaign);
        $creative->load('files');

        return view('creatives.edit', compact('creative'));
    }

    public function update(UpdateCreativeRequest $request, Creative $creative)
    {
        $this->authorize('update', $creative->campaign);

        $validated = $request->validated();

        $creative->update($validated);

        return redirect()->route('campaigns.edit', $creative->campaign)
            ->with('success', 'Creative updated successfully.');
    }

    public function destroy(Creative $creative)
    {
        $this->authorize('update', $creative->campaign);

        $campaign = $creative->campaign;
        $creative->delete();

        return redirect()->route('campaigns.edit', $campaign)
            ->with('success', 'Creative deleted successfully.');
    }

    public function upload(Request $request, Creative $creative)
    {
        $this->authorize('update', $creative->campaign);

        $request->validate([
            'files' => 'required',
            'files.*' => [
                'file',
                'max:51200', // 50 MB
                'mimes:'.self::ALLOWED_MIMES,
                'mimetypes:'.self::ALLOWED_MIME_TYPES,
            ],
        ]);

        $manager = new ImageManager(new GdDriver);
        $count = 0;

        foreach ($request->file('files') as $file) {
            $mimeType = $file->getMimeType();
            $isImage = str_starts_with($mimeType, 'image/');

            // Detect dimensions
            $width = 0;
            $height = 0;

            if ($isImage) {
                try {
                    $dims = getimagesize($file->getPathname());
                    if ($dims) {
                        $width = $dims[0];
                        $height = $dims[1];
                    }
                } catch (\Exception $e) { /* keep 0×0 */
                }
            } elseif ($mimeType === 'video/mp4' || $mimeType === 'video/webm') {
                try {
                    $output = shell_exec(
                        'ffprobe -v error -select_streams v:0 -show_entries stream=width,height'
                        .' -of csv=s=x:p=0 '.escapeshellarg($file->getPathname())
                    );
                    if ($output) {
                        $parts = explode('x', trim($output));
                        if (count($parts) === 2) {
                            $width = (int) $parts[0];
                            $height = (int) $parts[1];
                        }
                    }
                } catch (\Exception $e) { /* keep 0×0 */
                }
            }

            // Remove existing file with same dimensions
            if ($width > 0 && $height > 0) {
                foreach ($creative->files()->where('width', $width)->where('height', $height)->get() as $old) {
                    Storage::disk('creatives')->delete($old->path);
                    $old->delete();
                }
            }

            // Build a safe random path, preserving the original extension
            $ext = strtolower($file->getClientOriginalExtension());
            $safePath = $creative->id.'/'.Str::random(40).'.'.$ext;

            if ($isImage) {
                // Re-encode through Intervention Image → strips all EXIF metadata
                $encoded = $manager->read($file->getPathname())->encodeByMediaType($mimeType);
                Storage::disk('creatives')->put($safePath, (string) $encoded);
            } else {
                // Videos: stream directly to avoid loading 50 MB into PHP memory
                $stream = fopen($file->getPathname(), 'rb');
                Storage::disk('creatives')->put($safePath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $creative->files()->create([
                'name' => $file->getClientOriginalName(),
                'width' => $width,
                'height' => $height,
                'path' => $safePath,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
            ]);

            $count++;
        }

        return back()->with('success', "$count file(s) uploaded successfully.");
    }

    public function deleteFile(\App\Models\CreativeFile $file)
    {
        $this->authorize('update', $file->creative->campaign);

        Storage::disk('creatives')->delete($file->path);
        $file->delete();

        return back()->with('success', 'File deleted successfully.');
    }

    public function preview(\App\Models\CreativeFile $file)
    {
        $this->authorize('view', $file->creative->campaign);

        if (! Storage::disk('creatives')->exists($file->path)) {
            abort(404);
        }

        // Use the MIME type recorded at upload time, not auto-detected at serve time.
        // Pair with nosniff so browsers cannot reclassify the response.
        return response()->file(
            Storage::disk('creatives')->path($file->path),
            [
                'Content-Type' => $file->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'",
                'Content-Disposition' => 'inline',
            ]
        );
    }

    public function downloadFile(\App\Models\CreativeFile $file)
    {
        $this->authorize('view', $file->creative->campaign);

        if (! Storage::disk('creatives')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('creatives')->download($file->path, $file->name);
    }

    public function downloadAll(Creative $creative)
    {
        $this->authorize('view', $creative->campaign);

        $files = $creative->files;

        if ($files->isEmpty()) {
            return back()->with('error', 'No files to download.');
        }

        // Use a private temp directory — never under storage/app/public (symlinked to webroot)
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0750, true);
        }

        $zipFileName = 'creative-'.$creative->id.'-'.Str::random(16).'.zip';
        $zipPath = $tempDir.'/'.$zipFileName;

        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create zip file.');
        }

        foreach ($files as $file) {
            if (Storage::disk('creatives')->exists($file->path)) {
                $zip->addFromString($file->name, Storage::disk('creatives')->get($file->path));
            }
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
