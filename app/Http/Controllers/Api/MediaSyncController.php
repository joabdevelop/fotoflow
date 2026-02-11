<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use App\Services\MediaScannerService;
use Illuminate\Support\Facades\DB;

class MediaSyncController extends Controller
{
    private $hasher;

    public function __construct()
    {
        // Instancia o pHash no construtor
        $this->hasher = new ImageHash(new PerceptualHash());
    }

    public function store(Request $request, MediaScannerService $scanner)
    {
        // Pegamos apenas o nome do arquivo enviado pela extensão
        $filename = $request->input('filename');

        if (!$filename) {
            return response()->json(['error' => 'Nome do arquivo não fornecido'], 400);
        }

        // Chama o novo método especializado do Service
        $media = $scanner->scanFromExtension($filename);

        if (!$media) {
            return response()->json([
                'status' => 'erro/ignorado',
                'message' => 'Verifique se o arquivo está na pasta xtemp e se o PHP tem permissão.'
            ], 404);
        }

        return response()->json([
            'status' => 'sucesso',
            'id' => $media->id,
            'phash' => $media->perceptual_hash
        ], 201);
    }

    public function sync(Request $request)
    {
        // Validação rigorosa dos dados vindos do Service
        $request->validate([
            'media.file_hash' => 'required|string',
            'media.photo_gallery' => 'required|string',
            'faces' => 'array'
        ]);

        return DB::transaction(function () use ($request) {
            // 1. Criar ou atualizar a mídia (usando file_hash como chave única)
            $media = \App\Models\Media::updateOrCreate(
                ['file_hash' => $request->input('media.file_hash')],
                $request->input('media')
            );

            // 2. Sincronizar Faces (incluindo o parâmetro BestDist do seu .ini)
            if ($request->has('faces')) {
                foreach ($request->input('faces') as $faceData) {
                    $media->faces()->create([
                        'embedding'      => $faceData['embedding'],
                        'box'            => $faceData['box'],
                        'thumbnail_path' => $faceData['thumbnail_path'],
                        'best_dist'      => $faceData['best_dist'] ?? 0.6, // Valor padrão se não vier no JSON
                    ]);
                }
            }

            return response()->json([
                'status' => 'sucesso',
                'media_id' => $media->id,
                'sync_at' => now()->toDateTimeString()
            ], 201);
        });
    }
}
