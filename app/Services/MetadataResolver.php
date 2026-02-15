<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class MetadataResolver
{
    /**
     * Extrai metadados unificados de um arquivo e sua lista de acompanhantes (JSONs)
     */
    public function extract(UploadedFile $file, array $allFiles, array $params): array
    {
        $fileName = $file->getClientOriginalName();
        $galleryFallback = $params['galleryName'] ?? 'Geral';
        $eventFallback = $params['sourceEvent'] ?? 'Manual';

        // 1. Tenta encontrar o arquivo JSON correspondente na lista de arquivos enviados
        $jsonFile = $this->findMatchingJson($fileName, $allFiles);

        // 2. Se não houver JSON, retorna o esquema padrão (Fallback)
        if (!$jsonFile) {
            return $this->buildDefaultSchema($fileName, $galleryFallback, $eventFallback);
        }

        // 3. Se houver JSON, decodifica e tenta identificar o formato
        $jsonContent = json_decode(file_get_contents($jsonFile->getRealPath()), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("MetadataResolver: JSON inválido para o arquivo {$fileName}");
            return $this->buildDefaultSchema($fileName, $galleryFallback, $eventFallback);
        }

        return $this->parseJsonByFormat($jsonContent, $fileName, $galleryFallback, $eventFallback);
    }

    /**
     * Lógica de prioridade para identificar o formato do JSON (Canonical vs Google vs Outros)
     */
    private function parseJsonByFormat(array $json, string $fileName, string $gFallback, string $eFallback): array
    {
        // Tenta capturar faces de possíveis locais comuns no JSON
        $facesData = $json['canonical']['face_detection']['data'] ?? ($json['metadata']['face_detection']['data'] ?? ($json['faces'] ?? []));

        // FORMATO 1: CANONICAL (Seu padrão definido)
        if (isset($json['canonical'])) {
            return [
                'type' => 'canonical_sidecar',
                'media_gallery' => $json['canonical']['media_gallery'] ?? $gFallback,
                'source_event' => $json['canonical']['source_event'] ?? $eFallback,
                'title' => $json['canonical']['title'] ?? $fileName,
                'description' => $json['canonical']['description'] ?? null,
                'taken_at' => $json['canonical']['taken_at'] ?? now()->format('Y-m-d H:i:s'),
                'faces' => $facesData,
                'raw' => $json,
            ];
        }

        // FORMATO 2: GOOGLE FOTOS
        if (isset($json['googlePhotosOrigin']) || isset($json['photoTakenTime'])) {
            return [
                'type' => 'google_photos',
                'media_gallery' => 'GoogleFotos',
                'source_event' => $gFallback, // Usa a pasta principal como sub-evento
                'title' => $json['title'] ?? $fileName,
                'description' => $json['description'] ?? '',
                'taken_at' => isset($json['photoTakenTime']['timestamp']) ? date('Y-m-d H:i:s', (int) $json['photoTakenTime']['timestamp']) : now()->format('Y-m-d H:i:s'),
                'faces' => $facesData,
                'raw' => $json,
            ];
        }

        // FALLBACK: JSON genérico
        return array_merge($this->buildDefaultSchema($fileName, $gFallback, $eFallback), ['raw' => $json]);
    }

    /**
     * Localiza o JSON correto baseado no nome do arquivo (prefixos e extensões)
     */
    private function findMatchingJson(string $mediaName, array $allFiles): ?UploadedFile
    {
        $mediaPureName = pathinfo($mediaName, PATHINFO_FILENAME);
        $mediaNameLower = strtolower($mediaName);
        $mediaPureLower = strtolower($mediaPureName);

        foreach ($allFiles as $name => $file) {
            $jsonNameLower = strtolower($name);
            if (!str_ends_with($jsonNameLower, '.json')) {
                continue;
            }

            // Match exato: imagem.jpg -> imagem.jpg.json
            if ($jsonNameLower === $mediaNameLower . '.json') {
                return $file;
            }

            // Match sem extensão: imagem.jpg -> imagem.json
            if ($jsonNameLower === $mediaPureLower . '.json') {
                return $file;
            }

            // Match com sufixo metadata: imagem_metadata.json
            if (str_contains($jsonNameLower, '_metadata') && str_starts_with($jsonNameLower, $mediaPureLower)) {
                return $file;
            }
        }
        return null;
    }

    private function buildDefaultSchema($fileName, $gallery, $event): array
    {
        return [
            'type' => 'manual_fallback',
            'media_gallery' => $gallery,
            'source_event' => $event,
            'title' => $fileName,
            'description' => null,
            'taken_at' => now()->format('Y-m-d H:i:s'),
            'faces' => [],
            'raw' => null,
        ];
    }

    /**
     * Formata os dados para o padrão final exigido, garantindo campos zerados se vazios.
     */
    public function formatToStandard(array $extracted, string $fileHash, string $mimeType): array
    {
        return [
            'version' => 1,
            'media_id' => null,
            'canonical' => [
                'media_gallery' => $extracted['media_gallery'] ?? 'Geral',
                'source_event' => $extracted['source_event'] ?? 'Manual',
                'title' => $extracted['title'] ?? '',
                'description' => $extracted['description'] ?? '',
            ],
            'original_sidecar' => [
                'version' => 1,
                'source' => $extracted['type'] ?? 'manual_upload',
                'media_gallery' => $extracted['media_gallery'] ?? 'Geral',
                'source_event' => $extracted['source_event'] ?? 'Manual',
                'file_hash' => $fileHash,
                'phash' => '',
                'best_dist' => 6,
                'metadata' => [
                    'description' => $extracted['description'] ?? '',
                    'taken_at' => $extracted['taken_at'] ?? now()->format('Y-m-d H:i:s'),
                    'title' => $extracted['title'] ?? '',
                    'face_detection' => [
                        'data' => !empty($extracted['faces'])
                            ? $extracted['faces']
                            : [
                                [
                                    'thumbnail_filename' => '',
                                    'name' => '',
                                    'bounding_box' => ['y' => 0, 'w' => 0, 'h' => 0, 'x' => 0],
                                ],
                            ],
                        'processed' => !empty($extracted['faces']),
                    ],
                ],
                'technical' => [
                    'mime' => $mimeType,
                    'phash' => '',
                    'width' => 0,
                    'height' => 0,
                ],
            ],
        ];
    }
}
