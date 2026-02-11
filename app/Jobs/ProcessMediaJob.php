<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\MediaProcessing;
use Illuminate\Support\Facades\Log;

class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Declaramos a propriedade que vai segurar o ID
    protected $taskId;

    /**
     * O construtor recebe o ID enviado pelo Controller
     */
    public function __construct($taskId)
    {
        $this->taskId = $taskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Agora $this->taskId existe!
        $task = MediaProcessing::find($this->taskId);

        if (!$task || $task->status !== 'pending') {
            return;
        }

        $task->update(['status' => 'processing']);

        try {
            // Log para debug (ajuda a ver o BestDist vindo do .ini)
            Log::info("Processando mídia ID: {$this->taskId} com BestDist: {$task->best_dist}");

            // --- SEU PROCESSAMENTO AQUI ---
            // Exemplo: $result = SuaLogica::process($task->file_path, $task->best_dist);

            // Se tudo der certo, deletamos os arquivos físicos
            if (Storage::exists($task->file_path)) {
                // Aqui você moveria para a pasta final antes de deletar o temporário
                // Storage::copy($task->file_path, 'caminho/final/na/galeria.jpg');
                
                Storage::delete([$task->file_path, $task->sidecar_path]);
                
                // Remove a pasta da transação (inbound/hash_timestamp)
                $directory = dirname($task->file_path);
                Storage::deleteDirectory($directory);
            }

            // Elimina o registro da tabela após o sucesso total
            $task->delete(); 
            Log::info("Tarefa {$this->taskId} concluída e removida da tabela.");

        } catch (\Exception $e) {
            Log::error("Erro no Job {$this->taskId}: " . $e->getMessage());
            $task->update(['status' => 'error']);
        }
    }
}
