<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media_files'; // Nome da tabela no SQLite
    public $timestamps = true;

    protected $fillable = [
        'file_path',
        'file_hash',
        'phash',
        'similarity_score',
        'similar_to_id',
        'file_size',
        'file_extension',
        'mime_type',
        'media_gallery',
        'source_event',
        'title',
        'description',
        'rating',
        'is_private',
        'is_favorite',
        'face_scanned',
        'processed_face',

        // --- NOVOS CAMPOS (EXIF & GEO) ---
        'device_make',
        'device_model',
        'taken_at',
        'latitude',
        'longitude',
        'altitude',

        // --- CAMPOS DE SINCRONIZAÇÃO ---
        'is_synced',
        'synced_at',
        'remote_id',

        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_favorite' => 'boolean',
        'face_scanned' => 'boolean',
        'processed_face' => 'boolean',
        'is_synced' => 'boolean',
        'file_size' => 'integer',
        'similarity_score' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'altitude' => 'float',
        'taken_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    // Relacionamento com as Faces
    public function faces()
    {
        return $this->hasMany(Face::class, 'media_file_id');
    }
    // Fotos similares a esta
    public function similares()
    {
        return $this->hasMany(Media::class, 'similar_to_id', 'id');
    }

    // A foto original da qual esta é similar
    public function original()
    {
        return $this->belongsTo(Media::class, 'similar_to_id', 'id');
    }
    public function root()
    {
        $current = $this;

        while ($current->similar_to_id) {
            $current = Media::find($current->similar_to_id);
            if (!$current) {
                break;
            }
        }

        return $current;
    }
    // Retorna todas as cópias exatas (MD5) que apontam para este arquivo original
    public function copiasFisicas()
    {
        return $this->hasMany(CopiaExata::class, 'original_media_id', 'id');
    }
}
