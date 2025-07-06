<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'agency',
    ];

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
