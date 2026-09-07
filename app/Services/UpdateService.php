<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateService
{
    public const GITHUB_API = 'https://api.github.com/repos/Mukhamadirfan1997/RKAS-APP/releases/latest';

    /**
     * Cek versi terbaru dari GitHub Releases.
     * Timeout pendek, gagal diam-diam (offline).
     *
     * @return array|null ['ada_update'=>bool,'versi_baru'=>string,'url_unduh'=>string,'ukuran_mb'=>float,'versi_sekarang'=>string,'nama_file'=>string] atau null jika tidak ada update/gagal
     */
    public static function checkLatestVersion(): ?array
    {
        try {
            $versiSekarang = config('karsa.version', '0.1.0');
            // fallback baca langsung jika config 0.0.0
            if ($versiSekarang === '0.0.0' || empty($versiSekarang)) {
                try {
                    $conf = base_path('src-tauri/tauri.conf.json');
                    if (is_file($conf)) {
                        $j = json_decode((string) file_get_contents($conf), true);
                        if (! empty($j['version'])) $versiSekarang = $j['version'];
                    }
                } catch (\Throwable $e) {}
            }
            $versiSekarang = ltrim(trim((string) $versiSekarang), 'v');

            $resp = Http::timeout(5)->withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'KARSA-Update-Checker',
            ])->get(self::GITHUB_API);

            if (! $resp->successful()) {
                return null;
            }

            $data = $resp->json();
            $tag = $data['tag_name'] ?? null;
            if (empty($tag)) return null;

            $versiBaru = ltrim(trim((string) $tag), 'v');
            if ($versiBaru === '') return null;

            // Cari asset .exe / .msi
            $assets = $data['assets'] ?? [];
            $downloadUrl = null;
            $size = 0;
            $namaFile = null;
            foreach ($assets as $a) {
                $name = $a['name'] ?? '';
                $url = $a['browser_download_url'] ?? null;
                if (empty($url)) continue;
                $lower = strtolower($name);
                if (str_ends_with($lower, '.exe') || str_ends_with($lower, '.msi')) {
                    $downloadUrl = $url;
                    $size = (int) ($a['size'] ?? 0);
                    $namaFile = $name;
                    break;
                }
            }
            if (empty($downloadUrl)) return null;

            $adaUpdate = version_compare($versiBaru, $versiSekarang, '>');
            $ukuranMb = $size > 0 ? round($size / 1048576, 1) : 0;

            return [
                'ada_update' => $adaUpdate,
                'versi_baru' => $versiBaru,
                'versi_sekarang' => $versiSekarang,
                'url_unduh' => $downloadUrl,
                'ukuran_mb' => $ukuranMb,
                'nama_file' => $namaFile,
                'size_bytes' => $size,
            ];
        } catch (\Throwable $e) {
            Log::info('Cek update gagal (offline): '.$e->getMessage());
            return null;
        }
    }

    public static function updatesDir(): string
    {
        return storage_path('app/updates');
    }

    public static function downloadedFilePath(?string $namaFile = null): ?string
    {
        $dir = static::updatesDir();
        if (! is_dir($dir)) return null;
        if ($namaFile) {
            $p = $dir.'/'.$namaFile;
            return is_file($p) ? $p : null;
        }
        // cari file terbaru .exe/.msi di folder updates
        $files = glob($dir.'/*.{exe,msi,EXE,MSI}', GLOB_BRACE);
        if (empty($files)) return null;
        usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
        return $files[0];
    }
}
