<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // Ex: "Joabe" ou null para desconhecidos
            $table->boolean('is_visible')->default(true); // Para ocultar rostos irrelevantes

            // Referência para a foto principal do rosto desta pessoa (thumbnail da galeria)
            $table->unsignedBigInteger('profile_face_id')->nullable();

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
