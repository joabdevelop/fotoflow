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
            $canonical = $this->resolveCanonicalMetadata($request);
            $gallery = $canonical['media_gallery'] ?? 'Geral';

            $finalPath = $path;

            if (str_contains($path, 'inbound/')) {
                $filename = basename($path);
                $targetDir = "fotos/{$gallery}";
                $newLocation = "{$targetDir}/{$filename}";

                if (!Storage::disk('public')->exists($targetDir)) {
                    Storage::disk('public')->makeDirectory($targetDir);
                }

                if (Storage::disk('public')->exists($path)) {
                    if (Storage::disk('public')->move($path, $newLocation)) {
                        $oldFolder = dirname($path); // Pega o caminho da pasta (ex: inbound/hash_timestamp)
                        $finalPath = $newLocation;

                        // Deleta a pasta temporária do inbound se ela estiver vazia
                        if (str_contains($oldFolder, 'inbound/')) {
                            Storage::disk('public')->deleteDirectory($oldFolder);
                        }
                    }
                }
            }

            // 3. Verifica duplicata exata pelo HASH
            $existingMedia = \App\Models\Media::where('file_hash', $request->file_hash)->first();

            if ($existingMedia) {
                DB::table('copias_hash_exact')->insert([
                    'original_media_id' => $existingMedia->id,
                    'file_path' => $finalPath,
                    'file_name' => basename($finalPath),
                    'file_size' => $request->file_size,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Se for duplicata, removemos o arquivo (do novo local ou do inbound)
                if (Storage::disk('public')->exists($finalPath)) {
                    Storage::disk('public')->delete($finalPath);
                }

                $existingMedia->is_duplicate_upload = true;
                return $existingMedia;
            }

            // 4. Fluxo Normal: Criação do Registro
            $similar = $this->checkSimilarity($request);
            $mimeType = str_replace('image/jpg', 'image/jpeg', $request->mime_type);

            $phashHex = null;
            if ($request->phash) {
                $phashHex = str_pad(base_convert($request->phash, 2, 16), 16, '0', STR_PAD_LEFT);
            }

            // Criamos o modelo Media usando os campos que você listou (file_path, phash, etc)
            $media = \App\Models\Media::create(
                array_merge($canonical, [
                    'file_hash' => $request->file_hash,
                    'phash' => $phashHex,
                    'file_path' => $finalPath, // AQUI: deve ser fotos/midias/...
                    'file_size' => $request->file_size,
                    'file_extension' => $request->file_extension,
                    'mime_type' => $mimeType,
                    'source_event' => $request->watch_source_event ?? 'ManualUpload',
                    'similar_to_id' => $similar['id'] ?? null,
                    'similarity_score' => $similar['score'] ?? null,
                    'is_synced' => false,
                    'processed_face' => false,
                    'face_scanned' => false,
                    'is_favorite' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );

            $this->saveFinalSidecar($media, $request);

            return $media;
        });
    }
    /* ------------------------------------------ */
    /* CANONICAL METADATA */
    /* ------------------------------------------ */

    private function resolveCanonicalMetadata(Request $request)
    {
        // 1. Tenta pegar o JSON que veio no Request (enviado pelo Job)
        $sidecarRaw = $request->input('sidecar_json');
        $sidecar = $sidecarRaw ? json_decode($sidecarRaw, true) : [];

        // 2. REGRA DE OURO: Se o Job já mandou o 'watch_gallery' e 'watch_source_event',
        // nós DEVEMOS usar isso. Eles são a autoridade máxima.
        $gallery = $request->watch_gallery;
        $event = $request->watch_source_event;

        // Se por algum motivo estiverem vazios, aí sim tentamos o JSON interno
        if (!$gallery || $gallery === 'midias') {
            $gallery = $sidecar['media_gallery'] ?? ($sidecar['user']['media_gallery'] ?? 'Geral');
        }

        if (!$event || $event === 'midias') {
            $event = $sidecar['source_event'] ?? ($sidecar['source']['source_event'] ?? 'Raiz');
        }

        return [
            'media_gallery' => $gallery,
            'source_event' => $event,
            'title' => $sidecar['title'] ?? ($request->title ?? 'Sem título'),
            'description' => $sidecar['description'] ?? $request->description,
            'rating' => $sidecar['rating'] ?? 0,
            'timestamp' => $sidecar['taken_at'] ?? ($sidecar['timestamp'] ?? now()),
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

    private function saveFinalSidecar(Media $media, Request $request)
    {
        if (!$request->sidecar_json) {
            return;
        }

        $path = storage_path("app/public/{$media->file_path}.json");

        // O que o Job enviou como sidecar_json é o nosso "Canonical"
        $incomingCanonical = json_decode($request->sidecar_json, true);

        $finalJson = [
            'version' => 1,
            'media_id' => $media->id,
            'canonical' => [
                'media_gallery' => $media->media_gallery, // CatCafe
                'source_event' => $media->source_event, // Moto G-73
                'title' => $media->title,
                'description' => $media->description,
                'taken_at' => $media->timestamp,
            ],
            // AQUI ESTÁ O SEGREDO: Não jogue fora o que veio no Request
            'original_sidecar' => $incomingCanonical,
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
