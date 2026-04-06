<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Jobs\SendPasswordResetNotificationJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $with = ['userRole'];

    /**
     * Per-instance cache for hasPermission() results.
     * Populated on first call; invalidated on model update via boot().
     */
    protected array $permissionCache = [];

    /**
     * Flush the permission cache (e.g. when role/active state changes).
     */
    public function flushPermissionCache(): void
    {
        $this->permissionCache = [];
    }

    /**
     * Register model event listeners.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Invalidate permission cache whenever the model is about to be saved
        // so any in-process hasPermission() calls reflect the new state.
        static::updating(function (User $user): void {
            $user->flushPermissionCache();
        });
    }

    public function agencies()
    {
        return $this->belongsToMany(Agency::class)
            ->withPivot('access_all_clients')
            ->withTimestamps();
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    /**
     * Get all client IDs accessible to this user — combining:
     * - Direct client IDs from client_user pivot
     * - Client IDs belonging to agencies where access_all_clients is true
     */
    public function accessibleClientIds(): \Illuminate\Support\Collection
    {
        return once(function () {
            // Direct client access (from client_user pivot)
            $directIds = $this->clients()->pluck('clients.id');

            // Agency-based access — one query for all qualifying agencies
            $agencyIds = $this->agencies
                ->filter(fn ($agency) => $agency->pivot->access_all_clients)
                ->pluck('id');

            $agencyClientIds = $agencyIds->isNotEmpty()
                ? Client::whereIn('agency_id', $agencyIds)->pluck('id')
                : collect();

            return $directIds->merge($agencyClientIds)->unique()->values();
        });
    }

    /**
     * Get a query builder for all clients accessible to this user.
     */
    public function accessibleClients(): \Illuminate\Database\Eloquent\Builder
    {
        return Client::whereIn('id', $this->accessibleClientIds());
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'is_report',
        'receive_activity_notifications',
        'google2fa_secret',
    ];

    public function userRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasPermission($permissionKey): bool
    {
        if (array_key_exists($permissionKey, $this->permissionCache)) {
            return $this->permissionCache[$permissionKey];
        }

        // Disabled users have no permissions regardless of role or legacy flags
        if ($this->is_active === false) {
            return $this->permissionCache[$permissionKey] = false;
        }

        // Legacy fallback
        if ($this->is_admin) {
            return $this->permissionCache[$permissionKey] = true;
        }

        if (! $this->userRole) {
            if ($permissionKey === 'can_view_budget') {
                return $this->permissionCache[$permissionKey] = (bool) $this->can_view_budget;
            }
            if ($permissionKey === 'can_upload_reports') {
                return $this->permissionCache[$permissionKey] = (bool) $this->is_report;
            }

            return $this->permissionCache[$permissionKey] = false;
        }

        // Admin override or specific permission check
        $result = (bool) ($this->userRole->hasPermission('is_admin') || $this->userRole->hasPermission($permissionKey));

        return $this->permissionCache[$permissionKey] = $result;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'is_report' => 'boolean',
            'google2fa_secret' => 'encrypted', // auto encrypt/decrypt; column is text
        ];
    }

    /**
     * Scope: only active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: only disabled users.
     */
    public function scopeDisabled($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Check if user is a manager in a specific agency.
     */
    public function isManagerInAgency(Agency $agency): bool
    {
        return $this->agencies->contains($agency->id)
            && $this->hasPermission('can_manage_users');
    }

    /**
     * Get the single agency this manager manages (returns null if not a manager).
     */
    public function managedAgency(): ?Agency
    {
        if (! $this->hasPermission('can_manage_users')) {
            return null;
        }

        return $this->agencies->first();
    }

    /**
     * Validate single-agency constraint for users with can_manage_users permission.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateSingleAgencyConstraint(User $user, ?Role $role = null): void
    {
        $role = $role ?? $user->userRole;

        if (! $role || ! $role->hasPermission('can_manage_users')) {
            return;
        }

        // When attaching to an agency: user must not already belong to another agency
        if ($user->agencies()->count() > 0) {
            abort(422, 'Users with management permissions can only belong to one agency.');
        }
    }

    /**
     * Validate that a user in multiple agencies cannot be assigned a manager role.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public static function validateRoleAgencyConstraint(User $user, Role $role): void
    {
        if ($role->hasPermission('can_manage_users') && $user->agencies()->count() > 1) {
            abort(422, 'This user belongs to multiple agencies and cannot be assigned a manager role.');
        }
    }

    /**
     * Send the password reset notification via the queue so SMTP does not
     * block the HTTP response.
     */
    public function sendPasswordResetNotification($token): void
    {
        SendPasswordResetNotificationJob::dispatch($this, $token);
    }
}
