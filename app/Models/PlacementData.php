<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlacementData extends Model
{
    use HasFactory;
    protected $table = 'placements_data';
    protected $fillable = [
        'name',
        'campaign_id',
        'report_date',
        'impressions',
        'clicks',
        'visible_impressions',
        'uniques',
        'video_25',
        'video_50',
        'video_75',
        'video_100',
    ];
}
