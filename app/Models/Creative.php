<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Creative extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'landing',
        'status',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function files()
    {
        return $this->hasMany(CreativeFile::class);
    }
}
