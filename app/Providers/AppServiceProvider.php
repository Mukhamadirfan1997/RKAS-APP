<?php

namespace App\Providers;

use App\Services\BackupService;
use Illuminate\Support\Carbon;
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

        // Auto backup harian sekali per hari saat app dibuka pertama (desktop offline)
        // Hanya untuk request web (bukan console test/migrate) — guard testing ada di service
        try {
            if (! app()->runningInConsole()) {
                BackupService::ensureDailyAutoBackup();
            }
        } catch (\Throwable $e) {
            Log::warning('Auto backup boot gagal: '.$e->getMessage());
        }
    }
}
