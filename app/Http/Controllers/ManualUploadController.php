<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaProcessing;
use App\Models\CopiaExata;
use App\Jobs\ProcessMediaJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
        $files = $request->file('mediaFiles');
        $galleryBase = $request->input('media_gallery', 'Manual');

        // Decodifica os caminhos enviados pelo JS
        $filePaths = json_decode($request->input('file_paths'), true) ?? [];

        if (!$files) return back()->with('error', 'Arquivos não encontrados.');

        $allFiles = [];
        $jsonFiles = [];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'json') {
                // Armazenamos o arquivo JSON
                $jsonFiles[] = [
                    'name' => $originalName,
                    'file' => $file
                ];
            } else {
                $allFiles[$originalName] = $file;
            }
        }
        foreach ($allFiles as $name => $file) {
            if (str_contains($name, '_thumb')) continue;

            // 1. Pega o caminho do JS para definir a galeria/evento
            $relativePath = $filePaths[$name] ?? '';

          
            // Normaliza barras para garantir que o explode funcione em qualquer SO
            $relativePath = str_replace('\\', '/', $relativePath);
            $parts = explode('/', $relativePath);
            $detectedGallery = (count($parts) >= 3) ? $parts[0] : $galleryBase;
            $detectedSource  = (count($parts) >= 3) ? $parts[1] : 'Manual Upload';

            if (count($parts) > 1) {
                $detectedGallery = $parts[0]; // Ex: "Viagem Manaus"
                // Se houver uma subpasta, usa ela. Se não, usa o nome da galeria como evento
                $detectedSource  = (count($parts) > 2) ? $parts[1] : $parts[0];
            }

            // 2. BUSCA O JSON (Crucial: Usando a função de prefixo que sugeri)
            $jsonFile = $this->findMatchingJson($name, $jsonFiles);

            // 3. Extrai os metadados (Passando o arquivo JSON encontrado ou null)
            $metaData = $this->extractJsonMetadata($name, $jsonFile, $detectedGallery, $detectedSource);

            // 4. Salva no inbound e registra
            $this->processSingleFile($file, $metaData, $allFiles);
        }

        return back()->with('success', 'Importação enviada para processamento.');
    }

    //###############################################################################
    // Função aprimorada para processar um único arquivo, agora com suporte a thumbnails de face
    //###############################################################################
    private function processSingleFile($file, $metaData, $allFiles)
    {
        $fileHash = hash_file('md5', $file->getRealPath());

        // 1. Checagem de Duplicata
        $existente = Media::where('file_hash', $fileHash)->first();
        if ($existente) {
            CopiaExata::create([
                'original_media_id' => $existente->id,
                'file_path' => $file->getRealPath(),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);
            return;
        }

        $phash = $this->calculatePhash($file->getRealPath());
        $folder = "inbound/{$fileHash}_" . now()->timestamp;
        $mediaPath = $file->storeAs($folder, $file->getClientOriginalName(), 'public');

        // 2. TRATAMENTO DE FACE THUMBNAIL
        $faceThumbPath = '';
        $faces = $metaData['canonical']['face_detection']['data'] ?? [];
        foreach ($faces as $face) {
            $thumbName = $face['thumbnail_filename'] ?? null;
            if ($thumbName && isset($allFiles[$thumbName])) {
                $faceThumbPath = $allFiles[$thumbName]->storeAs($folder, $thumbName, 'public');
            }
        }

        // 3. Criação do Sidecar (Usando a chave correta media_gallery)
        $sidecarData = [
            'source'        => 'manual_upload',
            'media_gallery' => $metaData['canonical']['media_gallery'] ?? 'Manual',
            'file_hash'     => $fileHash,
            'phash'         => $phash,
            'metadata'      => $metaData['canonical'],
            'original_raw'  => $metaData['raw_original'] ?? []
        ];

        $sidecarPath = "{$folder}/metadata.json";
        Storage::disk('public')->put($sidecarPath, json_encode($sidecarData, JSON_PRETTY_PRINT));

        // 4. Registro na Fila (Apenas no Banco por enquanto)
        $processing = MediaProcessing::updateOrCreate(
            ['file_hash' => $fileHash],
            [
                'file_path'    => $mediaPath,
                'sidecar_path' => $sidecarPath,
                'status'       => 'pending',
                'best_dist'    => 6, // [cite: 2026-01-14]
                'face_thumbnail_path' => $faceThumbPath,
                'phash'        => $phash
            ]
        );

        Log::info("Upload Teste: {$file->getClientOriginalName()} para Galeria: {$sidecarData['media_gallery']}");
    }

    /**
     * Cálculo de Hash Perceptual (aHash)
     */
    private function calculatePhash($path)
    {
        $img = @imagecreatefromstring(file_get_contents($path));
        if (!$img) return null;

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
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Fórmula de cinza do Delphi: (R*299 + G*587 + B*114) div 1000
                $gray = ($r * 299 + $g * 587 + $b * 114) / 1000;

                $bx = (int)($x / $blockSize);
                $by = (int)($y / $blockSize);
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
        $hashBin = "";
        for ($i = 0; $i < 64; $i++) {
            // Importante: No Delphi você usou (UInt64(1) shl I), o que preenche da direita para esquerda
            // ou sequencialmente. Para manter a ordem binária idêntica:
            $hashBin .= ($blockAvg[$i] >= $globalAvg) ? "1" : "0";
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
 private function extractJsonMetadata($fileName, $jsonFile, $galleryName, $sourceEvent)
{
    $default = [
        'type' => 'manual',
        'canonical' => [
            'media_gallery' => $galleryName,
            'source_event'  => $sourceEvent,
            'description'   => null,
            'taken_at'      => now()->format('Y-m-d H:i:s'),
            'face_detection' => ['processed' => false, 'data' => []]
        ],
        'raw_original' => null
    ];

    if (!$jsonFile) return $default;

    $jsonContent = json_decode(file_get_contents($jsonFile->getRealPath()), true);
    if (json_last_error() !== JSON_ERROR_NONE) return $default;

    $facesData = $jsonContent['canonical']['face_detection']['data'] ?? $jsonContent['faces'] ?? [];

    // --- 1. DETECÇÃO GOOGLE FOTOS (Via Chave googlePhotosOrigin) ---
    if (isset($jsonContent['googlePhotosOrigin'])) {
        return [
            'type' => 'google_photos',
            'canonical' => [
                'media_gallery' => 'GoogleFotos', // Forçamos conforme sua regra
                'source_event'  => $galleryName,  // Usamos a pasta de upload (ex: Viagem Manaus)
                'title'         => $jsonContent['title'] ?? $fileName,
                'description'   => $jsonContent['description'] ?? "",
                'taken_at'      => isset($jsonContent['photoTakenTime']['timestamp'])
                    ? date('Y-m-d H:i:s', (int)$jsonContent['photoTakenTime']['timestamp'])
                    : now()->format('Y-m-d H:i:s'),
                'face_detection' => [
                    'processed' => !empty($facesData),
                    'data'      => $facesData
                ]
            ],
            'raw_original' => $jsonContent
        ];
    }

    // --- 2. DETECÇÃO SIDECAR PRÓPRIO (X / INTERNO) ---
    if (isset($jsonContent['user']['media_gallery']) || isset($jsonContent['source']['source_event'])) {
        return [
            'type' => 'internal_format',
            'canonical' => [
                'media_gallery' => $jsonContent['user']['media_gallery'] ?? $galleryName,
                'source_event'  => $jsonContent['source']['source_event'] ?? $sourceEvent,
                'description'   => $jsonContent['source']['description'] ?? null,
                'taken_at'      => isset($jsonContent['source']['timestamps']['created'])
                    ? date('Y-m-d H:i:s', (int)$jsonContent['source']['timestamps']['created'])
                    : now()->format('Y-m-d H:i:s'),
                'face_detection' => [
                    'processed' => !empty($facesData),
                    'data'      => $facesData
                ]
            ],
            'raw_original' => $jsonContent
        ];
    }

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
