<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Paginator::useBootstrapFive();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            // 1. Busca a lista de todas as galerias para o select
            $galerias = \App\Models\Media::select('media_gallery')
                ->whereNotNull('media_gallery')
                ->distinct()
                ->orderBy('media_gallery', 'asc')
                ->pluck('media_gallery');
            // 2. Recupera a galeria da sessão
            $currentGaleria = session('selected_galeria');

            // 3. Se for o primeiro acesso e a sessão estiver vazia, define a padrão
            if (!$currentGaleria && $galerias->isNotEmpty()) {
                $currentGaleria = $galerias->first(function ($value) {
                    return stripos($value, 'Priv') === false; // Pula as que contém "Priv"
                });

                // Se ainda assim não achou (ex: só existem galerias Priv), pega a primeira que existir
                if (!$currentGaleria) {
                    $currentGaleria = $galerias->first();
                }

                // Fixa na sessão para os próximos requests
                session(['selected_galeria' => $currentGaleria]);
            }

            // 4. Compartilha AMBAS as variáveis com todas as views
            $view->with('galerias', $galerias);
            $view->with('currentGaleria', $currentGaleria);
        });
    }
}
