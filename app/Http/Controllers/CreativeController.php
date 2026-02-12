<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Campaign;
use App\Models\Creative;
use Illuminate\Support\Facades\Storage;

class CreativeController extends Controller
{
    public function create(Campaign $campaign)
    {
        return view('creatives.create', compact('campaign'));
    }

    public function store(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'landing' => 'required|url',
            'status' => 'required|boolean',
        ]);

        $creative = $campaign->creatives()->create($validated);

        return redirect()->route('creatives.edit', $creative)->with('success', 'Creative created successfully. You can now add files.');
    }

    public function edit(Creative $creative)
    {
        $creative->load('files');
        return view('creatives.edit', compact('creative'));
    }

    public function update(Request $request, Creative $creative)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'landing' => 'required|url',
            'status' => 'required|boolean',
        ]);

        $creative->update($validated);

        return redirect()->route('campaigns.edit', $creative->campaign)->with('success', 'Creative updated successfully.');
    }


    public function destroy(Creative $creative)
    {
        $campaign = $creative->campaign;
        $creative->delete();

        return redirect()->route('campaigns.edit', $campaign)->with('success', 'Creative deleted successfully.');
    }

    public function upload(Request $request, Creative $creative)
    {
        $request->validate([
            'files' => 'required',
            'files.*' => 'file|max:51200', // 50MB max per file
        ]);

        $uploadedFiles = $request->file('files');
        $count = 0;

        foreach ($uploadedFiles as $file) {
            $path = $file->store($creative->id, 'creatives');
            
            // Auto-detect dimensions
            $width = 0;
            $height = 0;
            
            if (str_starts_with($file->getMimeType(), 'image/')) {
                try {
                    $dimensions = getimagesize($file->getPathname());
                    if ($dimensions) {
                        $width = $dimensions[0];
                        $height = $dimensions[1];
                    }
                } catch (\Exception $e) {
                    // Ignore errors, keep 0x0
                }
            } elseif (str_starts_with($file->getMimeType(), 'video/')) {
                try {
                    $ffprobeCommand = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 " . escapeshellarg($file->getPathname());
                    $output = shell_exec($ffprobeCommand);
                    if ($output) {
                        $dimensions = explode('x', trim($output));
                        if (count($dimensions) == 2) {
                            $width = (int)$dimensions[0];
                            $height = (int)$dimensions[1];
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore errors, keep 0x0
                }
            }

            $creative->files()->create([
                'name' => $file->getClientOriginalName(),
                'width' => $width,
                'height' => $height,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $count++;
        }

        return back()->with('success', "$count file(s) uploaded successfully.");
    }

    public function deleteFile(\App\Models\CreativeFile $file)
    {
        \Illuminate\Support\Facades\Storage::disk('creatives')->delete($file->path);
        $file->delete();

        return back()->with('success', 'File deleted successfully.');
    }

    public function preview(\App\Models\CreativeFile $file)
    {
        if (!Storage::disk('creatives')->exists($file->path)) {
            abort(404);
        }

        return response()->file(Storage::disk('creatives')->path($file->path));
    }

    public function downloadFile(\App\Models\CreativeFile $file)
    {
        if (!Storage::disk('creatives')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('creatives')->download($file->path, $file->name);
    }

    public function downloadAll(Creative $creative)
    {
        $files = $creative->files;

        if ($files->isEmpty()) {
            return back()->with('error', 'No files to download.');
        }

        $zipFileName = 'creative-' . $creative->id . '-files-' . now()->timestamp . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName); // Temporarily store in public disk
        
        // Ensure directory exists
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                if (Storage::disk('creatives')->exists($file->path)) {
                    $content = Storage::disk('creatives')->get($file->path);
                    $zip->addFromString($file->name, $content);
                }
            }
            $zip->close();
        } else {
             return back()->with('error', 'Could not create zip file.');
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
