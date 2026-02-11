<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;
use App\Models\Face;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\Response;


class ProcessFaceDetection implements ShouldQueue
{
    use Queueable;
    protected $batchSize;

    /**
     * Create a new job instance.
     */
    public function __construct($batchSize = 50)
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $medias = Media::where('face_scanned', false)
            ->orderBy('id')
            ->limit($this->batchSize ?? 10)
            ->get();

        if ($medias->isEmpty()) return;

        foreach ($medias as $media) {
            // --- SKIP VIDEO ---
           // if (str_contains($media->mime_type, 'video')) {
           //     Log::info("Skipping video: {$media->id}");
                // Opcional: marcar como true para o teste fluir
                // $media->update(['face_scanned' => true]); 
           //     continue;
           // }

            try {
                $response = Http::timeout(60)->post('http://127.0.0.1:8000/extract', [
                    'path' => $media->file_path
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Se a API retornou "no_faces", apenas encerramos essa mídia
                    if (($data['status'] ?? '') === 'no_faces') {
                        $media->update(['face_scanned' => true]);
                        continue;
                    }

                    foreach ($data['faces'] as $faceData) {
                        $imageContent = Http::get($faceData['thumbnail_url'])->body();
                        $localPath = 'faces/' . $faceData['thumbnail_filename'];

                        Face::create([
                            'media_file_id'   => $media->id,
                            'thumbnail_path'  => $localPath,
                            'embedding'       => json_encode($faceData['embedding']),
                            'x' => $faceData['x'],
                            'y' => $faceData['y'],
                            'w' => $faceData['w'],
                            'h' => $faceData['h'],
                            'embedding_model' => 'ArcFace'
                        ]);
                        // 1. Baixa o conteúdo
                        $imageContent = Http::get($faceData['thumbnail_url'])->body();

                        // 2. Salva no Storage e verifica se deu certo
                        if (Storage::disk('public')->put($localPath, $imageContent)) {

                            // 3. Só deleta do Python se o arquivo foi salvo com sucesso no Laravel
                            Http::post('http://127.0.0.1:8000/delete', [
                                'filename' => $faceData['thumbnail_filename']
                            ]);
                        } else {
                            Log::error("Falha ao salvar o thumbnail localmente. O cleanup foi abortado para evitar perda de dados.");
                        }
                    }

                    $media->update(['face_scanned' => true]);
                }
            } catch (\Exception $e) {
                Log::error("Erro no processamento ID {$media->id}: " . $e->getMessage());
            }
        }
    }
}
