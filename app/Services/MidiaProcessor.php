<?php

namespace App\Services\Media;

use App\Models\MediaProcessing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaProcessor
{
    protected $hasher;
    protected $resolver;

    public function __construct(MediaHasher $hasher, MetadataResolver $resolver)
    {
        $this->hasher = $hasher;
        $this->resolver = $resolver;
    }

    public function process(UploadedFile $file, array $allFiles, array $params)
    {
        $fileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();

        // 1. Gera o Hash MD5 (Identidade Única)
        $fileHash = $this->hasher->calculateMd5($file->getRealPath());

        // 2. Extrai e Normaliza Metadados
        $extracted = $this->resolver->extract($file, $allFiles, $params);

        // 3. Monta o JSON no formato padrão que você enviou
        $finalMetadata = $this->resolver->formatToStandard($extracted, $fileHash, $mimeType);

        // 4. Define pastas e caminhos (Inbound)
        $timestampFolder = now()->format('Ymd_His') . '_' . uniqid();
        $tempPath = "inbound/{$timestampFolder}";

        // Salva a Mídia
        $storedFilePath = $file->storeAs($tempPath, $fileName, 'public');

        // Salva o JSON estruturado (metadata.json)
        $sidecarPath = "{$tempPath}/metadata.json";
        Storage::disk('public')->put($sidecarPath, json_encode($finalMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 5. Cria o registro de processamento pendente
        return MediaProcessing::create([
            'file_hash' => $fileHash,
            'file_path' => $storedFilePath,
            'sidecar_path' => $sidecarPath,
            'phash' => $finalMetadata['original_sidecar']['phash'] ?? '', // Passa o phash (se houver)
            'best_dist' => $finalMetadata['original_sidecar']['best_dist'] ?? 6,
            'status' => 'pending',
            'face_thumbnail_path' => '', // Agora aceito se for nullable no banco
            'attempts' => 0,
            'processing_started_at' => now(),
        ]);
    }
}
