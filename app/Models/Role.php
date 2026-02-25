<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'permissions',
        'sort_order',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public static function availablePermissions()
    {
        return [
            'is_admin' => 'Administrator',
            'can_view_campaigns' => 'Can View Campaigns',
            'can_edit_campaigns' => 'Can Create & Edit Campaigns',
            'can_view_budget' => 'Can View Budget',
            'can_upload_reports' => 'Reports Upload',
        ];
    }

    public function hasPermission($permissionKey): bool
    {
        return isset($this->permissions[$permissionKey]) && $this->permissions[$permissionKey] === true;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
