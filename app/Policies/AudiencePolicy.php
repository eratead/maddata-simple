<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Audience;
use App\Models\User;

class AudiencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_clients');
    }

    public function view(User $user, Audience $audience): bool
    {
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_clients');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_clients');
    }

    public function update(User $user, Audience $audience): bool
    {
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_clients');
    }

    public function delete(User $user, Audience $audience): bool
    {
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_clients');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_clients');
    }
}
