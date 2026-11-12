<?php

namespace App\Services;

use RuntimeException;

/**
 * Status "Akses LAN" milik instalasi ini.
 *
 * Sengaja TIDAK memakai tabel database: keputusan desain menyatakan tidak boleh
 * ada perubahan skema SQLite, dan status ini murni pengaturan mesin (bukan data
 * RKAS). Berkas disimpan di `storage/app/lan.json` sehingga ikut terbawa saat
 * storage dipindah ke app_data_dir mode desktop.
 *
 * Default (berkas belum ada / rusak) = LAN MATI. Fail-closed: berkas yang tidak
 * bisa dibaca tidak boleh membuat jaringan terbuka.
 */
class LanConfigService
{
    private const VERSI = 1;

    /** @var array{versi:int,aktif:bool,diubah_pada:?string,diubah_oleh:?int}|null */
    private ?array $cache = null;

    public function berkas(): string
    {
        // Bisa diarahkan lewat config (dipakai pengujian agar tidak menyentuh
        // berkas milik instalasi yang sebenarnya).
        $khusus = config('karsa.lan.berkas');
        if (is_string($khusus) && trim($khusus) !== '') {
            return $khusus;
        }

        return storage_path('app/lan.json');
    }

    /**
     * @return array{versi:int,aktif:bool,diubah_pada:?string,diubah_oleh:?int}
     */
    public function semua(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $bawaan = [
            'versi' => self::VERSI,
            'aktif' => false,
            'diubah_pada' => null,
            'diubah_oleh' => null,
        ];

        $path = $this->berkas();
        if (! is_file($path)) {
            return $this->cache = $bawaan;
        }

        $mentah = @file_get_contents($path);
        if ($mentah === false || trim($mentah) === '') {
            return $this->cache = $bawaan;
        }

        $data = json_decode($mentah, true);
        if (! is_array($data)) {
            return $this->cache = $bawaan;
        }

        return $this->cache = [
            'versi' => (int) ($data['versi'] ?? self::VERSI),
            'aktif' => (bool) ($data['aktif'] ?? false),
            'diubah_pada' => isset($data['diubah_pada']) && is_string($data['diubah_pada'])
                ? $data['diubah_pada']
                : null,
            'diubah_oleh' => isset($data['diubah_oleh']) && is_numeric($data['diubah_oleh'])
                ? (int) $data['diubah_oleh']
                : null,
        ];
    }

    public function aktif(): bool
    {
        return (bool) $this->semua()['aktif'];
    }

    /**
     * Ubah status LAN. Penulisan atomik (tulis `.tmp` lalu rename) agar berkas
     * tidak pernah setengah jadi bila aplikasi ditutup mendadak.
     *
     * @return array{versi:int,aktif:bool,diubah_pada:?string,diubah_oleh:?int}
     */
    public function aturAktif(bool $aktif, ?int $userId = null): array
    {
        $data = [
            'versi' => self::VERSI,
            'aktif' => $aktif,
            'diubah_pada' => now()->toIso8601String(),
            'diubah_oleh' => $userId,
        ];

        $path = $this->berkas();
        $folder = dirname($path);
        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException("Folder penyimpanan pengaturan LAN tidak bisa dibuat: {$folder}");
        }

        $isi = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($isi === false) {
            throw new RuntimeException('Gagal menyusun pengaturan LAN.');
        }

        $sementara = $path.'.tmp';
        if (@file_put_contents($sementara, $isi) === false) {
            throw new RuntimeException("Pengaturan LAN tidak bisa disimpan di {$path}.");
        }

        if (! @rename($sementara, $path)) {
            @unlink($sementara);

            throw new RuntimeException("Pengaturan LAN tidak bisa disimpan di {$path}.");
        }

        $this->cache = $data;

        return $data;
    }

    public function resetCache(): void
    {
        $this->cache = null;
    }
}
