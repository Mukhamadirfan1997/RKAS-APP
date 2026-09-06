<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupService
{
    public static function dir(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Buat snapshot DB yang konsisten ke file zip.
     * Reuse logic VACUUM INTO + fallback copy (dipakai BackupController & Pengesahan).
     *
     * @throws \RuntimeException jika gagal
     */
    public static function snapshotDbZip(string $zipPath): void
    {
        $tmp = $zipPath.'.sqlite';
        static::buatSnapshot($tmp);
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::delete($tmp);
            throw new \RuntimeException('Tidak dapat membuat file zip backup.');
        }
        $zip->addFile($tmp, 'database/database.sqlite');
        $zip->close();
        File::delete($tmp);
    }

    /**
     * Buat snapshot file sqlite yang konsisten (VACUUM INTO atau copy fallback).
     */
    public static function buatSnapshot(string $tmp): void
    {
        $inTransaction = DB::transactionLevel() > 0;
        if (! $inTransaction) {
            try {
                DB::connection()->getPdo()->exec("VACUUM INTO '".str_replace("'", "''", $tmp)."'");
                if (is_file($tmp) && filesize($tmp) > 0) {
                    return;
                }
            } catch (\Throwable $e) {
                // lanjut ke fallback copy
            }
        }

        // Fallback saat dalam transaksi atau VACUUM gagal: salin file langsung
        $dbName = DB::connection()->getDatabaseName();
        $source = ($dbName !== '' && $dbName !== ':memory:' && is_file($dbName)) ? $dbName : database_path('database.sqlite');
        if (! is_file($source)) {
            throw new \RuntimeException('Snapshot gagal: file database tidak ditemukan.');
        }
        $io = @fopen($source, 'rb');
        $out = @fopen($tmp, 'wb');
        if ($io === false || $out === false) {
            if ($io !== false) {
                fclose($io);
            }
            throw new \RuntimeException('Snapshot gagal: tidak dapat membaca/menulis file.');
        }
        stream_copy_to_stream($io, $out);
        fclose($io);
        fclose($out);
        if (filesize($tmp) === 0) {
            throw new \RuntimeException('Snapshot gagal: file cadangan kosong.');
        }
    }

    /**
     * Helper: buat backup dengan nama file tertentu di folder backup.
     *
     * @return string full path zip
     */
    public static function createNamedBackup(string $filename): string
    {
        File::ensureDirectoryExists(static::dir());
        $zipPath = static::dir().'/'.$filename;
        static::snapshotDbZip($zipPath);

        return $zipPath;
    }

    /**
     * Pastikan ada 1 backup auto harian (sekali per hari saat app dibuka pertama).
     * Dipanggil dari AppServiceProvider::boot (tiap request) tapi hanya bikin file jika belum ada untuk hari ini.
     * Prune otomatis per kategori: auto 7, backup 10, pre 10, pengesahan 20.
     *
     * @return string|null path file yang baru dibuat, null jika sudah ada atau testing
     */
    public static function ensureDailyAutoBackup(): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }
        try {
            File::ensureDirectoryExists(static::dir());
            $today = now('Asia/Jakarta')->format('Y-m-d');
            $filename = "rkas-auto-{$today}.zip";
            $zipPath = static::dir().'/'.$filename;
            if (is_file($zipPath) && filesize($zipPath) > 0) {
                return null;
            }
            // Hindari bikin barengan jika dua request bersamaan: cek lagi setelah lock sederhana
            static::snapshotDbZip($zipPath);
            static::pruneAllSafety();
            // Audit opsional (jangan gagalkan boot kalau audit error)
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'backup.auto',
                    'auditable_type' => 'Backup',
                    'auditable_id' => null,
                    'description' => 'Auto backup harian — '.$filename,
                    'new_values' => ['file' => $filename],
                ]);
            } catch (\Throwable $e) {
            }

            return $zipPath;
        } catch (\Throwable $e) {
            Log::warning('Auto backup harian gagal: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Hapus rkas-auto-* lama, sisakan $keep file terbaru (default 7).
     */
    public static function pruneOldAutoBackups(int $keep = 7): void
    {
        static::pruneByPrefix('rkas-auto-', $keep);
    }

    /**
     * Hapus file backup dengan prefix tertentu, sisakan $keep terbaru.
     */
    public static function pruneByPrefix(string $prefix, int $keep): void
    {
        if (! is_dir(static::dir())) {
            return;
        }
        $files = collect(File::files(static::dir()))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), $prefix) && $f->getExtension() === 'zip')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();
        if ($files->count() <= $keep) {
            return;
        }
        $files->slice($keep)->each(fn ($f) => @File::delete($f->getPathname()));
    }

    /**
     * Prune semua kategori backup per prefix agar tidak membengkak (dipanggil harian + setelah create/restore/katalog).
     * - rkas-auto-*: 7 hari (harian)
     * - rkas-backup-*: 10 terbaru (manual "Buat Backup Baru")
     * - rkas-pre-restore-*: 10 terbaru (safety sebelum restore)
     * - rkas-pre-katalog-*: 10 terbaru (safety sebelum update katalog)
     * - rkas-pengesahan-*: 20 terbaru (arsip resmi disahkan)
     */
    public static function pruneAllSafety(int $autoKeep = 7, int $backupKeep = 10, int $preKeep = 10, int $pengesahanKeep = 20): void
    {
        static::pruneByPrefix('rkas-auto-', $autoKeep);
        static::pruneByPrefix('rkas-backup-', $backupKeep);
        static::pruneByPrefix('rkas-pre-restore-', $preKeep);
        static::pruneByPrefix('rkas-pre-katalog-', $preKeep);
        static::pruneByPrefix('rkas-pengesahan-', $pengesahanKeep);
    }
}
