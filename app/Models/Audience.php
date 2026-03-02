<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audience extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'icon',
        'main_category',
        'sub_category',
        'name',
        'full_path',
        'estimated_users',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_audience');
    }
}
