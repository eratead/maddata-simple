<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedStagingRoles extends Command
{
    protected $signature = 'seed:staging-roles';

    protected $description = 'Create roles and assign them to users for staging. Admin to users 1,2. Viewer Campaign + Budget to everyone else.';

    public function handle(): int
    {
        $this->info('Seeding roles for staging...');

        DB::transaction(function () {
            // Create Admin role
            $adminRole = Role::firstOrCreate(
                ['name' => 'Admin'],
                [
                    'permissions' => [
                        'is_admin' => true,
                        'can_view_campaigns' => true,
                        'can_edit_campaigns' => true,
                        'can_view_budget' => true,
                        'can_upload_reports' => true,
                        'can_see_logs' => true,
                        'can_manage_users' => true,
                        'can_manage_clients' => true,
                    ],
                    'sort_order' => 0,
                ]
            );
            $this->info("  Admin role: ID {$adminRole->id}");

            // Create Agency Manager role
            $managerRole = Role::firstOrCreate(
                ['name' => 'Agency Manager'],
                [
                    'permissions' => [
                        'can_manage_users' => true,
                        'can_manage_clients' => true,
                        'can_view_campaigns' => true,
                        'can_edit_campaigns' => true,
                        'can_view_budget' => true,
                    ],
                    'sort_order' => 1,
                ]
            );
            $this->info("  Agency Manager role: ID {$managerRole->id}");

            // Create Viewer Campaign + Budget role
            $viewerRole = Role::firstOrCreate(
                ['name' => 'Viewer Campaign + Budget'],
                [
                    'permissions' => [
                        'can_view_campaigns' => true,
                        'can_view_budget' => true,
                    ],
                    'sort_order' => 2,
                ]
            );
            $this->info("  Viewer Campaign + Budget role: ID {$viewerRole->id}");

            // Create Third Party Communicator role
            Role::firstOrCreate(
                ['name' => 'Third Party Communicator'],
                [
                    'permissions' => [
                        'can_upload_reports' => true,
                        'can_see_logs' => true,
                    ],
                    'sort_order' => 3,
                ]
            );

            // Assign Admin role to users 1 and 2
            $adminCount = User::whereIn('id', [1, 2])->update(['role_id' => $adminRole->id]);
            $this->info("  Assigned Admin role to {$adminCount} users (IDs 1, 2)");

            // Assign Viewer Campaign + Budget to all other users
            $viewerCount = User::whereNotIn('id', [1, 2])
                ->whereNull('role_id')
                ->update(['role_id' => $viewerRole->id]);
            $this->info("  Assigned Viewer Campaign + Budget to {$viewerCount} users");
        });

        $this->info('Done! Roles seeded successfully.');

        return self::SUCCESS;
    }
}
