<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Media; // Seu novo Model MySQL
use App\Models\Face;  // Seu novo Model MySQL

class MigratePostgresToMysql extends Command
{
    protected $signature = 'migrate:legacy-data';
    protected $description = 'Migra dados do Postgres para o MySQL local';

    public function handle()
    {
        $this->info('Iniciando migração...');

        // 1. Migrar Media Files usando Chunk para não travar a memória
        DB::connection('postgres_legacy')->table('media_files')->chunkById(100, function ($oldMedia) {
            foreach ($oldMedia as $item) {
                // Conversão de dados se necessário (ex: phash bit para string)
                Media::updateOrCreate(
                    ['file_hash' => $item->file_hash],
                    [
                        'phash'             => (string) $item->phash, 
                        'file_path'         => $item->file_path,
                        'file_size'         => $item->file_size,
                        'photo_gallery'     => $item->photo_gallery,
                        'mime_type'         => $item->mime_type,
                        'processed_face'    => (bool) $item->processed_face,
                        'is_synced'         => false, // Define como pendente para a VPS
                        'created_at'        => $item->created_at,
                    ]
                );
            }
            $this->comment('Processando lote de mídias...');
        });

        // 2. Migrar Faces (Relacionando com os novos IDs)
        $this->info('Migrando Faces...');
        DB::connection('postgres_legacy')->table('faces')->chunkById(100, function ($oldFaces) {
            foreach ($oldFaces as $oldFace) {
                // Encontra a mídia correspondente no MySQL pelo hash (garante integridade)
                $newMedia = Media::where('file_hash', $oldFace->parent_hash)->first();

                if ($newMedia) {
                    Face::create([
                        'media_file_id'  => $newMedia->id,
                        'embedding'      => json_decode($oldFace->embedding), // Garante formato JSON
                        'box'            => json_decode($oldFace->box),
                        'thumbnail_path' => $oldFace->thumbnail_path,
                        'best_dist'      => $oldFace->best_dist ?? 0.6, // Valor do seu .ini
                    ]);
                }
            }
        });

        $this->info('Migração finalizada com sucesso!');
    }
}