<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MediaConfigServiceProvider extends ServiceProvider
{
    public function register()
    {
        $iniPath = storage_path('app/config/media.ini');

        if (!file_exists($iniPath)) {
            return;
        }

        $ini = parse_ini_file($iniPath, true);

        Config::set('media.library_path', $ini['paths']['LibraryPath'] ?? null);
        Config::set('media.duplicates_path', $ini['paths']['DuplicatesPath'] ?? null);
    }
}

