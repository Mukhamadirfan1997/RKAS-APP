<?php

namespace App\Http\Controllers;

use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    public function cek(Request $request)
    {
        $info = UpdateService::checkLatestVersion();
        if ($info === null) {
            return response()->json(['ada_update' => false, 'checked' => true]);
        }

        return response()->json($info);
    }

    public function unduh(Request $request)
    {
        $request->validate([
            'url_unduh' => 'nullable|url',
        ]);

        $info = UpdateService::checkLatestVersion();
        if ($info === null || empty($info['url_unduh'])) {
            // fallback pakai url dari request jika cek gagal tapi user sudah punya url (cache)
            $url = $request->input('url_unduh');
            if (empty($url)) {
                return response()->json(['success' => false, 'message' => 'Tidak ada update tersedia atau gagal cek versi. Pastikan ada Release di GitHub.'], 404);
            }
            $info = [
                'url_unduh' => $url,
                'nama_file' => basename(parse_url($url, PHP_URL_PATH)) ?: 'KARSA-Update.exe',
                'ukuran_mb' => 0,
            ];
        }

        $url = $info['url_unduh'];
        $namaFile = $info['nama_file'] ?? basename(parse_url($url, PHP_URL_PATH)) ?: 'KARSA-Installer.exe';
        // sanitasi nama
        $namaFile = preg_replace('/[^A-Za-z0-9._\-]/', '_', $namaFile);
        if (! str_ends_with(strtolower($namaFile), '.exe') && ! str_ends_with(strtolower($namaFile), '.msi')) {
            $namaFile .= '.exe';
        }

        $dir = UpdateService::updatesDir();
        File::ensureDirectoryExists($dir);
        $dest = $dir.'/'.$namaFile;

        // Jika sudah ada dan ukuran cocok, skip download
        if (is_file($dest) && ! empty($info['size_bytes']) && filesize($dest) === (int) $info['size_bytes']) {
            return response()->json(['success' => true, 'message' => 'File sudah terunduh.', 'file' => $namaFile, 'path' => $dest]);
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            // Streaming download via sink (tidak load ke memory)
            $resp = Http::timeout(300)->withHeaders([
                'User-Agent' => 'KARSA-Updater',
                'Accept' => 'application/octet-stream',
            ])->sink($dest)->get($url);

            if (! $resp->successful()) {
                @File::delete($dest);

                return response()->json(['success' => false, 'message' => 'Gagal mengunduh installer (HTTP '.$resp->status().').'], 500);
            }

            if (! is_file($dest) || filesize($dest) < 1024) {
                @File::delete($dest);

                return response()->json(['success' => false, 'message' => 'File unduhan tidak valid.'], 500);
            }

            return response()->json(['success' => true, 'message' => 'Unduhan selesai.', 'file' => $namaFile, 'size_mb' => round(filesize($dest) / 1048576, 1)]);
        } catch (\Throwable $e) {
            @File::delete($dest);
            Log::error('Unduh update gagal: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal mengunduh: '.$e->getMessage()], 500);
        }
    }

    public function status(Request $request)
    {
        $dir = UpdateService::updatesDir();
        if (! is_dir($dir)) {
            return response()->json(['exists' => false]);
        }
        $nama = $request->query('file');
        $path = null;
        if ($nama) {
            $path = $this->pathZonaAman($nama);
        } else {
            $path = UpdateService::downloadedFilePath();
            // downloadedFilePath sudah return path aman dari glob, tapi tetap validasi ekstensi
            if ($path && ! $this->isAllowedExtension($path)) {
                $path = null;
            }
        }
        if (! $path || ! is_file($path)) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'file' => basename($path),
            'size_bytes' => filesize($path),
            'size_mb' => round(filesize($path) / 1048576, 1),
            'modified' => date('c', filemtime($path)),
        ]);
    }

    public function downloadFile($nama)
    {
        $path = $this->pathZonaAman($nama);
        if (! $path) {
            abort(404);
        }

        return response()->download($path);
    }

    /**
     * Guard path traversal seperti BackupController::pathZonaAman.
     * Hanya izinkan basename eksak dan ekstensi .exe/.msi
     */
    private function pathZonaAman(string $nama): ?string
    {
        if (basename($nama) !== $nama) {
            return null;
        }
        $lower = strtolower($nama);
        if (! str_ends_with($lower, '.exe') && ! str_ends_with($lower, '.msi')) {
            return null;
        }
        $path = UpdateService::updatesDir().'/'.$nama;
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function isAllowedExtension(string $path): bool
    {
        $lower = strtolower($path);

        return str_ends_with($lower, '.exe') || str_ends_with($lower, '.msi');
    }
}
