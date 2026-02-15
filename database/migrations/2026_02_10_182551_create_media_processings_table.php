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
        Schema::create('media_processings', function (Blueprint $table) {
            $table->id();
            $table->string('file_hash');
            $table->string('file_path');
            $table->string('sidecar_path');
            $table->string('phash')->nullable(); // Adicione este para guardar o hash binário inicial
            $table->string('face_thumbnail_path')->nullable(); // Tornar nullable
            $table->string('status', 20)->default('pending');
            $table->integer('best_dist')->default(6); // Definir valor padrão
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_processings');
    }
};
