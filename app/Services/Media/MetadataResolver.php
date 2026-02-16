<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class MetadataResolver
{
    /**
     * Extrai metadados unificados de um arquivo e sua lista de acompanhantes (JSONs)
     */
    /**
     * Extrai metadados de um arquivo e tenta casar com um JSON (Sidecar) correspondente.
     */
    public function extract(UploadedFile $file, array $allFiles, array $params): array
    {
        $fileName = $file->getClientOriginalName();
        $galleryFallback = $params['galleryName'] ?? 'Geral';
        $eventFallback = $params['sourceEvent'] ?? 'Manual';

        $jsonFile = $this->findMatchingJson($fileName, $allFiles);

        // Se não houver JSON, cria um schema básico com os dados do upload
        if (!$jsonFile) {
            return $this->buildDefaultSchema($fileName, $galleryFallback, $eventFallback);
        }

        // 1. Lê o conteúdo de forma segura
        $rawContent = file_get_contents($jsonFile->getRealPath());

        // 2. Tenta decodificar. Se falhar, limpa caracteres UTF-8 inválidos
        $jsonContent = json_decode($rawContent, true);

        if (is_null($jsonContent)) {
            $cleanContent = mb_convert_encoding($rawContent, 'UTF-8', 'UTF-8');
            $jsonContent = json_decode($cleanContent, true);
        }

        // 3. Validação de integridade do JSON
        if (!is_array($jsonContent)) {
            Log::warning("MetadataResolver: Falha ao decodificar JSON para {$fileName}.");
            return $this->buildDefaultSchema($fileName, $galleryFallback, $eventFallback);
        }

        // 4. Caso Especial: O JSON já é o nosso formato 'canonical_sidecar'
        if (isset($jsonContent['canonical']) && isset($jsonContent['original_sidecar'])) {
            $originalMetadata = $jsonContent['original_sidecar']['metadata'] ?? [];

            // Captura o pHash de onde quer que ele esteja
            $originalPhash = $jsonContent['original_sidecar']['phash'] ?? ($jsonContent['original_sidecar']['technical']['phash'] ?? '');

            // Captura Geo: Tenta no nosso padrão, depois nos campos brutos do Google
            $geoData = $jsonContent['original_sidecar']['geo'] ?? ($jsonContent['original_sidecar']['location'] ?? ($jsonContent['geoData'] ?? ($jsonContent['geoDataExif'] ?? null)));

            $exifRaw = $jsonContent['original_sidecar']['Exif'] ?? ($jsonContent['original_sidecar']['exif'] ?? ($jsonContent['Exif'] ?? ($jsonContent['exif'] ?? [])));

            return [
                'type' => 'canonical_sidecar',
                'media_gallery' => $jsonContent['canonical']['media_gallery'] ?? $galleryFallback,
                'source_event' => $jsonContent['canonical']['source_event'] ?? $eventFallback,
                'title' => $jsonContent['canonical']['title'] ?? $fileName,
                'description' => $originalMetadata['description'] ?? '',
                'rating' => $jsonContent['canonical']['rating'] ?? ($originalMetadata['rating'] ?? 0),
                'taken_at' => $originalMetadata['taken_at'] ?? now()->format('Y-m-d H:i:s'),
                'faces' => $originalMetadata['face_detection']['data'] ?? [],
                'phash' => $originalPhash,
                'geo' => [
                    'latitude' => $geoData['latitude'] ?? 0.0,
                    'longitude' => $geoData['longitude'] ?? 0.0,
                    'altitude' => $geoData['altitude'] ?? 0.0,
                ],
                'exif' => [
                    'make' => $exifRaw['Make'] ?? ($exifRaw['make'] ?? 'Unknown'),
                    'model' => $exifRaw['Model'] ?? ($exifRaw['model'] ?? 'Unknown'),
                    'taken_at' => $exifRaw['DateTime'] ?? ($exifRaw['datetime_original'] ?? ($originalMetadata['taken_at'] ?? now()->format('Y-m-d H:i:s'))),
                ],
                'raw' => $jsonContent,
            ];
        }

        // 5. Caso Geral: Tenta identificar outros formatos (Google Fotos puro, etc)
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
                'rating' => $json['canonical']['rating'] ?? 0,
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
                'rating' => $json['rating'] ?? 0,
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
        // Remove extensão e limpa espaços/caracteres para uma comparação "suja"
        $cleanMedia = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($mediaName, PATHINFO_FILENAME));

        foreach ($allFiles as $name => $file) {
            if (!str_ends_with(strtolower($name), '.json')) {
                continue;
            }

            $cleanJson = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($name, PATHINFO_FILENAME));

            // Se o nome da imagem (sem extensão e sem lixo) estiver contido no nome do JSON
            if ($cleanMedia !== '' && str_contains($cleanJson, $cleanMedia)) {
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
            'rating' => 0,
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
                'rating' => $extracted['rating'] ?? 0,
                'taken_at' => $extracted['taken_at'] ?? now()->format('Y-m-d H:i:s'),
            ],
            'original_sidecar' => [
                'version' => 1,
                'source' => $extracted['type'] ?? 'manual_upload',
                'media_gallery' => $extracted['media_gallery'] ?? 'Geral',
                'source_event' => $extracted['source_event'] ?? 'Manual',
                'file_hash' => $fileHash,
                'phash' => $extracted['phash'] ?? '',
                'rating' => $extracted['rating'] ?? 0,
                'best_dist' => 6,
                'geo' => $extracted['geo'] ?? null, // Adicionado null coalescing por segurança
                'exif' => $extracted['exif'] ?? null,
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
