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
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            
            // Nome da pessoa (ou 'Desconhecido' inicialmente)
            $table->string('name')->nullable()->index();
            
            // Referência para a face que será a "foto do perfil" (thumbnail principal)
            $table->foreignId('cover_face_id')->nullable(); 
            
            // Embedding médio (opcional, para acelerar a busca de novos matches)
            $table->json('representative_embedding')->nullable();
            
            $table->text('notes')->nullable();
            $table->boolean('is_favorite')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
