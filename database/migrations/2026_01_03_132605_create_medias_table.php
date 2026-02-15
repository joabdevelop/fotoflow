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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id(); // Laravel já entende autoIncrement e Primary Key

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
            $table->integer('rating')->default(0);
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamp('taken_at')->nullable(); // Data real da foto (do EXIF)

            // --- NOVOS CAMPOS: Georeferenciamento ---
            // Usamos decimal(10,8) e (11,8) para precisão de GPS
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();

            // --- NOVOS CAMPOS: EXIF / Hardware ---
            $table->string('device_make', 100)->nullable(); // Motorola
            $table->string('device_model', 100)->nullable(); // Moto G-73

            // Lógica de Similaridade
            $table->foreignId('similar_to_id')->nullable()->constrained('media_files')->onDelete('set null');
            $table->integer('similarity_score')->nullable();

            // Controle de Processamento (Status)
            $table->boolean('processed_face')->default(false);
            $table->boolean('face_scanned')->default(false);
            $table->boolean('is_favorite')->default(false);

            // Campos de Sincronização
            $table->boolean('is_synced')->default(false)->index();
            $table->timestamp('synced_at')->nullable();
            $table->string('remote_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
