<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\MediaProcessing;
use App\Services\MediaStoreService; // Importante!
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\Media\MetadataResolver; // Importante!
use App\Services\Media\MediaProcessor; // Importante!

class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $taskId;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
    }

    /**
     * Injetamos o MediaStoreService diretamente no handle
     */
    public function handle(MediaStoreService $service): void
    {
        $task = MediaProcessing::find($this->taskId);
        if (!$task || $task->status !== 'pending') {
            return;
        }

        $task->update(['status' => 'processing']);

        try {
            $sidecarPath = $task->sidecar_path;
            $sidecarContent = json_decode(Storage::disk('public')->get($sidecarPath), true);

            // 1. LEITURA DA NOVA ESTRUTURA CANONICAL
            // Buscamos primeiro no novo padrão, com fallback para o antigo (por segurança)
            $gallery = $sidecarContent['canonical']['media_gallery'] ?? ($sidecarContent['media_gallery'] ?? 'Manual');

            $event = $sidecarContent['canonical']['source_event'] ?? ($sidecarContent['source_event'] ?? 'ManualUpload');

            // 2. PREPARAÇÃO DOS DADOS PARA O SERVICE
            $data = [
                'file_hash' => $task->file_hash,
                'phash' => $task->phash, // O phash calculado pelo Controller
                'file_size' => Storage::disk('public')->size($task->file_path),
                'file_extension' => pathinfo($task->file_path, PATHINFO_EXTENSION),
                'mime_type' => Storage::disk('public')->mimeType($task->file_path),

                // Enviamos o canonical inteiro como metadados para o Service salvar no banco
                'sidecar_full' => $sidecarContent,

                'watch_gallery' => $gallery,
                'watch_source_event' => $event,
                'best_dist' => $task->best_dist ?? 6,
            ];

            // 3. EXECUÇÃO DO SERVICE
            // O service agora moverá o arquivo para: /storage/{gallery}/{event}/{filename}
            $service->process(new \Illuminate\Http\Request($data), $task->file_path);

            // Sucesso: remove da fila de processamento
            $task->delete();
        } catch (\Exception $e) {
            Log::error("Erro no Job {$this->taskId}: " . $e->getMessage());
            Log::error($e->getTraceAsString()); // Útil para debugar se algo quebrar no Service
            $task->update(['status' => 'error']);
        }
    }
}
