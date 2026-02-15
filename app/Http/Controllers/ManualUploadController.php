<?php

namespace App\Http\Controllers;

use App\Services\Media\MediaProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ManualUploadController extends Controller
{
    protected $processor;

    /**
     * Injeção do Serviço Orquestrador
     */
    public function __construct(MediaProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * Exibe o formulário de upload
     */
    public function show()
    {
        return view('uploadManualMedia');
    }

    /**
     * Processa o upload múltiplo vindo da interface
     */
    public function processUpload(Request $request)
    {
        try {
            // 1. Organiza todos os arquivos enviados em um mapa (Nome -> Objeto UploadedFile)
            // Isso facilita para o Resolver encontrar os JSONs sidecar
            $allFiles = [];
            foreach ($request->file('mediaFiles') as $file) {
                $allFiles[$file->getClientOriginalName()] = $file;
            }

            $filePaths = json_decode($request->input('file_paths'), true) ?? [];
            $galleryFallback = $request->input('gallery_fallback', 'Geral');

            $processedCount = 0;

            // 2. Loop principal: Processa apenas mídias, ignorando JSONs isolados
            foreach ($request->file('mediaFiles') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                
                if ($extension === 'json') {
                    continue; 
                }

                // Identifica a subpasta para definir o evento (ex: "Viagem/foto.jpg" -> "Viagem")
                $fileName = $file->getClientOriginalName();
                $relativePath = $filePaths[$fileName] ?? '';
                $parts = explode('/', $relativePath);
                $folderName = count($parts) > 1 ? $parts[count($parts) - 2] : $galleryFallback;

                // 3. DELEGAÇÃO: O serviço assume toda a responsabilidade técnica
                $this->processor->process($file, $allFiles, [
                    'galleryName' => $galleryFallback,
                    'sourceEvent' => $folderName,
                ]);

                $processedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$processedCount} arquivos enviados para a fila de processamento."
            ]);

        } catch (\Exception $e) {
            Log::error("ManualUploadController: Erro no upload: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar upload: ' . $e->getMessage()
            ], 500);
        }
    }
}