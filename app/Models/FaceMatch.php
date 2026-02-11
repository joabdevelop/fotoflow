<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceMatch extends Model
{
    protected $table = 'face_matches'; // Verifique se o nome no banco é singular ou plural
    protected $fillable = ['face_id_1', 'face_id_2', 'distance', 'is_match'];
}
