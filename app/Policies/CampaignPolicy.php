<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CampaignPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->hasPermission('is_admin')) return true;
        if (!$user->role_id) return $user->clients->contains($campaign->client_id);
        
        return $user->hasPermission('can_view_campaigns') && $user->clients->contains($campaign->client_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (!$user->role_id) return $user->hasPermission('is_admin');

        return $user->hasPermission('is_admin') || $user->hasPermission('can_edit_campaigns');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        if ($user->hasPermission('is_admin')) return true;
        if (!$user->role_id) return $user->clients->contains($campaign->client_id);

        return $user->hasPermission('can_edit_campaigns') && $user->clients->contains($campaign->client_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        if ($user->hasPermission('is_admin')) return true;
        if (!$user->role_id) return $user->clients->contains($campaign->client_id);

        return $user->hasPermission('can_edit_campaigns') && $user->clients->contains($campaign->client_id);
    }

    /**
     * Determine whether the user can edit the budget of the campaign.
     */
    public function editBudget(User $user): bool
    {
        return $user->hasPermission('is_admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Campaign $campaign): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return false;
    }
}
