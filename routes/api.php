<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MediaSyncController;
use App\Http\Controllers\Api\MediaUploadController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/media/sync', [MediaSyncController::class, 'store']);

Route::middleware('auth:sanctum')->get('/v1/ping', function (Request $request) {
    return response()->json([
        'status' => 'Conectado!',
        'user' => $request->user()->email,
        'message' => 'O token do Sanctum está funcionando corretamente.',
        'server_time' => now()->toDateTimeString(),
    ]);
});

// Altere de [MediaSyncController::class, 'store'] para:
Route::middleware('auth:sanctum')->post('/v1/sync-media', [MediaSyncController::class, 'sync']);

// Rota para o serviço de processamento de Hash gravar novas mídias
Route::middleware('auth:sanctum')->post('/v1/media/store', [MediaUploadController::class, 'store']);

// Rota para o serviço de Analise de similaridade de imagens
Route::middleware('auth:sanctum')->post('/v1/media/analyze', [MediaUploadController::class, 'analyze']);