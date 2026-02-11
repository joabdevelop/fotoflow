<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Services\MediaScannerService;
use Symfony\Component\Finder\Finder;

class MediaScannerCommand extends Command
{
    protected $signature = 'media:scan {path} {--clear}';
    protected $description = 'Escanear imagens e vídeos ignorando arquivos inúteis e pastas ocultas';

    public function handle(MediaScannerService $service)
    {
        $dir = $this->argument('path');

        if (!is_dir($dir)) {
            $this->error("O diretório não existe: $dir");
            return;
        }

        if ($this->option('clear')) {
            $this->info("Limpando tabela de mídia...");
            \App\Models\Media::truncate();
        }

        $this->info("Localizando arquivos válidos...");

        // Usamos o Finder do Symfony (que o Laravel usa por baixo) para filtros avançados
        $finder = new Finder();
        $finder->files()
            ->in($dir)
            ->ignoreDotFiles(true)     // Ignora arquivos que começam com ponto (ex: .DS_Store)
            ->ignoreVCS(true)          // Ignora pastas como .git, .svn
            ->name('/\.(jpg|jpeg|png|gif|mp4|mov|avi|mkv|wmv)$/i'); // Filtra extensões (case insensitive)

        $fileCount = $finder->count();

        if ($fileCount === 0) {
            $this->warn("Nenhum arquivo de imagem ou vídeo encontrado.");
            return;
        }

        $this->info("Encontrados $fileCount arquivos. Iniciando processamento...");
        $bar = $this->output->createProgressBar($fileCount);
        $bar->start();

        foreach ($finder as $file) {
            // Passamos o objeto SplFileInfo diretamente para o service
            $service->scanFile($file);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Escaneamento concluído com sucesso!");
    }
}