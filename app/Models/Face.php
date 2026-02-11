<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Face extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'media_file_id',
        'thumbnail_path',
        'x',
        'y',
        'w',
        'h',
        'embedding',
        'embedding_model',
        'face_hash'
    ];

    protected $casts = [
        'embedding' => 'array', // O Laravel transforma o JSONB do Postgres em Array
    ];

    public function getThumbnailUrlAttribute()
    {
        return Storage::url($this->thumbnail_path);
    }
}
