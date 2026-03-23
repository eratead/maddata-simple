<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgencyRequest;
use App\Http\Requests\UpdateAgencyRequest;
use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::withCount('clients')->orderBy('name')->get();

        return view('admin.agencies.index', compact('agencies'));
    }

    public function create()
    {
        return view('admin.agencies.create');
    }

    public function store(StoreAgencyRequest $request)
    {
        $agency = DB::transaction(function () use ($request) {
            $agency = Agency::create([
                'name' => $request->validated('name'),
            ]);

            // Auto-create agency manager if manager fields are provided
            if ($request->filled('manager_name')) {
                $role = Role::firstOrCreate(
                    ['name' => 'Agency Manager'],
                    [
                        'permissions' => [
                            'can_manage_users' => true,
                            'can_manage_clients' => true,
                            'can_view_campaigns' => true,
                            'can_edit_campaigns' => true,
                            'can_view_budget' => true,
                        ],
                    ]
                );

                $user = User::create([
                    'name' => $request->validated('manager_name'),
                    'email' => $request->validated('manager_email'),
                    'password' => $request->validated('manager_password'),
                    'is_active' => true,
                ]);

                $user->role_id = $role->id;
                $user->save();

                $agency->users()->attach($user->id, ['access_all_clients' => true]);
            }

            return $agency;
        });

        app(ActivityLogger::class)->log('created', $agency, "Created agency \"{$agency->name}\"");

        $message = 'Agency created successfully.';
        if ($request->filled('manager_name')) {
            $message = 'Agency created with manager account for '.$request->validated('manager_email').'.';
        }

        return redirect()->route('admin.agencies.index')
            ->with('success', $message);
    }

    public function edit(Agency $agency)
    {
        $agency->loadCount('clients');

        return view('admin.agencies.edit', compact('agency'));
    }

    public function update(UpdateAgencyRequest $request, Agency $agency)
    {
        $oldName = $agency->name;
        $agency->update($request->validated());

        app(ActivityLogger::class)->log('updated', $agency, "Updated agency \"{$oldName}\"", [
            'old_name' => $oldName,
            'new_name' => $agency->name,
        ]);

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency updated successfully.');
    }

    public function destroy(Agency $agency)
    {
        if ($agency->clients()->count() > 0) {
            return back()->with('error', 'Cannot delete agency with active clients.');
        }

        app(ActivityLogger::class)->log('deleted', $agency, "Deleted agency \"{$agency->name}\"");

        $agency->delete();

        return redirect()->route('admin.agencies.index')
            ->with('success', 'Agency deleted successfully.');
    }
}
