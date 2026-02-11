<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaStoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MediaProcessing;
use App\Jobs\ProcessMediaUpload;
use App\Jobs\ProcessMediaJob;
use Illuminate\Support\Facades\Storage;

class MediaUploadController extends Controller
{

public function store(Request $request) 
{
    // 1. Validação
    $request->validate([
        'file' => 'required|file',
        'file_hash' => 'required|string',
        'sidecar_json' => 'required|string',
    ]);

    // 2. Dados do Delphi
    $hash = $request->input('file_hash');
    $sidecarRaw = $request->input('sidecar_json');
    $bestDist = $request->input('best_dist', 6); // [cite: 2026-01-14]

    // 3. Pasta Sandbox
    $folder = "inbound/{$hash}_" . now()->timestamp;

    // 4. Salva a Mídia Original
    $mediaFile = $request->file('file');
    $mediaPath = $mediaFile->storeAs($folder, $mediaFile->getClientOriginalName());

    // 5. Salva o JSON do Sidecar
    $jsonPath = "{$folder}/metadata.json";
    Storage::put($jsonPath, $sidecarRaw);

    // --- LÓGICA DE DEBUG: EXTRAIR THUMBNAILS DAS FACES ---
    try {
        $sidecarData = json_decode($sidecarRaw, true);
        // Caminho no JSON: canonical -> face_detection -> data -> faces
        $faces = $sidecarData['canonical']['face_detection']['data']['faces'] ?? [];

        foreach ($faces as $index => $face) {
            if (isset($face['thumbnail_base64'])) {
                // Decodifica o Base64 enviado pelo Delphi
                $imageData = base64_decode($face['thumbnail_base64']);
                
                // Nome do arquivo de face (ex: face_0_origem.jpg)
                $faceFileName = "{$folder}/face_debug_{$index}.jpg";
                
                // Salva o arquivo físico para você conferir
                // Salvando no mesmo disco 'public'
                Storage::disk('public')->put($faceFileName, $imageData);
            }
        }
    } catch (\Exception $e) {
        // Se houver erro no parse do JSON, ignoramos no debug para não travar o upload
    }
/*    
    // 6. Grava na Tabela de Controle
    $processing = MediaProcessing::create([
        'file_hash'    => $hash,
        'file_path'    => $mediaPath,
        'sidecar_path' => $jsonPath,
        'best_dist'    => $bestDist, // Parâmetro persistido conforme configurado [cite: 2026-01-14]
        'status'       => 'pending'
    ]);

    // 7. Notifica o Job
    ProcessMediaJob::dispatch($processing->id);

    return response()->json([
        'result' => 'queued', 
        'id' => $processing->id,
        'hash' => $hash
    ]);
*/

    return response()->json([
        'result' => 'debug_mode', 
        'message' => 'Tudo salvo! Verifique a pasta: ' . $folder,
        'hash' => $hash,
        'best_dist_received' => $bestDist // [cite: 2026-01-14]
    ]);
}

    
public function store_job(Request $request) 
{
    // 1. Salva o arquivo em 'storage/app/temp'
    $tempPath = $request->file('file')->store('temp');

    // 2. Despacha o Job com os dados do request + caminho do arquivo
    ProcessMediaJob::dispatch($request->all(), $tempPath);

    return response()->json(['message' => 'Upload aceito, processando...'], 202);
}

    // Método para analisar similaridade de imagens via API
    public function analyze(Request $request)
    {
        $hash = $request->input('file_hash');
        $phash = $request->input('phash');
        $limit = $request->input('best_dist', 6); // Valor padrão vindo do .ini

        // 1. Verifica Duplicata Exata
        $exact = DB::table('media_files')->where('file_hash', $hash)->first(['id']);
        if ($exact) {
            return response()->json(['result' => 'drDuplicate', 'similar_id' => $exact->id, 'score' => 0]);
        }

        // 2. Verifica Similaridade (pHash) - Lógica bit_count para MySQL
        if ($phash && $phash !== '0000000000000000') {
            // Chama a Stored Procedure passando o pHash e o BestDist do .ini
            $result = DB::select('CALL sp_find_similar_media(?, ?)', [$phash, $limit]);

            if (!empty($result)) {
                $similar = $result[0];
                return response()->json([
                    'result' => 'drSimilar',
                    'similar_id' => $similar->id,
                    'score' => $similar->dist
                ]);
            }
        }
        return response()->json(['result' => 'drNew', 'similar_id' => null, 'score' => null]);
    }
}
