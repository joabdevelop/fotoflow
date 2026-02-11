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
        Schema::create('copias_hash_exact', function (Blueprint $table) {
            // No MySQL, bigIncrements ou increments já definem como PRIMARY KEY e AUTO_INCREMENT
            $table->id(); 
            
            // original_media_id como INTEGER e com INDEX conforme seu SQL
            $table->integer('original_media_id')->nullable()->index('idx_original_media');
            
            // file_path como TEXT
            $table->text('file_path')->nullable();
            
            // file_name como VARCHAR(255)
            $table->string('file_name', 255)->nullable();
            
            // file_size como BIGINT
            $table->bigInteger('file_size')->nullable();
            
            // created_at usando o timestamp do banco de dados
             $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copias_hash_exact');
    }
};
