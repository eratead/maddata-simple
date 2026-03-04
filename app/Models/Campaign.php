<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'client_id',
        'expected_impressions',
        'budget',
        'is_video',
        'start_date',
        'end_date',
        'required_sizes',
        'creative_optimization',
        'status',
        'targeting_rules',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'creative_optimization' => 'boolean',
        'targeting_rules' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function data()
    {
        return $this->hasMany(CampaignData::class);
    }

    public function creatives()
    {
        return $this->hasMany(Creative::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function audiences()
    {
        return $this->belongsToMany(Audience::class, 'campaign_audience');
    }

    public function locations()
    {
        return $this->hasMany(CampaignLocation::class);
    }
}
