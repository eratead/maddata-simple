<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignData extends Model
{
    use HasFactory;
    protected $fillable = [
        'campaign_id',
        'impressions',
        'clicks',
        'visible_impressions',
        'uniques',
        'report_date',
        'video_25',
        'video_50',
        'video_75',
        'video_100',
    ];
}
