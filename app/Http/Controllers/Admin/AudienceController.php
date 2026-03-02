<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Audience;
use Illuminate\Http\Request;
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
            'sub_category'  => 'nullable|string|max:255',
            'name'          => 'required|string|max:255',
            'estimated_users' => 'nullable|integer|min:0',
            'provider'      => 'nullable|string|max:255',
            'is_active'     => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sub_category'] = $validated['sub_category'] ?: $validated['main_category'];
        $validated['full_path'] = $this->buildFullPath($validated['main_category'], $validated['sub_category'], $validated['name']);

        Audience::create($validated);

        return redirect()->route('admin.audiences.index')->with('success', 'Audience created successfully.');
    }

    public function update(Request $request, Audience $audience)
    {
        $validated = $request->validate([
            'main_category' => 'required|string|max:255',
            'sub_category'  => 'nullable|string|max:255',
            'name'          => 'required|string|max:255',
            'estimated_users' => 'nullable|integer|min:0',
            'provider'      => 'nullable|string|max:255',
            'is_active'     => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sub_category'] = $validated['sub_category'] ?: $validated['main_category'];
        $validated['full_path'] = $this->buildFullPath($validated['main_category'], $validated['sub_category'], $validated['name']);

        $audience->update($validated);

        return redirect()->route('admin.audiences.index')->with('success', 'Audience updated successfully.');
    }

    public function destroy(Audience $audience)
    {
        $audience->delete();

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
            'file'     => ['required', 'file', 'mimes:xlsx,xls'],
            'provider' => 'nullable|string|max:255',
        ]);

        $collection = Excel::toCollection(null, $request->file('file'))->first();
        $provider = $request->input('provider') ?: null;

        $newCount = 0;
        $updatedCount = 0;

        foreach ($collection as $index => $row) {
            // Skip header row
            if ($index === 0) continue;

            // Skip rows with empty col A
            if (empty($row[0])) continue;

            $fullPath = trim($row[0]);
            $parts = array_map('trim', explode('>', $fullPath));

            // parts[0] = "Audience" (always discard)
            if (count($parts) === 4) {
                // depth 4: Audience > MainCategory > SubCategory > Name
                $mainCategory = $parts[1];
                $subCategory = $parts[2];
                $name = $parts[3];
            } elseif (count($parts) === 3) {
                // depth 3: Audience > MainCategory > Name (no sub)
                $mainCategory = $parts[1];
                $subCategory = $parts[1];
                $name = $parts[2];
            } else {
                continue;
            }

            $estimatedUsers = isset($row[1]) && is_numeric($row[1]) ? (int) $row[1] : null;

            $existing = Audience::where('full_path', $fullPath)->first();

            $fields = [
                'main_category'   => $mainCategory,
                'sub_category'    => $subCategory,
                'name'            => $name,
                'estimated_users' => $estimatedUsers,
                'is_active'       => true,
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
        return redirect()->back()->with('success', "Imported {$total} audiences ({$updatedCount} updated, {$newCount} new).");
    }
}
