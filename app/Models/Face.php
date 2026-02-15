<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Face extends Model
{
    protected $fillable = ['media_file_id', 'person_name', 'is_known', 'box', 'thumbnail_path', 'embedding', 'best_dist'];

    protected $casts = [
        'box' => 'array', // Crucial: Transforma o JSON do banco em array PHP
        'embedding' => 'array', // Crucial: Para os vetores da IA
        'is_known' => 'boolean',
        'best_dist' => 'float',
    ];

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_file_id');
    }

    public function getThumbnailUrlAttribute()
    {
        return Storage::url($this->thumbnail_path);
    }
}
