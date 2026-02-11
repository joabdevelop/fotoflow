<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaStoreService
{
    

    public function process(Request $request, string $path)
    {
        return DB::transaction(function () use ($request, $path) {

            // 1. Verifica duplicata exata pelo HASH
            $existingMedia = \App\Models\Media::where('file_hash', $request->file_hash)->first();

            if ($existingMedia) {
                // REGISTRA A CÓPIA
                DB::table('copias_hash_exact')->insert([
                    'original_media_id' => $existingMedia->id,
                    'file_path'         => $path, // Guardamos o caminho que ele teria
                    'file_name'         => basename($path),
                    'file_size'         => $request->file_size,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                // ELIMINA A MÍDIA DUPLICADA DO STORAGE (Economia de espaço)
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }

                // Retornamos o objeto original com um marcador de duplicata
                $existingMedia->is_duplicate_upload = true;
                return $existingMedia;
            }

            // 2. SE NÃO É DUPLICATA, SEGUE FLUXO NORMAL...
            $canonical = $this->resolveCanonicalMetadata($request);
            $similar = $this->checkSimilarity($request);
            $mimeType = str_replace('image/jpg', 'image/jpeg', $request->mime_type);

            $phashHex = null;
            if ($request->phash) {
                $phashHex = str_pad(base_convert($request->phash, 2, 16), 16, '0', STR_PAD_LEFT);
            }

            $media = \App\Models\Media::create(array_merge($canonical, [
                'file_hash'        => $request->file_hash,
                'file_path'        => $path,
                'phash'            => $phashHex,
                'similar_to_id'    => $similar['id'],
                'similarity_score' => $similar['score'],
                'file_size'        => $request->file_size,
                'file_extension'   => $request->file_extension,
                'mime_type'        => $mimeType,
                'is_synced'        => false,
                'processed_face'   => false,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]));

            $this->saveFinalSidecar($media, $request);

            return $media;
        });
    }
    /* ------------------------------------------ */
    /* CANONICAL METADATA */
    /* ------------------------------------------ */

    private function resolveCanonicalMetadata(Request $request)
    {
        $sidecarRaw = $request->input('sidecar_json')
            ?? $request->input('sidecar_raw');

        $sidecar = $sidecarRaw
            ? json_decode($sidecarRaw, true)
            : [];

        $titleRequest = $request->title;
        if (in_array($titleRequest, ['Raiz', '', null])) {
            $titleRequest = null;
        }

        $descriptionRequest = $request->description;
        if (in_array($descriptionRequest, ['Raiz', '', null])) {
            $descriptionRequest = null;
        }

        return [
            'media_gallery' =>
            $request->watch_gallery
                ?? data_get($sidecar, 'user.media_gallery')
                ?? 'Geral',

            'source_event' =>
            $request->watch_source_event
                ?? data_get($sidecar, 'source.source_event')
                ?? 'Raiz',

            'title' =>
            data_get($sidecar, 'source.title')
                ?? data_get($sidecar, 'title')
                ?? $titleRequest
                ?? 'Sem título',

            'description' =>
            data_get($sidecar, 'source.description')
                ?? data_get($sidecar, 'description')
                ?? $descriptionRequest,

            'rating' =>
            data_get($sidecar, 'user.rating')
                ?? $request->rating
                ?? 0,

            'timestamp' =>
            data_get($sidecar, 'source.timestamps.created')
                ?? data_get($sidecar, 'creationTime.timestamp'),

            'is_private' =>
            $request->boolean('is_private')
        ];
    }


    public function previewCanonical(Request $request)
    {
        return $this->resolveCanonicalMetadata($request);
    }



    /* ------------------------------------------ */
    /* SIDECAR */
    /* ------------------------------------------ */

    private function decodeSidecar(Request $request): array
    {
        if (!$request->sidecar_json) {
            return [];
        }

        return json_decode($request->sidecar_json, true) ?? [];
    }

    /* ------------------------------------------ */
    /* SIMILARIDADE */
    /* ------------------------------------------ */

    private function checkSimilarity(Request $request): array
    {
        if (!$request->phash || $request->phash === '0000000000000000') {
            return ['id' => null, 'score' => null];
        }

        $phashHex = str_pad(
            base_convert($request->phash, 2, 16),
            16,
            '0',
            STR_PAD_LEFT
        );

        $result = DB::select('CALL sp_find_similar_media(?, ?)', [
            $phashHex,
            $request->input('best_dist', 6)
        ]);

        if (!empty($result)) {
            return [
                'id' => $result[0]->id ?? null,
                'score' => $result[0]->dist ?? null
            ];
        }

        return ['id' => null, 'score' => null];
    }

    /* ------------------------------------------ */
    /* FINAL SIDECAR */
    /* ------------------------------------------ */

    private function saveFinalSidecar(Media $media, Request $request)
    {
        if (!$request->sidecar_json) {
            return;
        }

        $path = storage_path("app/public/{$media->file_path}.json");

        file_put_contents($path, json_encode([
            'version' => 1,
            'media_id' => $media->id,
            'canonical' => [
                'media_gallery' => $media->media_gallery,
                'source_event' => $media->source_event,
                'title' => $media->title,
                'description' => $media->description
            ],
            'original_sidecar' => json_decode($request->sidecar_json, true)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
