<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaProcessing;
use App\Models\CopiaExata;
use App\Jobs\ProcessMediaJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ManualUploadController extends Controller
{
    /**
     * Exibe o formulário de upload
     */
    public function show()
    {
        return view('uploadManualMedia');
    }

    /**
     * Ponto de entrada para o processamento de múltiplos arquivos
     */
    public function processUpload(Request $request)
    {
        $allFiles = [];
        // Captura todos os arquivos (Mídias e JSONs)
        foreach ($request->file('mediaFiles') as $file) {
            $allFiles[$file->getClientOriginalName()] = $file;
        }

        $filePaths = json_decode($request->input('file_paths'), true);
        $galleryFallback = $request->input('gallery_fallback'); // O valor da tela

        foreach ($request->file('mediaFiles') as $file) {
            $fileName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());

            // Ignora arquivos JSON no loop principal (eles serão lidos dentro do processSingleFile)
            if ($extension === 'json') {
                continue;
            }

            // Tenta extrair o evento do caminho da pasta (ex: "Viagem/foto.jpg" -> "Viagem")
            $relativePath = $filePaths[$fileName] ?? '';
            $parts = explode('/', $relativePath);
            $folderName = count($parts) > 1 ? $parts[count($parts) - 2] : $galleryFallback;

            // CHAMA O PROCESSAMENTO
            $this->processSingleFile($file, $allFiles, [
                'galleryName' => $galleryFallback, // Sugestão da interface
                'sourceEvent' => $folderName, // Sugestão da pasta
            ]);
        }
    }

    //###############################################################################
    // Função aprimorada para processar um único arquivo, agora com suporte a thumbnails de face
    //###############################################################################
    private function processSingleFile(UploadedFile $file, array $allFiles, array $params)
    {
        $galleryName = $params['galleryName'];
        $sourceEvent = $params['sourceEvent'];
        $fileName = $file->getClientOriginalName();

        // 1. Hash e pasta inbound
        $fileHash = strtoupper(md5_file($file->getRealPath()));
        $tempFolder = 'inbound/' . now()->format('Ymd_His') . '_' . uniqid();
        $filePath = $file->storeAs($tempFolder, $fileName, 'public');

        // 2. JSON sidecar correspondente
        $jsonName = $fileName . '.json';
        $jsonFile = $allFiles[$jsonName] ?? null;

        // 3. Sidecar: COPIAR SEM ALTERAR
        if ($jsonFile) {
            $sidecarContent = file_get_contents($jsonFile->getRealPath());
            dd($sidecarContent);
        } else {
            // fallback mínimo
            $sidecarContent = json_encode(
                [
                    'version' => 1,
                    'source' => 'manual_upload',
                    'canonical' => [
                        'media_gallery' => $galleryName,
                        'source_event' => $sourceEvent,
                        'title' => $fileName,
                        'description' => '',
                        'taken_at' => now()->format('Y-m-d H:i:s'),
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            );
        }

        $sidecarPath = $tempFolder . '/metadata.json';
        Storage::disk('public')->put($sidecarPath, $sidecarContent);

        // 4. Registro para processamento
        return \App\Models\MediaProcessing::create([
            'file_hash' => $fileHash,
            'file_path' => $filePath,
            'sidecar_path' => $sidecarPath,
            'face_thumbnail_path' => '',
            'status' => 'pending',
            'best_dist' => 0,
            'attempts' => 0,
            'last_error' => null,
            'phash' => '',
            'status' => 'pending',
            'processing_started_at' => now(),
        ]);
    }

    /**
     * Cálculo de Hash Perceptual (aHash)
     */
    private function calculatePhash($path)
    {
        $img = @imagecreatefromstring(file_get_contents($path));
        if (!$img) {
            return null;
        }

        $imgSize = 32;
        $blocks = 8;
        $blockSize = $imgSize / $blocks; // 4

        // 1. Redimensiona para 32x32 (Igual ao Delphi Scaled.SetSize)
        $scaled = imagecreatetruecolor($imgSize, $imgSize);
        imagecopyresampled($scaled, $img, 0, 0, 0, 0, $imgSize, $imgSize, imagesx($img), imagesy($img));

        $blockAvg = array_fill(0, 64, 0);

        // 2. Calcula a média de cinza por blocos (BlockAvg)
        for ($y = 0; $y < $imgSize; $y++) {
            for ($x = 0; $x < $imgSize; $x++) {
                $rgb = imagecolorat($scaled, $x, $y);
                $r = ($rgb >> 16) & 0xff;
                $g = ($rgb >> 8) & 0xff;
                $b = $rgb & 0xff;

                // Fórmula de cinza do Delphi: (R*299 + G*587 + B*114) div 1000
                $gray = ($r * 299 + $g * 587 + $b * 114) / 1000;

                $bx = (int) ($x / $blockSize);
                $by = (int) ($y / $blockSize);
                $index = $by * $blocks + $bx;

                $blockAvg[$index] += $gray;
            }
        }

        // Média final de cada um dos 64 blocos
        $globalAvg = 0;
        for ($i = 0; $i < 64; $i++) {
            $blockAvg[$i] = $blockAvg[$i] / ($blockSize * $blockSize);
            $globalAvg += $blockAvg[$i];
        }

        // Média Global
        $globalAvg = $globalAvg / 64;

        // 3. Gera o Hash Binário (Igual ao Delphi IntToBin64)
        $hashBin = '';
        for ($i = 0; $i < 64; $i++) {
            // Importante: No Delphi você usou (UInt64(1) shl I), o que preenche da direita para esquerda
            // ou sequencialmente. Para manter a ordem binária idêntica:
            $hashBin .= $blockAvg[$i] >= $globalAvg ? '1' : '0';
        }

        // imagedestroy($img);
        // imagedestroy($scaled);

        // Se o seu banco espera HEX para o bit_count, converta aqui.
        // Se espera a string binária de 64 chars, retorne $hashBin direto.
        // Como o Delphi retorna IntToBin64, vamos manter binário:
        return $hashBin;
    }

    //###############################################################################
    // Função aprimorada para extrair metadados JSON
    //###############################################################################
    private function extractJsonMetadata($fileName, $jsonFile, $galleryFallback, $eventFallback)
    {
        // 1. Caso base: Se não houver JSON, usamos o que veio da interface/pasta
        $default = [
            'type' => 'manual',
            'canonical' => [
                'media_gallery' => $galleryFallback,
                'source_event' => $eventFallback,
                'title' => $fileName,
                'description' => null,
                'taken_at' => now()->format('Y-m-d H:i:s'),
                'face_detection' => ['processed' => false, 'data' => []],
            ],
            'raw_original' => null,
        ];

        if (!$jsonFile) {
            return $default;
        }

        $jsonContent = json_decode(file_get_contents($jsonFile->getRealPath()), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }

        // Tenta capturar faces de qualquer lugar (canonical ou raiz do JSON)
        $facesData = $jsonContent['canonical']['face_detection']['data'] ?? ($jsonContent['metadata']['face_detection']['data'] ?? ($jsonContent['faces'] ?? []));

        // --- PRIORIDADE 1: FORMATO CANONICAL (Seu script PowerShell) ---
        // Se o bloco 'canonical' existe, ele tem precedência TOTAL sobre a interface.
        if (isset($jsonContent['canonical'])) {
            return [
                'type' => 'canonical_sidecar',
                'canonical' => [
                    // Aqui está o segredo: priorizamos o valor interno do JSON
                    'media_gallery' => $jsonContent['canonical']['media_gallery'] ?? $galleryFallback,
                    'source_event' => $jsonContent['canonical']['source_event'] ?? $eventFallback,
                    'title' => $jsonContent['canonical']['title'] ?? $fileName,
                    'description' => $jsonContent['canonical']['description'] ?? null,
                    'taken_at' => $jsonContent['canonical']['taken_at'] ?? now()->format('Y-m-d H:i:s'),
                    'face_detection' => [
                        'processed' => !empty($facesData),
                        'data' => $facesData,
                    ],
                ],
                'raw_original' => $jsonContent,
            ];
        }

        // --- PRIORIDADE 2: GOOGLE FOTOS ---
        if (isset($jsonContent['googlePhotosOrigin'])) {
            return [
                'type' => 'google_photos',
                'canonical' => [
                    'media_gallery' => 'GoogleFotos',
                    'source_event' => $galleryFallback, // Usa a pasta como subevento
                    'title' => $jsonContent['title'] ?? $fileName,
                    'description' => $jsonContent['description'] ?? '',
                    'taken_at' => isset($jsonContent['photoTakenTime']['timestamp']) ? date('Y-m-d H:i:s', (int) $jsonContent['photoTakenTime']['timestamp']) : now()->format('Y-m-d H:i:s'),
                    'face_detection' => ['processed' => !empty($facesData), 'data' => $facesData],
                ],
                'raw_original' => $jsonContent,
            ];
        }

        // Se o JSON existir mas não tiver os formatos acima, retorna o default enriquecido com o raw
        $default['raw_original'] = $jsonContent;
        return $default;
    }
    // ###############################################################################
    // Função aprimorada para encontrar o JSON correspondente a uma mídia, usando lógica de prefixo
    // ###############################################################################

    private function findMatchingJson($mediaName, $jsonFiles)
    {
        $mediaNameLower = strtolower($mediaName);
        $mediaPureName = strtolower(pathinfo($mediaName, PATHINFO_FILENAME));

        foreach ($jsonFiles as $jsonItem) {
            $jsonNameLower = strtolower($jsonItem['name']);

            // 1. Match para seu formato próprio (_metadata.json)
            // Ex: hahorgx..._metadata.json começa com hahorgx...
            if (str_contains($jsonNameLower, '_metadata') && str_starts_with($jsonNameLower, $mediaPureName)) {
                return $jsonItem['file'];
            }

            // 2. Match para Google (truncado ou supplemental)
            // Ex: img_123.jpg.supplemental... começa com img_123.jpg
            if (str_starts_with($jsonNameLower, $mediaNameLower)) {
                return $jsonItem['file'];
            }

            // 3. Fallback para casos onde o Google remove a extensão da imagem no nome do JSON
            // Ex: img_123.json para a imagem img_123.jpg
            if (str_starts_with($jsonNameLower, $mediaPureName . '.json')) {
                return $jsonItem['file'];
            }
        }

        Log::warning("JSON não encontrado para mídia: {$mediaName}");
        return null;
    }
}
