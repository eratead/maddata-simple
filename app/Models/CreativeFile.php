<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreativeFile extends Model
{
    protected $fillable = [
        'creative_id',
        'name',
        'width',
        'height',
        'path',
        'mime_type',
        'size',
    ];

    public function creative()
    {
        return $this->belongsTo(Creative::class);
    }
}
