<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignLocation extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'lat',
        'lng',
        'radius_meters',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
