<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;

class MediaScannerService
{
    private $hasher;

    public function __construct()
    {
        $this->hasher = new ImageHash(new PerceptualHash());
    }

    /**
     * Scaneia o arquivo e registra no banco apenas com hash de integridade (MD5).
     * O pHash foi removido temporariamente para otimizar a velocidade inicial.
     */
    public function scanFile($file)
    {
        // 1. Normalização do Caminho
        $path = ($file instanceof \Symfony\Component\Finder\SplFileInfo) ? $file->getRealPath() : $file;
        $path = str_replace('\\', '/', $path);

        $debugLog = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/media_scanner.log'),
        ]);

        try {
            if (!file_exists($path)) {
                $debugLog->warning("Arquivo ignorado (não existe): $path");
                return null;
            }

            $mime = mime_content_type($path);

            // Filtro básico para processar apenas imagens e vídeos
            if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
                return null;
            }

            // 2. Persistência no Banco de Dados
            // Usamos o path como chave única
            $media = Media::firstOrNew(['path' => $path]);

            $media->fill([
                'filename'        => basename($path),
                'extension'       => pathinfo($path, PATHINFO_EXTENSION),
                'mime_type'       => $mime,
                'size_bytes'      => filesize($path),
                'hash'            => md5_file($path), // Hash rápido de integridade
                'perceptual_hash' => null,            // Ficará nulo por enquanto
            ]);

            $media->save();

            $debugLog->info("ID: {$media->id} indexado com sucesso (apenas MD5).");

            return $media;
        } catch (\Exception $e) {
            $debugLog->error("Falha ao processar " . basename($path) . ": " . $e->getMessage());
            return null;
        }
    }

    // Dentro da classe MediaScannerService
    public function calculateImagePHash($path)
    {
        // Usa o hasher que você já configurou no construtor
        return $this->hasher->hash($path)->toHex();
    }

    /**
     * Processa um arquivo vindo especificamente da extensão do navegador.
     * Localiza o arquivo na pasta xtemp e gera o pHash imediatamente.
     */
    public function scanFromExtension($filename)
    {
        $basePath = 'C:/Users/jcfab/Pictures/xtemp';
        $fullPath = $basePath . '/' . basename($filename);
        $tempFramePath = storage_path('app/temp_frame_' . time() . '.jpg');

        $debugLog = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/extension_sync.log'),
        ]);

        try {
            clearstatcache(true, $fullPath);

            if (!file_exists($fullPath)) {
                $debugLog->warning("Extensão: Arquivo não encontrado: $fullPath");
                return null;
            }

            $mime = mime_content_type($fullPath);
            $media = Media::firstOrNew(['path' => $fullPath]);

            $isImage = str_starts_with($mime, 'image/');
            $isVideo = str_starts_with($mime, 'video/');

            if (!$isImage && !$isVideo) return null;

            $media->fill([
                'filename'   => basename($fullPath),
                'extension'  => pathinfo($fullPath, PATHINFO_EXTENSION),
                'mime_type'  => $mime,
                'size_bytes' => filesize($fullPath),
                'hash'       => md5_file($fullPath),
            ]);

            // --- LÓGICA DE PHASH ---
            if ($isImage) {
                $media->perceptual_hash = $this->calculateImagePHash($fullPath);
            } elseif ($isVideo) {
                $debugLog->info("Extraindo frame do vídeo para pHash: " . $media->filename);

                // SUBSTITUA PELO CAMINHO QUE VOCÊ COPIOU NO PASSO 1
               $ffmpegPath = 'C:\yt-dlp\ffmpeg.exe';

                // Usamos escapeshellarg para proteger os caminhos de arquivos com espaços
                $input = escapeshellarg($fullPath);
                $outputFile = escapeshellarg($tempFramePath);

                $command = "\"$ffmpegPath\" -y -ss 00:00:01 -i $input -vframes 1 -q:v 2 $outputFile 2>&1";

                exec($command, $output, $returnVar);

                if ($returnVar === 0 && file_exists($tempFramePath)) {
                    $media->perceptual_hash = $this->calculateImagePHash($tempFramePath);
                    unlink($tempFramePath);
                } else {
                    $debugLog->error("FFmpeg falhou: " . implode(" ", $output));
                    $media->perceptual_hash = null;
                }
            }

            $media->save();
            $debugLog->info("Sincronizado: {$media->filename} | pHash: {$media->perceptual_hash}");

            return $media;
        } catch (\Exception $e) {
            if (file_exists($tempFramePath)) unlink($tempFramePath);
            $debugLog->error("Erro no processamento: " . $e->getMessage());
            return null;
        }
    }
}
