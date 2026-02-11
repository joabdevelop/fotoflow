<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MediasController;
use App\Http\Controllers\ManualUploadController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessFaceDetection;


// --- GRUPO DE ROTAS PROTEGIDAS (Apenas usuários logados) ---
Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class,     'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/galeria', [MediasController::class, 'index'])->name('media.index');

    Route::get('/media/stream', [MediasController::class, 'stream'])->name('media.stream');
    Route::post('/media/open', [MediasController::class, 'openMedia'])->name('media.open');


    // Rotas de Duplicatas (Páginas que comparam pHash e Hash MD5)
    Route::get('/duplicates/exact', [MediasController::class, 'duplicatesExact'])->name('duplicates.exact');
    Route::delete('/duplicates/exact/delete/{id}', [MediasController::class, 'deleteExact'])->name('duplicates.exact.delete');

    Route::get('/duplicates/similares', [MediasController::class, 'duplicatesSimilares'])->name('duplicates.similares');
    Route::get('/duplicates/cenario', [MediasController::class, 'duplicatesCenario'])->name('duplicates.cenario');
    Route::post('/duplicates/manter', [MediasController::class, 'duplicatesManterNoBanco'])->name('duplicates.manter');
    Route::delete('/duplicates/similares/delete/{id}', [MediasController::class, 'deleteSimilar'])->name('duplicates.similar.delete');

    // Funções de Gerenciamento (Update e Delete)
    Route::put('/media/update', [MediasController::class, 'updateMedia'])->name('media.update');
    Route::delete('/media/{id}', [MediasController::class, 'destroy'])->name('media.destroy');

    // Manual Upload Rota de exibição do formulário
    Route::get('/upload-manual', [ManualUploadController::class, 'show'])->name('media.upload.show');
    // Rota de processamento
    Route::post('/upload-manual', [ManualUploadController::class, 'processUpload'])->name('media.upload.process.submit');


    Route::post('/media/favorite/{id}', [MediasController::class, 'toggleFavorite'])->name('media.favorite');
    Route::get('/favorites', [MediasController::class, 'favoritesIndex'])->name('media.favorites');


    // Função para Resetar a Tabela (A que ajustamos para o SQLite Truncate)
    Route::post('/media/reset', [MediasController::class, 'apagarTabela'])->name('media.reset');


    // Perfil do Usuário (Nativo do Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Start-Stop serviço de inputs
    Route::post('/services/control/{name}/{action}', [MediasController::class, 'startStopService'])
        ->name('services.control')
        ->middleware(['auth']);
});

// #############################################################################
// Rotas públicas para testes e desenvolvimento
// #############################################################################



Route::get('/reset-ia', function () {
    // 1. Limpa os arquivos físicos do Laravel para não acumular lixo
    Storage::disk('public')->deleteDirectory('faces');
    Storage::disk('public')->makeDirectory('faces');

    // 2. Limpa a tabela de faces (Truncate)
    DB::statement('TRUNCATE TABLE faces RESTART IDENTITY CASCADE');

    // 3. Reseta o status das mídias
    DB::table('media_files')->update(['face_scanned' => false]);

    return "Sistema de IA resetado com sucesso! Arquivos apagados e banco limpo.";
});
Route::get('/start-scan', function () {
    ProcessFaceDetection::dispatch(100);
    return "Job de 100 mídias enviado para a fila!";
});

// #############################################################################
// END OF Rotas públicas para testes e desenvolvimento
// #############################################################################


// Em routes/web.php
Route::get('/galeria-faces', function () {
    // Aponta para a pasta correta que você mencionou
    $diretorio = base_path('storage/faces');

    if (!File::exists($diretorio)) {
        return "Pasta não encontrada em: " . $diretorio;
    }

    $arquivos = File::allFiles($diretorio);

    echo "<body style='background:#1a202c; color:white; font-family:sans-serif; padding:20px;'>";
    echo "<h1>Galeria: " . count($arquivos) . " Arquivos Detectados</h1>";
    echo "<div style='display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:15px;'>";

    foreach ($arquivos as $arquivo) {
        $caminhoFisico = $arquivo->getRealPath();

        // Executa o Python para extrair o Hash
        $python = 'C:\laragon\bin\python\python-3.10\python.exe';
        $script = base_path('ai_scripts/face_detector.py');
        $output = shell_exec("$python " . escapeshellarg($script) . " " . escapeshellarg($caminhoFisico));



        $dados = json_decode($output, true);

        if (!empty($dados['faces'])) {
            foreach ($dados['faces'] as $face) {
                echo "<div style='background:#2d3748; padding:10px; border-radius:8px;'>";
                echo "<strong style='color:#68d391;'>Face detectada</strong><br>";
                echo "Hash: {$face['hash']}<br>";
                echo "Confiança: {$face['confidence']}<br>";
                echo "Embedding: {$face['embedding_size']} dims";
                echo "</div>";
            }
        }
    }
    echo "</div></body>";
});

require __DIR__ . '/auth.php';
