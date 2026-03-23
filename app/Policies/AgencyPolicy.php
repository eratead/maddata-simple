<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

class AgencyPolicy
{
    /**
     * Can user manage this agency's users/clients?
     * Admin OR (user belongs to agency AND has can_manage_users permission).
     */
    public function manage(User $user, Agency $agency): bool
    {
        if ($user->hasPermission('is_admin')) {
            return true;
        }

        return $user->agencies->contains($agency->id)
            && $user->hasPermission('can_manage_users');
    }

    /**
     * Can user view this agency? (for controllers/viewers)
     * Admin OR user belongs to agency.
     */
    public function view(User $user, Agency $agency): bool
    {
        if ($user->hasPermission('is_admin')) {
            return true;
        }

        return $user->agencies->contains($agency->id);
    }
}
