<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'cek.lisensi' => \App\Http\Middleware\CekLisensi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Mode terinstall (Tauri): arahkan storage (log, compiled views, backup, update)
// ke app_data_dir agar tetap writable & tidak hilang saat update aplikasi.
$storageDataDir = getenv('KARSA_DATA_DIR');
if ($storageDataDir && is_string($storageDataDir) && $storageDataDir !== '') {
    $app->useStoragePath(rtrim($storageDataDir, '/\\').'/storage');
}

return $app;
