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
        Schema::create('faces', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com a media_files
            $table->foreignId('media_file_id')
                ->constrained('media_files')
                ->onDelete('cascade'); // Se deletar a foto, deleta as faces detectadas nela

            // Dados da Face
            $table->json('embedding'); // Vetor da face (compatível com MySQL JSON)
            $table->json('box');       // Coordenadas [x, y, w, h]
            $table->string('thumbnail_path')->nullable();
            
            // Parâmetro de precisão usado no processamento local (.ini)
            $table->float('best_dist')->nullable(); 
            
            // Campos de controle para o reconhecimento
            $table->string('person_name')->nullable()->index(); // Nome atribuído após identificação
            $table->boolean('is_known')->default(false);        // Identifica se já foi rotulada
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faces');
    }
};
