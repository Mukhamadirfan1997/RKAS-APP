<?php

namespace App\Providers;

use App\Services\AuditLogArchiveService;
use App\Services\BackupService;
use App\Services\DatabaseHealthService;
use App\Services\JaringanService;
use App\Services\LanConfigService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Status akses LAN & deteksi jaringan dipakai bersama oleh middleware dan
        // controller. Singleton agar berkas lan.json hanya dibaca sekali per
        // permintaan, dan perubahan status langsung terlihat tanpa cache basi.
        $this->app->singleton(LanConfigService::class);
        $this->app->singleton(JaringanService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'id');
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

        // Rate limit login: maksimal 5 percobaan per menit per alamat IP.
        // Berlaku juga untuk komputer lain lewat Akses LAN, sehingga tebak sandi
        // otomatis tidak bisa jalan tanpa henti. Mekanisme autentikasi tetap
        // sama — hanya ditambah pembatas percobaan (bukan dimatikan).
        RateLimiter::for('login', function (Request $request) {
            $pesan = 'Terlalu banyak percobaan masuk (maksimal 5 per menit). Tunggu sebentar lalu coba lagi.';

            return Limit::perMinute(5)
                ->by('login|'.$request->ip())
                ->response(function (Request $request) use ($pesan) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => $pesan], 429);
                    }

                    return back()
                        ->withErrors(['email' => $pesan])
                        ->withInput($request->only('email'));
                });
        });

        // WAL mode — idempoten, aman dipanggil berulang (lebih tahan crash)
        try {
            // Coba via Service (guard :memory:), fallback direct statement untuk file DB
            DatabaseHealthService::enableWal();
            // Double ensure via direct PRAGMA bila koneksi file tapi service skip
            $dbName = null;
            try {
                $dbName = DB::connection()->getDatabaseName();
            } catch (\Throwable $e) {
            }
            if ($dbName !== ':memory:' && $dbName !== '' && $dbName !== null) {
                try {
                    DB::statement('PRAGMA journal_mode=WAL');
                } catch (\Throwable $e) {
                }
                try {
                    DB::statement('PRAGMA synchronous=NORMAL');
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Enable WAL gagal: '.$e->getMessage());
        }

        // Auto backup harian sekali per hari saat app dibuka pertama (desktop offline)
        // Hanya untuk request web (bukan console test/migrate) — guard testing ada di service
        try {
            if (! app()->runningInConsole()) {
                BackupService::ensureDailyAutoBackup();
            }
        } catch (\Throwable $e) {
            Log::warning('Auto backup boot gagal: '.$e->getMessage());
        }

        // Pengecekan integritas berkala — maksimal 1x per 30 hari, cepat, jangan blokir startup
        try {
            if (! app()->runningInConsole()) {
                if (DatabaseHealthService::shouldRunCheck()) {
                    DatabaseHealthService::runIntegrityCheck();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Database health check boot gagal: '.$e->getMessage());
        }

        // Arsip audit log berkala — maksimal 1x per 30 hari, arsipkan log >90 hari ke tabel archive
        try {
            if (! app()->runningInConsole()) {
                if (AuditLogArchiveService::shouldRunArchive()) {
                    AuditLogArchiveService::runArchive();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AuditLog archive boot gagal: '.$e->getMessage());
        }
    }
}
