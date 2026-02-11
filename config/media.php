<?php
return [
    // Onde as fotos oficiais aprovadas ficarão
    'library_path' => storage_path('app/public'),


    // Onde fotos duplicadas (identificadas pelo PHASH) serão movidas
    'duplicates_path' => storage_path('app/public/Duplicates'),
    
    // Conforme sua solicitação de Jan/2026: Parâmetro de sensibilidade para similaridade
    'best_dist' => env('MEDIA_BEST_DIST', 10), 
];
