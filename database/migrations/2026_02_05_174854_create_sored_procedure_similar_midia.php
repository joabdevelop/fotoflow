<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usamos o DROP primeiro para garantir uma instalação limpa
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_find_similar_media;");

        DB::unprepared("
        CREATE PROCEDURE sp_find_similar_media(
            IN p_phash_hex VARCHAR(16),
            IN p_best_dist INT
        )
        BEGIN
            -- DECLARE cria uma variável local protegida, em vez de global (@)
            DECLARE v_input_val BIGINT UNSIGNED;
            
            -- Converte o pHash de entrada uma única vez
            SET v_input_val = CONV(p_phash_hex, 16, 10);

            SELECT id, 
                BIT_COUNT(CONV(phash, 16, 10) ^ v_input_val) AS dist
            FROM media_files
            WHERE phash IS NOT NULL 
              AND phash != '0000000000000000'
            -- O HAVING é necessário porque 'dist' é um alias criado no SELECT
            HAVING dist <= p_best_dist
            ORDER BY dist ASC
            LIMIT 1;
        END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::unprepared("DROP PROCEDURE IF EXISTS sp_find_similar_media;");
    }
};
