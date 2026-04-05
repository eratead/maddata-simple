<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Role;
use App\Models\User;

/**
 * Shared privilege-escalation guard used by UserController,
 * AgencyUserController, and Admin/RoleController.
 *
 * Call preventPrivilegeEscalation($actingUser, $targetRole) before
 * persisting any role assignment to ensure the acting user cannot
 * grant permissions they do not themselves hold.
 */
trait PreventsPrivilegeEscalation
{
    /**
     * Abort 403 if the target role grants any permission that the acting
     * user does not hold.  Also aborts if the target role carries
     * `is_admin` and the acting user is not an admin.
     */
    protected function preventPrivilegeEscalation(User $actingUser, Role $targetRole): void
    {
        // Iterate every permission defined on the target role
        foreach ($targetRole->permissions ?? [] as $key => $granted) {
            if (! $granted) {
                continue;
            }

            if (! $actingUser->hasPermission($key)) {
                abort(403, "You cannot grant the '{$key}' permission.");
            }
        }

        // Also check legacy booleans that live outside the role JSON
        $legacyChecks = [
            'is_admin',
            'can_view_budget',
            'can_upload_reports',
            'can_manage_users',
            'can_manage_clients',
        ];

        foreach ($legacyChecks as $key) {
            if ($targetRole->hasPermission($key) && ! $actingUser->hasPermission($key)) {
                abort(403, "You cannot grant the '{$key}' permission.");
            }
        }
    }
}
