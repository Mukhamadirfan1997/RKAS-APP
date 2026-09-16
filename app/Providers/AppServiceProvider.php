<?php

namespace App\Providers;

use App\Services\BackupService;
use App\Services\DatabaseHealthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'id');
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

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
    }
}
