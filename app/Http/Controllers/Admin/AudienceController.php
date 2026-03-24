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

        foreach ($collection as $index => $row) {
            // Skip header row
            if ($index === 0) {
                continue;
            }

            // Skip rows with empty col A
            if (empty($row[0])) {
                continue;
            }

            $fullPath = trim($row[0]);
            $parts = array_map('trim', explode('>', $fullPath));

            // parts[0] = "Audience" (always discard)
            // Minimum: Audience > Category > Name (3 parts)
            if (count($parts) < 3) {
                continue;
            }

            // Last part is always the name
            $name = array_pop($parts);
            // First part is "Audience" — discard
            array_shift($parts);
            // First remaining part is main_category
            $mainCategory = array_shift($parts);
            // Everything remaining (if any) joined as sub_category
            $subCategory = count($parts) > 0 ? implode(' > ', $parts) : $mainCategory;

            $estimatedUsers = isset($row[1]) && is_numeric($row[1]) ? (int) $row[1] : null;

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
}
