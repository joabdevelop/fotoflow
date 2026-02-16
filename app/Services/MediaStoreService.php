<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Face;
use App\Models\MediaProcessing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaStoreService
{
    public function process(Request $request, string $path)
    {
        // 1. Prepara o Sidecar Completo vindo do Job
        $sidecarFull = $request->input('sidecar_full') ?? [];
        $original = $sidecarFull['original_sidecar'] ?? [];

        return DB::transaction(function () use ($request, $path, $sidecarFull, $original) {
            $canonical = $this->resolveCanonicalMetadata($request);
            $gallery = $canonical['media_gallery'] ?? 'Geral';

            $finalPath = $path;

            // --- LÓGICA DE MOVIMENTAÇÃO DE ARQUIVO ---
            if (str_contains($path, 'inbound/')) {
                $filename = basename($path);
                $targetDir = "fotos/{$gallery}";
                $newLocation = "{$targetDir}/{$filename}";

                if (!Storage::disk('public')->exists($targetDir)) {
                    Storage::disk('public')->makeDirectory($targetDir);
                }

                if (Storage::disk('public')->exists($path)) {
                    if (Storage::disk('public')->move($path, $newLocation)) {
                        $oldFolder = dirname($path);
                        $finalPath = $newLocation;
                        if (str_contains($oldFolder, 'inbound/')) {
                            Storage::disk('public')->deleteDirectory($oldFolder);
                        }
                    }
                }
            }

            // --- VERIFICAÇÃO DE DUPLICATA EXATA ---
            $existingMedia = Media::where('file_hash', $request->file_hash)->first();
            if ($existingMedia) {
                DB::table('copias_hash_exact')->insert([
                    'original_media_id' => $existingMedia->id,
                    'file_path' => $finalPath,
                    'file_name' => basename($finalPath),
                    'file_size' => $request->file_size,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (Storage::disk('public')->exists($finalPath)) {
                    Storage::disk('public')->delete($finalPath);
                }
                return $existingMedia;
            }

            // --- LÓGICA DE SIMILARIDADE E PHASH ---
            $similar = $this->checkSimilarity($request);
            $mimeType = str_replace('image/jpg', 'image/jpeg', $request->mime_type);

            $phashRaw = $request->phash ?? ($original['phash'] ?? null);
            $phashHex = $phashRaw ? substr($phashRaw, 0, 64) : null;

            // --- CRIAÇÃO DO REGISTRO (COM CORREÇÃO PARA "GEO" E "EXIF") ---
            $media = Media::create(
                array_merge($canonical, [
                    'file_hash' => $request->file_hash,
                    'phash' => $phashHex,
                    'file_path' => $finalPath,
                    'file_size' => $request->file_size,
                    'file_extension' => $request->file_extension,
                    'mime_type' => $mimeType,
                    'source_event' => $request->watch_source_event ?? ($original['source_event'] ?? 'ManualUpload'),

                    // Ajuste de Case (No seu JSON veio 'Make' e 'Model' com maiúsculo)
                    'device_make' => $original['exif']['Make'] ?? ($original['exif']['make'] ?? null),
                    'device_model' => $original['exif']['Model'] ?? ($original['exif']['model'] ?? null),

                    // Pegando a data resolvida pelo canonical
                    'taken_at' => $canonical['timestamp'] ?? now(),

                    // CORREÇÃO DO ERRO "GEO": Usando ?? null para o nível 'geo'
                    'latitude' => $original['geo']['latitude'] ?? null,
                    'longitude' => $original['geo']['longitude'] ?? null,
                    'altitude' => $original['geo']['altitude'] ?? 0,

                    'similar_to_id' => $similar['id'] ?? null,
                    'similarity_score' => $similar['score'] ?? null,
                    'is_synced' => false,
                    'processed_face' => !empty($original['metadata']['face_detection']['data']),
                    'face_scanned' => true,
                    'is_favorite' => false,
                ]),
            );

            // --- SALVAMENTO DE FACES ---
            $faces = $original['metadata']['face_detection']['data'] ?? [];
            $jobBestDist = $original['best_dist'] ?? null;

            foreach ($faces as $faceData) {
                $box = $faceData['bounding_box'] ?? [];

                \App\Models\Face::create([
                    'media_file_id' => $media->id,
                    'thumbnail_path' => $faceData['thumbnail_filename'] ?? null,
                    'best_dist' => $faceData['best_dist'] ?? ($faceData['distance'] ?? $jobBestDist),
                    'person_name' => $faceData['name'] ?? null,
                    'box' => [
                        'x' => $box['x'] ?? 0,
                        'y' => $box['y'] ?? 0,
                        'w' => $box['w'] ?? 0,
                        'h' => $box['h'] ?? 0,
                    ],
                    'face_hash' => md5($faceData['thumbnail_filename'] ?? uniqid()),
                    'embedding' => [],
                ]);
            }

            $this->saveFinalSidecar($media, $sidecarFull);

            return $media;
        });
    }

    /**
     * Move o arquivo da inbound para a estrutura de pastas: public/media/{gallery}/{event}/{filename}
     */
    /**
     * Move o arquivo da inbound para a pasta final e garante o Sidecar JSON
     */
    private function moveFilesToFinalDestination($mediaFile, $tempPath)
    {
        $fileName = basename($tempPath); // Nome original da imagem
        $destinationDir = "fotos/{$mediaFile->media_gallery}"; // Ex: fotos/Pessoal
        $destinationPath = "{$destinationDir}/{$fileName}";

        // 1. Garante que a pasta final existe
        if (!Storage::disk('public')->exists($destinationDir)) {
            Storage::disk('public')->makeDirectory($destinationDir);
        }

        // 2. Move a Imagem Principal
        if (Storage::disk('public')->exists($tempPath)) {
            Storage::disk('public')->move($tempPath, $destinationPath);
        }

        // 3. Deleta a pasta temporária do Inbound
        // O metadata.json que estava lá será substituído pelo "Final Sidecar"
        // gerado pelo método saveFinalSidecar que escreve direto na pasta fotos/Pessoal
        $tempDir = dirname($tempPath);
        Storage::disk('public')->deleteDirectory($tempDir);

        return $destinationPath;
    }

    /* ------------------------------------------ */
    /* CANONICAL METADATA */
    /* ------------------------------------------ */

    private function resolveCanonicalMetadata(Request $request)
    {
        $sidecarFull = $request->input('sidecar_full') ?? [];
        $original = $sidecarFull['original_sidecar'] ?? [];
        $metadata = $original['metadata'] ?? [];
        // Tenta pegar do canonical do sidecar, se não tiver, tenta do original_sidecar
        $rating = $sidecarFull['rating'] ?? ($sidecarFull['canonical']['rating'] ?? ($sidecarFull['original_sidecar']['rating'] ?? ($sidecarFull['original_sidecar']['Rating'] ?? 0)));

        $original = $sidecarFull['original_sidecar'] ?? $sidecarFull;
        $metadata = $original['metadata'] ?? [];

        return [
            'media_gallery' => $request->watch_gallery ?? ($sidecarFull['canonical']['media_gallery'] ?? 'Geral'),
            'source_event' => $request->watch_source_event ?? ($sidecarFull['canonical']['source_event'] ?? 'Manual'),
            'title' => $sidecarFull['canonical']['title'] ?? ($metadata['title'] ?? 'Sem título'),
            'description' => $sidecarFull['canonical']['description'] ?? ($metadata['description'] ?? null),
            'rating' => (int) $rating, // GARANTE QUE É UM INTEIRO
            'timestamp' => $metadata['taken_at'] ?? ($original['exif']['DateTime'] ?? now()),
            'is_private' => $request->boolean('is_private'),
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

        $phashHex = str_pad(base_convert($request->phash, 2, 16), 16, '0', STR_PAD_LEFT);

        $result = DB::select('CALL sp_find_similar_media(?, ?)', [$phashHex, $request->input('best_dist', 6)]);

        if (!empty($result)) {
            return [
                'id' => $result[0]->id ?? null,
                'score' => $result[0]->dist ?? null,
            ];
        }

        return ['id' => null, 'score' => null];
    }

    /* ------------------------------------------ */
    /* FINAL SIDECAR */
    /* ------------------------------------------ */
    // Mude de (Media $media, Request $request) para:
    private function saveFinalSidecar(Media $media, array $sidecarFull)
    {
        $path = storage_path("app/public/{$media->file_path}.json");

        $finalJson = [
            'version' => 1,
            'media_id' => $media->id,
            'canonical' => [
                'media_gallery' => $media->media_gallery,
                'source_event' => $media->source_event,
                'title' => $media->title, // Agora virá do banco corrigido
                'description' => $media->description, // Agora virá do banco corrigido
                'taken_at' => $media->taken_at,
                'rating' => $media->rating, // Adicione esta linha se quiser no JSON final
            ],
            'original_sidecar' => $sidecarFull['original_sidecar'] ?? $sidecarFull,
        ];

        file_put_contents($path, json_encode($finalJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    // No seu MediaStoreService.php
    public function processFromInbound($data, string $path)
    {
        // Criamos um Request "fake" populado com os dados do arquivo no inbound
        $request = new Request($data);
        return $this->process($request, $path);
    }
    // Exemplo de lógica para processar o que está no inbound
    public function handleInbound()
    {
        $pending = MediaProcessing::where('status', 'pending')->get();

        foreach ($pending as $item) {
            // 1. Carrega o metadata.json que salvamos na pasta
            $jsonContent = Storage::disk('public')->get($item->sidecar_path);
            $meta = json_decode($jsonContent, true);

            // 2. Prepara os dados para o MediaStoreService
            $data = [
                'file_hash' => $item->file_hash,
                'phash' => $item->phash,
                'file_size' => Storage::disk('public')->size($item->file_path),
                'file_extension' => pathinfo($item->file_path, PATHINFO_EXTENSION),
                'mime_type' => Storage::disk('public')->mimeType($item->file_path),
                'sidecar_json' => json_encode($meta['metadata'] ?? []),
                'watch_gallery' => $meta['media_gallery'] ?? 'Manual',
                'watch_source_event' => $meta['source_event'] ?? 'Upload',
                // Adicione outros campos que o seu resolveCanonicalMetadata espera
            ];

            // 3. Chama o serviço unificado
            try {
                $mediaStoreService = app(MediaStoreService::class);
                $result = $mediaStoreService->processFromInbound($data, $item->file_path);

                // 4. Se deu certo, atualiza o status
                $item->update(['status' => 'completed']);
                Log::info('Arquivo processado via serviço unificado: ' . $item->file_path);
            } catch (\Exception $e) {
                $item->update(['status' => 'error']);
                Log::error('Erro ao processar inbound: ' . $e->getMessage());
            }
        }
    }
}
