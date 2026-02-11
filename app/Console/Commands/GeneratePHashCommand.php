<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use App\Services\MediaScannerService;
use Illuminate\Support\Facades\File;

class GeneratePHashCommand extends Command
{
    // O comando aceita um limite opcional para não travar o servidor por horas
    protected $signature = 'media:phash {--limit= : Quantidade máxima de arquivos a processar}';
    protected $description = 'Calcula o Perceptual Hash para registros que possuem apenas MD5';

    public function handle(MediaScannerService $service)
    {
        ini_set('memory_limit', '2G');
        // Busca apenas registros onde o perceptual_hash está nulo
        $query = Media::whereNull('perceptual_hash');

        if ($this->option('limit')) {
            $query->limit($this->option('limit'));
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info("Todos os arquivos já possuem pHash calculado!");
            return;
        }

        $this->info("Processando pHash para $total arquivos...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($medias) use ($service, $bar) {
            foreach ($medias as $media) {
                if (File::exists($media->path)) {
                    // Reutilizamos a lógica do service para calcular e salvar
                    $this->calculatePHash($media, $service);
                    // Força a limpeza de memória após cada arquivo
                    unset($media); 
                    gc_collect_cycles(); 
                    
                    $bar->advance();

                } else {
                    $this->warn("\nArquivo não encontrado: {$media->path}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Processamento concluído!");
    }

    protected function calculatePHash($media, $service)
    {
        try {
            // Aqui chamamos o método específico do service que você já tem
            // Ou atualizamos diretamente se o service estiver simplificado
            $mime = $media->mime_type;
            $pHash = null;

            if (str_starts_with($mime, 'image/')) {
                // Certifique-se que o service tem o hasher público ou um método getPHash
                $pHash = $service->calculateImagePHash($media->path);
            } 
            
            if ($pHash) {
                $media->update(['perceptual_hash' => $pHash]);
            }
        } catch (\Exception $e) {
            $this->error("\nErro no arquivo {$media->filename}: " . $e->getMessage());
        }
    }
}