<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaProcessing extends Model
{
    protected $table = 'media_processings';
    public $timestamps = false; // Gerenciado pelo Delphi via created_at

    protected $fillable = [
        'file_hash',
        'file_path',
        'sidecar_path',
        'face_thumbnail_path',
        'status',
        'best_dist',
        'attempts',
        'last_error',
        'processing_started_at'
    ];


}
