<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
     * @return string full path zip
     */
    public static function createNamedBackup(string $filename): string
    {
        File::ensureDirectoryExists(static::dir());
        $zipPath = static::dir().'/'.$filename;
        static::snapshotDbZip($zipPath);
        return $zipPath;
    }
}
