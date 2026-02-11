<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id()->autoIncrement(); // INTEGER SERIAL (Primary Key)
            
            // Identificação Única e Integridade
            $table->string('file_hash', 64)->unique();
            $table->string('phash')->nullable(); 
            
            // Dados do Arquivo
            $table->text('file_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_extension', 10)->nullable();
            $table->string('mime_type', 50)->nullable();
            
            // Organização e Metadados
            $table->string('media_gallery', 100)->nullable();
            $table->string('source_event', 100)->nullable();
            $table->integer("rating")->default(0);
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            
            // Lógica de Similaridade
           $table->foreignId('similar_to_id')
                ->nullable()
                ->constrained('media_files')
                ->onDelete('set null');
            $table->integer('similarity_score')->nullable();
            
            // Controle de Processamento (Status)
            $table->boolean('processed_face')->default(false);
            $table->boolean('face_scanned')->default(false);
            $table->boolean('is_favorite')->default(false);
            
            // Campos de Sincronização (Adicionados para a nova arquitetura)
            $table->boolean('is_synced')->default(false)->index(); 
            $table->timestamp('synced_at')->nullable();
            $table->string('remote_id')->nullable(); // ID que este registro recebeu na Hostinger

            $table->timestamps(); // Cria created_at e updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
