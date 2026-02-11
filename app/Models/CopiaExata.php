<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopiaExata extends Model
{
    protected $table = 'copias_hash_exact';
    public $timestamps = false; // Gerenciado pelo Delphi via created_at

    protected $fillable = [
        'original_media_id',
        'copia_media_id',
        'file_path',
        'file_name',
        'file_size'
    ];

    // Relacionamento com a mídia original
    public function original()
    {
        return $this->belongsTo(Media::class, 'original_media_id', 'id');
    }
}