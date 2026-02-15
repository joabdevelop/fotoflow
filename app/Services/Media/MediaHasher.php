<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;

class MediaHasher
{
    /**
     * Calcula o Hash MD5 binário de um arquivo (Rápido para duplicatas exatas)
     */
    public function calculateMd5(string $path): string
    {
        return strtoupper(md5_file($path));
    }

    /**
     * Calcula o Hash Perceptual (pHash/aHash) 
     * Implementação otimizada para compatibilidade com o algoritmo Delphi
     */
    public function calculatePhash(string $path): ?string
    {
        // 1. Carrega a imagem
        $content = file_get_contents($path);
        $img = @imagecreatefromstring($content);
        
        if (!$img) {
            Log::warning("MediaHasher: Não foi possível criar imagem a partir do path: {$path}");
            return null;
        }

        $imgSize = 32;
        $blocks = 8;
        $blockSize = $imgSize / $blocks; // 4 pixels por bloco

        // 2. Redimensiona para 32x32 (Interpolação compatível com Scaled.SetSize)
        $scaled = imagecreatetruecolor($imgSize, $imgSize);
        imagecopyresampled($scaled, $img, 0, 0, 0, 0, $imgSize, $imgSize, imagesx($img), imagesy($img));

        $blockAvg = array_fill(0, 64, 0);

        // 3. Converte para Cinza e acumula média por blocos
        for ($y = 0; $y < $imgSize; $y++) {
            for ($x = 0; $x < $imgSize; $x++) {
                $rgb = imagecolorat($scaled, $x, $y);
                $r = ($rgb >> 16) & 0xff;
                $g = ($rgb >> 8) & 0xff;
                $b = $rgb & 0xff;

                // Fórmula de Luminosidade (Rec. 601) idêntica ao Delphi:
                // (R*299 + G*587 + B*114) div 1000
                $gray = ($r * 299 + $g * 587 + $b * 114) / 1000;

                $bx = (int) ($x / $blockSize);
                $by = (int) ($y / $blockSize);
                $index = $by * $blocks + $bx;

                $blockAvg[$index] += $gray;
            }
        }

        // 4. Calcula Média Global dos 64 blocos
        $globalAvg = 0;
        for ($i = 0; $i < 64; $i++) {
            $blockAvg[$i] = $blockAvg[$i] / ($blockSize * $blockSize);
            $globalAvg += $blockAvg[$i];
        }
        $globalAvg = $globalAvg / 64;

        // 5. Gera o Hash Binário de 64 bits
        $hashBin = '';
        for ($i = 0; $i < 64; $i++) {
            $hashBin .= ($blockAvg[$i] >= $globalAvg) ? '1' : '0';
        }

        // Limpeza de memória
        imagedestroy($img);
        imagedestroy($scaled);

        return $hashBin;
    }

    /**
     * Converte o hash binário (64 chars) para Hexadecimal (16 chars)
     * Útil para armazenamento otimizado no MySQL ou PostgreSQL
     */
    public function binToHex(string $hashBin): string
    {
        $hex = '';
        foreach (str_split($hashBin, 4) as $chunk) {
            $hex .= dechex(bindec($chunk));
        }
        return str_pad($hex, 16, '0', STR_PAD_LEFT);
    }
}