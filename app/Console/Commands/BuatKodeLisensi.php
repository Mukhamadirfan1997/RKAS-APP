<?php

namespace App\Console\Commands;

use App\Services\LisensiService;
use Illuminate\Console\Command;

class BuatKodeLisensi extends Command
{
    protected $signature = 'lisensi:buat-kode {device_code : Kode perangkat (UUID) dari sekolah} {tahun : Tahun anggaran, mis 2027}';
    protected $description = 'Buat kode aktivasi untuk device_code + tahun tertentu (kirim via WhatsApp)';

    public function handle(): int
    {
        $deviceCode = trim((string) $this->argument('device_code'));
        $tahun = (int) $this->argument('tahun');

        if ($deviceCode === '' || $tahun < 2020 || $tahun > 2100) {
            $this->error('Parameter tidak valid. Contoh: php artisan lisensi:buat-kode 550e8400-e29b-41d4-a716-446655440000 2027');
            return self::FAILURE;
        }

        $kode = LisensiService::generateActivationCode($deviceCode, $tahun);
        $this->info("Kode Aktivasi untuk device {$deviceCode} tahun {$tahun}:");
        $this->line('');
        $this->line("  <fg=green;options=bold>{$kode}</>");
        $this->line('');
        $this->line('Kirim kode ini ke sekolah via WhatsApp. Kode hanya berlaku untuk tahun tersebut.');

        return self::SUCCESS;
    }
}
