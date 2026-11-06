<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupService
{
    /**
     * Berkas pendamping SQLite mode WAL. Wajib bersih sebelum & sesudah menimpa
     * database: bila `-wal` lama tertinggal, SQLite memutar ulang frame lama di
     * atas file baru sehingga data lama "bangkit" tanpa error apa pun.
     */
    private const SIDECAR_DB = ['-wal', '-shm', '-journal'];

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

        // Fallback saat dalam transaksi atau VACUUM gagal: salin file langsung.
        // Wajib checkpoint dulu: tanpa ini salinan hanya berisi file utama dan
        // frame terbaru yang masih di `-wal` ikut tertinggal (cadangan jadi basi).
        try {
            DB::connection()->getPdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable $e) {
            // Bukan mode WAL / terkunci: lanjut, salinan masih bisa dipakai.
        }

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

    /**
     * Restore database dari berkas zip backup.
     *
     * Dipakai bersama oleh halaman Backup (setelah login) dan halaman
     * "Pulihkan" pada instalasi baru (tanpa akun). Sebelum menimpa, snapshot
     * pengaman dibuat agar data lama masih bisa diambil kembali.
     *
     * @return string nama berkas snapshot pengaman
     *
     * @throws \RuntimeException bila zip tidak valid / gagal menimpa
     */
    public static function restoreFromZip(string $zipPath): string
    {
        $stamp = now()->format('Y-m-d_H-i-s');
        $tmpDir = static::dir().'/restore-tmp-'.$stamp.'-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($tmpDir);

        try {
            $extractPath = $tmpDir.'/extract';
            File::ensureDirectoryExists($extractPath);

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Zip tidak valid.');
            }
            $zip->extractTo($extractPath);
            $zip->close();

            // Cari file database.sqlite di dalam zip
            $dbFiles = collect(File::allFiles($extractPath))
                ->filter(fn ($f) => $f->getFilename() === 'database.sqlite');

            if ($dbFiles->isEmpty()) {
                throw new \RuntimeException('Isi zip tidak valid: tidak ada database/database.sqlite.');
            }

            $dbBaru = $dbFiles->first()->getPathname();
            if (! static::isValidSqlite($dbBaru)) {
                throw new \RuntimeException('File database dalam zip bukan SQLite yang valid.');
            }
            // Verifikasi isi (bukan hanya header: integrity_check + ada tabel).
            // Dijalankan SEBELUM data lama disentuh.
            try {
                static::periksaSqlite($dbBaru);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException($e->getMessage().' Tidak ada data yang diubah.', 0, $e);
            }

            // Simpan cadangan keselamatan DB saat ini sebelum menimpa
            $safetyZip = static::dir().'/rkas-pre-restore-'.$stamp.'.zip';
            static::snapshotDbZip($safetyZip);

            // Mendukung DB di app_data_dir (Tauri produksi, writable tanpa admin) maupun install dir (dev)
            $dbName = (string) DB::connection()->getDatabaseName();

            if ($dbName === ':memory:') {
                if (! app()->environment('testing')) {
                    throw new \RuntimeException('Restore gagal: koneksi database aktif bukan file database.sqlite standar ('.$dbName.')');
                }
                // Lingkungan tes memakai DB in-memory: berkas database dev TIDAK boleh
                // disentuh. Restore diuji ke berkas sementara di dalam folder tes.
                static::pasangFileDatabase($dbBaru, $tmpDir.'/restore-target.sqlite');
            } elseif ($dbName !== '') {
                // $dbName bisa berupa app_data_dir/database.sqlite (produksi) atau database/database.sqlite (dev)
                static::pasangFileDatabase($dbBaru, $dbName);
            } else {
                throw new \RuntimeException('Restore gagal: koneksi database aktif bukan file database.sqlite standar ('.$dbName.')');
            }

            static::pruneAllSafety();

            return $safetyZip;
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    /**
     * Ganti berkas database aktif dengan berkas hasil restore secara aman.
     *
     * Kenapa tidak sekadar `copy()` (bug nyata yang pernah terjadi):
     * - SQLite mode WAL menyimpan tulisan terbaru di `database.sqlite-wal`.
     *   Bila file utama ditimpa tetapi `-wal`/`-shm` lama tertinggal (mis. masih
     *   dipegang proses lain seperti `php artisan migrate`), SQLite memutar
     *   ulang frame lama di atas file baru → restore "sukses" tetapi data lama
     *   kembali muncul dan `integrity_check` tetap `ok` (kegagalan senyap).
     * - Menimpa langsung juga berisiko menyisakan file separuh bila penulisan
     *   gagal di tengah jalan.
     *
     * Urutan yang dipakai:
     * 1. Validasi + salin sumber ke berkas sementara (database aktif belum disentuh).
     * 2. Checkpoint WAL, lepas koneksi aplikasi, snapshot berkas aktif untuk rollback.
     * 3. Hapus sidecar; bila masih dipakai proses lain → BATAL (data tidak diubah).
     * 4. `rename()` berkas sementara → database aktif (atomik).
     * 5. Verifikasi hasil (checksum identik + integritas). Bila gagal → kembalikan
     *    berkas lama dan lempar error; tidak pernah melaporkan sukses palsu.
     *
     * @throws \RuntimeException bila gagal / database sedang dipakai proses lain
     */
    public static function pasangFileDatabase(string $sumber, string $target): void
    {
        if (! is_file($sumber)) {
            throw new \RuntimeException('Restore gagal: berkas database sumber tidak ditemukan.');
        }

        // 1. Sumber harus SQLite sehat. Inilah satu-satunya pemeriksaan
        //    `integrity_check` (mahal untuk DB 40+ MB); hasil pemasangan
        //    diverifikasi dengan sidik jari SHA-256 yang lebih ketat
        //    (byte-identik dengan cadangan) agar restore tetap cepat.
        static::periksaSqlite($sumber);
        $halamanSumber = static::halamanSqlite($sumber);
        $hashSumber = hash_file('sha256', $sumber);
        if ($hashSumber === false) {
            throw new \RuntimeException('Restore gagal: berkas database sumber tidak dapat dibaca.');
        }
        if ($hashSumber === false) {
            throw new \RuntimeException('Restore gagal: berkas database sumber tidak dapat dibaca.');
        }

        File::ensureDirectoryExists(dirname($target));

        $sementara = $target.'.restore-'.getmypid().'-'.bin2hex(random_bytes(4)).'.tmp';
        $cadangan = $target.'.sebelum-restore-'.getmypid().'-'.bin2hex(random_bytes(4)).'.bak';
        $adaTargetLama = is_file($target);

        try {
            if (! @copy($sumber, $sementara)) {
                throw new \RuntimeException('Restore gagal: tidak dapat menyalin berkas database.');
            }
            static::hapusSidecar($sementara, 0.2);
            clearstatcache(true, $sementara);
            if (hash_file('sha256', $sementara) !== $hashSumber) {
                throw new \RuntimeException('Restore gagal: hasil salinan database tidak sama dengan sumber.');
            }

            // 2. Lepas koneksi aplikasi dulu, lalu snapshot berkas aktif (rollback).
            static::lepasKoneksiDatabase($target);
            if ($adaTargetLama) {
                if (! @copy($target, $cadangan)) {
                    throw new \RuntimeException('Restore dibatalkan: tidak dapat membuat cadangan berkas database aktif.');
                }
            }

            // 3. Jangan sentuh apa pun bila database masih dipegang proses lain.
            if (! static::hapusSidecar($target)) {
                throw new \RuntimeException(
                    'Restore dibatalkan: database sedang dipakai proses lain. '
                    .'Tutup semua jendela KARSA (termasuk tunggu proses migrasi selesai) lalu ulangi. Data tidak diubah.'
                );
            }

            // 4. Ganti berkas secara atomik.
            if (! @rename($sementara, $target)) {
                throw new \RuntimeException('Restore gagal: tidak dapat menimpa database aktif (berkas terkunci). Data tidak diubah.');
            }
            static::hapusSidecar($target, 1.0);

            // 5. Verifikasi hasil: berkas terpasang harus byte-identik dengan
            //    cadangan (SHA-256) DAN jumlah halamannya sama. Bila sisa WAL
            //    lama ikut terbaca, halaman/isi akan berbeda → dibatalkan,
            //    bukan dilaporkan "sukses".
            clearstatcache(true, $target);
            $hashTarget = is_file($target) ? hash_file('sha256', $target) : false;
            if ($hashTarget !== $hashSumber) {
                throw new \RuntimeException(
                    'Restore gagal: isi database setelah dipasang berbeda dengan berkas backup. Perubahan dibatalkan.'
                );
            }
            if (static::halamanSqlite($target) !== $halamanSumber) {
                throw new \RuntimeException(
                    'Restore gagal: isi database setelah dipasang berbeda dengan berkas backup. Perubahan dibatalkan.'
                );
            }

            static::hapusSidecar($target, 1.0);
            // Paksa koneksi aplikasi berikutnya memakai berkas baru.
            static::lepasKoneksiDatabase($target);
        } catch (\Throwable $e) {
            // Rollback hanya bila berkas aktif sudah tertimpa (sementara sudah hilang).
            $sudahMenimpa = ! is_file($sementara);

            if ($sudahMenimpa) {
                static::lepasKoneksiDatabase($target);
                $dipulihkan = false;

                if ($adaTargetLama && is_file($cadangan)) {
                    static::hapusSidecar($target, 0.2);
                    $dipulihkan = @copy($cadangan, $target)
                        && hash_file('sha256', $target) === hash_file('sha256', $cadangan);
                } elseif (! $adaTargetLama) {
                    @unlink($target);
                    clearstatcache(true, $target);
                    $dipulihkan = ! is_file($target);
                }
                static::hapusSidecar($target, 0.5);

                // Jangan pernah melaporkan "berhasil" bila rollback pun tidak terbukti.
                $pesan = preg_replace(
                    '/\s*(Data tidak diubah|Perubahan dibatalkan)\./u',
                    '',
                    $e->getMessage()
                ) ?: 'Restore gagal.';

                throw new \RuntimeException(
                    $pesan.($dipulihkan
                        ? ' Data lama sudah dikembalikan.'
                        : ' Data lama TIDAK dapat dikembalikan otomatis — pulihkan dari berkas backup safety.'),
                    0,
                    $e
                );
            }

            throw $e instanceof \RuntimeException
                ? $e
                : new \RuntimeException('Restore gagal: '.$e->getMessage(), 0, $e);
        } finally {
            foreach ([$sementara, $cadangan] as $sisa) {
                static::hapusSidecar($sisa, 0.2);
                if (is_file($sisa)) {
                    @unlink($sisa);
                }
            }
        }
    }

    /**
     * Berkas pendamping SQLite yang harus ikut dibersihkan saat mengganti file.
     *
     * @return list<string>
     */
    protected static function sidecarFiles(string $dbPath): array
    {
        return array_map(fn ($suffix) => $dbPath.$suffix, self::SIDECAR_DB);
    }

    /**
     * Hapus `-wal` / `-shm` / `-journal` dengan percobaan ulang.
     *
     * @return bool true bila semua sidecar benar-benar hilang
     */
    protected static function hapusSidecar(string $dbPath, float $batasDetik = 3.0): bool
    {
        $mulai = microtime(true);

        do {
            $bersih = true;
            foreach (static::sidecarFiles($dbPath) as $berkas) {
                clearstatcache(true, $berkas);
                if (! file_exists($berkas)) {
                    continue;
                }
                @unlink($berkas);
                clearstatcache(true, $berkas);
                if (file_exists($berkas)) {
                    $bersih = false;
                }
            }
            if ($bersih) {
                return true;
            }
            usleep(120000); // 120 ms: beri kesempatan proses lain menutup berkas
        } while ((microtime(true) - $mulai) < $batasDetik);

        return false;
    }

    /**
     * Buka berkas SQLite dan pastikan sehat.
     *
     * @return int jumlah halaman (page_count) sebagai sidik jari isi berkas
     *
     * @throws \RuntimeException bila bukan SQLite / rusak / tanpa tabel
     */
    public static function periksaSqlite(string $path): int
    {
        if (! static::isValidSqlite($path)) {
            throw new \RuntimeException('Berkas database bukan SQLite yang valid.');
        }

        try {
            $pdo = new \PDO('sqlite:'.$path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            $integrity = strtolower((string) $pdo->query('PRAGMA integrity_check')->fetchColumn());
            if ($integrity !== 'ok') {
                throw new \RuntimeException('Berkas database rusak: integrity_check = '.$integrity);
            }

            $jumlahTabel = (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
            if ($jumlahTabel < 1) {
                throw new \RuntimeException('Berkas database tidak berisi satu tabel pun.');
            }

            $halaman = (int) $pdo->query('PRAGMA page_count')->fetchColumn();
            $pdo = null;

            if ($halaman < 1) {
                throw new \RuntimeException('Berkas database tidak berisi data.');
            }

            return $halaman;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Berkas database tidak dapat dibaca: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Jumlah halaman SQLite (murah: hanya membaca header, tanpa scan penuh).
     *
     * Dipakai sebagai pemeriksaan tambahan setelah berkas dipasang: bila sisa
     * WAL lama ikut terpulihkan, jumlah halaman tidak akan sama dengan sumber.
     *
     * @throws \RuntimeException bila berkas tidak dapat dibuka
     */
    public static function halamanSqlite(string $path): int
    {
        try {
            $pdo = new \PDO('sqlite:'.$path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $halaman = (int) $pdo->query('PRAGMA page_count')->fetchColumn();
            $pdo = null;

            return $halaman;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Berkas database tidak dapat dibaca: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Checkpoint WAL lalu lepas koneksi aplikasi bila target = database aktif.
     */
    protected static function lepasKoneksiDatabase(string $target): void
    {
        try {
            $aktif = DB::connection()->getDatabaseName();
        } catch (\Throwable $e) {
            return;
        }

        if ($aktif === null || $aktif === '' || $aktif === ':memory:') {
            return;
        }
        if (! static::samaPath($aktif, $target)) {
            return;
        }

        try {
            DB::connection()->getPdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable $e) {
            // Terkunci / bukan WAL: pengosongan tetap dijamin oleh hapusSidecar + verifikasi.
        }

        DB::purge();
    }

    protected static function samaPath(string $a, string $b): bool
    {
        $norm = function (string $path): string {
            $nyata = realpath($path);

            return strtolower(str_replace('\\', '/', $nyata !== false ? $nyata : $path));
        };

        return $norm($a) === $norm($b);
    }

    /**
     * Batas unggah nyata (byte) = nilai terkecil antara `upload_max_filesize`
     * dan `post_max_size`. Dipakai agar pesan validasi tidak menjanjikan
     * "500 MB" padahal server hanya menerima 64 MB (upload bisa gagal senyap).
     */
    public static function batasUnggahByte(): int
    {
        $kandidat = array_filter([
            static::parseIniByte((string) ini_get('post_max_size')),
            static::parseIniByte((string) ini_get('upload_max_filesize')),
        ], fn ($v) => $v > 0);

        // Bila php.ini tanpa batas (-1/0), pakai 64 MB sebagai batas wajar aplikasi.
        return $kandidat === [] ? 64 * 1024 * 1024 : min($kandidat);
    }

    /**
     * Batas unggah dalam MB (dibulatkan ke bawah, minimal 1 MB).
     */
    public static function batasUnggahMb(): int
    {
        return max(1, (int) floor(static::batasUnggahByte() / 1048576));
    }

    /**
     * Ubah nilai php.ini (mis. "64M", "512K", "1G") menjadi byte.
     */
    private static function parseIniByte(string $nilai): int
    {
        $nilai = trim($nilai);
        if ($nilai === '') {
            return 0;
        }

        $angka = (float) $nilai;
        $pengali = match (strtolower(substr($nilai, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return (int) max(0, $angka * $pengali);
    }

    /**
     * Cek header SQLite (16 byte pertama).
     */
    public static function isValidSqlite(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 16);
        fclose($handle);

        return $header === "SQLite format 3\x00";
    }
}
