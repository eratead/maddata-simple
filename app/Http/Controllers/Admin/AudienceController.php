<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class AudienceController extends Controller
{
    public function index()
    {
        $audiences = Audience::orderBy('main_category')
            ->orderBy('sub_category')
            ->orderBy('name')
            ->get();

        $categories = Audience::select('main_category')
            ->distinct()
            ->orderBy('main_category')
            ->pluck('main_category');

        $subCategories = Audience::select('sub_category')
            ->distinct()
            ->whereColumn('sub_category', '!=', 'main_category')
            ->whereNotNull('sub_category')
            ->orderBy('sub_category')
            ->pluck('sub_category');

        $providers = Audience::select('provider')
            ->distinct()
            ->whereNotNull('provider')
            ->where('provider', '!=', '')
            ->orderBy('provider')
            ->pluck('provider');

        return view('admin.audiences.index', compact('audiences', 'categories', 'subCategories', 'providers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'main_category' => 'required|string|max:255',
            'sub_category' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'estimated_users' => 'nullable|integer|min:0',
            'provider' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sub_category'] = $validated['sub_category'] ?: $validated['main_category'];
        $validated['full_path'] = $this->buildFullPath($validated['main_category'], $validated['sub_category'], $validated['name']);

        Audience::create($validated);

        Cache::forget('active_audiences');

        return redirect()->route('admin.audiences.index')->with('success', 'Audience created successfully.');
    }

    public function update(Request $request, Audience $audience)
    {
        $validated = $request->validate([
            'main_category' => 'required|string|max:255',
            'sub_category' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'estimated_users' => 'nullable|integer|min:0',
            'provider' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sub_category'] = $validated['sub_category'] ?: $validated['main_category'];
        $validated['full_path'] = $this->buildFullPath($validated['main_category'], $validated['sub_category'], $validated['name']);

        $audience->update($validated);

        Cache::forget('active_audiences');

        return redirect()->route('admin.audiences.index')->with('success', 'Audience updated successfully.');
    }

    public function destroy(Audience $audience)
    {
        $audience->delete();

        Cache::forget('active_audiences');

        return redirect()->route('admin.audiences.index')->with('success', 'Audience deleted.');
    }

    private function buildFullPath(string $main, string $sub, string $name): string
    {
        if ($sub && $sub !== $main) {
            return "Audience > {$main} > {$sub} > {$name}";
        }

        return "Audience > {$main} > {$name}";
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'provider' => 'nullable|string|max:255',
        ]);

        $collection = Excel::toCollection(null, $request->file('file'))->first();
        $provider = $request->input('provider') ?: null;

        $newCount = 0;
        $updatedCount = 0;

        // Detect format: new format has "Category" in col A header, old format has segment path
        $header = $collection->first();
        $isNewFormat = $header && isset($header[0]) && str_contains(strtolower(trim($header[0])), 'category');

        foreach ($collection as $index => $row) {
            // Skip header row
            if ($index === 0) {
                continue;
            }

            if ($isNewFormat) {
                // New format: Col A = Category, Col B = Segment Name (full path), Col C = Active Unique Users
                if (empty($row[1])) {
                    continue;
                }

                $mainCategory = trim($row[0] ?? '');
                $segmentName = trim($row[1]);
                $estimatedUsers = isset($row[2]) && is_numeric($row[2]) ? (int) $row[2] : null;

                // Full path is the segment name as-is
                $fullPath = $segmentName;

                // Parse the segment path to extract sub_category and name
                $parts = array_map('trim', explode('>', $segmentName));

                // Name = last segment, Sub category = second-to-last segment
                $name = array_pop($parts);
                $subCategory = count($parts) > 0 ? end($parts) : $mainCategory;
            } else {
                // Old format: Col A = full path like "Audience > Category > Sub > Name", Col B = Active Unique Users
                if (empty($row[0])) {
                    continue;
                }

                $fullPath = trim($row[0]);
                $parts = array_map('trim', explode('>', $fullPath));

                if (count($parts) < 3) {
                    continue;
                }

                $name = array_pop($parts);
                array_shift($parts); // discard "Audience"
                $mainCategory = array_shift($parts);
                $subCategory = count($parts) > 0 ? implode(' > ', $parts) : $mainCategory;
                $estimatedUsers = isset($row[1]) && is_numeric($row[1]) ? (int) $row[1] : null;
            }

            if (empty($mainCategory) || empty($name)) {
                continue;
            }

            $existing = Audience::where('full_path', $fullPath)->first();

            $fields = [
                'main_category' => $mainCategory,
                'sub_category' => $subCategory,
                'name' => $name,
                'estimated_users' => $estimatedUsers,
                'is_active' => true,
            ];
            if ($provider !== null) {
                $fields['provider'] = $provider;
            }

            Audience::updateOrCreate(['full_path' => $fullPath], $fields);

            if ($existing) {
                $updatedCount++;
            } else {
                $newCount++;
            }
        }

        $total = $newCount + $updatedCount;

        Cache::forget('active_audiences');

        return redirect()->back()->with('success', "Imported {$total} audiences ({$updatedCount} updated, {$newCount} new).");
    }

    public function batchDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:audiences,id',
        ]);

        $count = Audience::whereIn('id', $request->ids)->delete();

        Cache::forget('active_audiences');

        return redirect()->back()->with('success', "{$count} audiences deleted.");
    }
}
