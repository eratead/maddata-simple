<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_report',
        'receive_activity_notifications',
        'role_id',
    ];

    public function userRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasPermission($permissionKey): bool
    {
        // Legacy fallback
        if ($this->is_admin) {
            return true;
        }

        if (!$this->userRole) {
            if ($permissionKey === 'can_view_budget') {
                return (bool) $this->can_view_budget;
            }
            if ($permissionKey === 'can_upload_reports') {
                return (bool) $this->is_report;
            }
            return false;
        }

        // Admin override or specific permission check
        return (bool) ($this->userRole->hasPermission('is_admin') || $this->userRole->hasPermission($permissionKey));
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_admin' => 'boolean',
            'is_report' => 'boolean',
        ];
    }
}
