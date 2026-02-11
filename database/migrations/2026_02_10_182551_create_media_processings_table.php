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
        Schema::create('media_processings', function (Blueprint $table) {
            $table->id();
            $table->string('file_hash')->unique(); // ID único da tarefa
            $table->string('file_path'); // Caminho exato da mídia no storage
            $table->string('sidecar_path'); // Caminho exato do JSON no storage
            $table->string('face_thumbnail_path'); // face thumbnail path no storage
            $table->string('status', 20);    // ['pending', 'processing', 'completed', 'error'])  Status da tarefa
            $table->integer('best_dist'); // Valor capturado do seu .ini no Delphi [cite: 2026-01-14]
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
