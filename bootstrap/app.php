<?php

use App\Http\Middleware\CekLisensi;
use App\Services\BackupService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'cek.lisensi' => CekLisensi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Berkas lebih besar dari post_max_size: PHP membuang body sehingga
        // controller tidak pernah jalan. Tampilkan pesan jelas (bukan 413 polos).
        $exceptions->render(function (PostTooLargeException $e, $request) {
            $maksMb = BackupService::batasUnggahMb();
            $pesan = "Berkas yang diunggah melebihi batas server ({$maksMb} MB). "
                .'Pilih berkas backup yang lebih kecil lalu ulangi. Tidak ada data yang diubah.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $pesan], 413);
            }

            return response()->view('errors.413', ['maksMb' => $maksMb, 'pesan' => $pesan], 413);
        });
    })->create();

// Mode terinstall (Tauri): arahkan storage (log, compiled views, backup, update)
// ke app_data_dir agar tetap writable & tidak hilang saat update aplikasi.
$storageDataDir = getenv('KARSA_DATA_DIR');
if ($storageDataDir && is_string($storageDataDir) && $storageDataDir !== '') {
    $app->useStoragePath(rtrim($storageDataDir, '/\\').'/storage');
}

return $app;
