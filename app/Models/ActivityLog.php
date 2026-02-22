<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'subject_type',
        'subject_id',
        'action',
        'description',
        'changes',
        'status',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
