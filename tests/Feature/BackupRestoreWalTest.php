<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Regresi bug restore "sukses palsu" (WAL lama membangkitkan data lama).
 *
 * Berkas nyata dipakai (bukan :memory:) karena bug hanya muncul pada SQLite
 * mode WAL: bila `database.sqlite-wal` lama tertinggal saat berkas utama
 * ditimpa, SQLite memutar ulang frame lama di atas database baru dan
 * `integrity_check` tetap `ok` — kegagalan senyap.
 */
class BackupRestoreWalTest extends TestCase
{
    private string $dir;

    private string $dbFile;

    private string $databaseAsli;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/uji-restore-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);
        $this->dbFile = $this->dir.'/database.sqlite';

        $this->databaseAsli = (string) config('database.connections.sqlite.database');
        config(['database.connections.sqlite.database' => $this->dbFile]);
        // Laravel mensyaratkan berkas sudah ada sebelum koneksi SQLite dibuka.
        touch($this->dbFile);
        DB::purge();

        // Database target: mode WAL + data lama yang masih berada di `-wal`.
        DB::statement('PRAGMA journal_mode=WAL');
        DB::statement('CREATE TABLE sentinel (nilai TEXT)');
        DB::table('sentinel')->insert(['nilai' => 'LAMA-1']);
        DB::table('sentinel')->insert(['nilai' => 'LAMA-2']);
    }

    protected function tearDown(): void
    {
        DB::purge();
        config(['database.connections.sqlite.database' => $this->databaseAsli]);
        DB::purge();
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * Buat zip backup berisi database valid dengan isi sentinel tertentu.
     */
    private function buatZipBackup(array $nilai = ['BARU-1'], bool $rusak = false): string
    {
        $sumber = $this->dir.'/sumber-'.bin2hex(random_bytes(4)).'.sqlite';

        if ($rusak) {
            // Header SQLite valid tetapi isi bukan halaman database.
            file_put_contents($sumber, "SQLite format 3\x00".str_repeat('X', 256));
        } else {
            $pdo = new \PDO('sqlite:'.$sumber);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('CREATE TABLE sentinel (nilai TEXT)');
            $stmt = $pdo->prepare('INSERT INTO sentinel (nilai) VALUES (?)');
            foreach ($nilai as $v) {
                $stmt->execute([$v]);
            }
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $pdo = null;
            @unlink($sumber.'-wal');
            @unlink($sumber.'-shm');
        }

        $zipPath = $this->dir.'/backup-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFile($sumber, 'database/database.sqlite');
        $zip->close();

        return $zipPath;
    }

    public function test_restore_membersihkan_wal_lama_sehingga_data_lama_tidak_bangkit(): void
    {
        $zip = $this->buatZipBackup();

        // Prasyarat bug: WAL lama memang ada & berisi frame.
        clearstatcache();
        $this->assertFileExists($this->dbFile.'-wal', 'Prasyarat uji: WAL lama harus ada.');

        $safety = BackupService::restoreFromZip($zip);

        $this->assertStringContainsString('rkas-pre-restore-', $safety);
        $this->assertSame(
            ['BARU-1'],
            DB::table('sentinel')->orderBy('nilai')->pluck('nilai')->all(),
            'Data lama tidak boleh muncul kembali setelah restore.'
        );
        $this->assertSame('ok', DB::selectOne('PRAGMA integrity_check')->integrity_check);

        // Tidak boleh ada berkas sementara/rollback yang tertinggal.
        $sisa = collect(File::files($this->dir))
            ->map(fn ($f) => $f->getFilename())
            ->filter(fn ($n) => str_contains($n, '.restore-') || str_contains($n, '.sebelum-restore-') || str_contains($n, '.bak'));
        $this->assertCount(0, $sisa->all(), 'Berkas sementara restore harus dibersihkan: '.$sisa->implode(', '));
    }

    public function test_restore_dibatalkan_dan_data_lama_utuh_saat_database_masih_dipegang_proses_lain(): void
    {
        $zip = $this->buatZipBackup();

        // Simulasi proses lain (mis. thread `php artisan migrate` milik Tauri)
        // yang masih memegang berkas WAL. Windows menolak menghapus berkas ini.
        $penahan = new \PDO('sqlite:'.$this->dbFile);
        $penahan->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->assertGreaterThan(0, (int) $penahan->query('SELECT count(*) FROM sentinel')->fetchColumn());

        $pesan = '';
        try {
            BackupService::restoreFromZip($zip);
        } catch (\RuntimeException $e) {
            $pesan = $e->getMessage();
        } finally {
            $penahan = null;
        }

        // Wajib gagal dengan pesan jelas, BUKAN sukses palsu.
        $this->assertStringContainsString('Restore dibatalkan', $pesan);
        $this->assertStringContainsString('dipakai proses lain', $pesan);

        // Data lama harus tetap utuh (tidak berubah menjadi data backup).
        $this->assertSame(
            ['LAMA-1', 'LAMA-2'],
            DB::table('sentinel')->orderBy('nilai')->pluck('nilai')->all(),
            'Restore yang dibatalkan tidak boleh mengubah data lama.'
        );
    }

    public function test_restore_menolak_sqlite_rusak_tanpa_menyentuh_data_lama(): void
    {
        $zip = $this->buatZipBackup(rusak: true);

        $pesan = '';
        try {
            BackupService::restoreFromZip($zip);
        } catch (\RuntimeException $e) {
            $pesan = $e->getMessage();
        }

        $this->assertNotSame('', $pesan, 'SQLite rusak harus ditolak, bukan dipasang.');
        $this->assertStringContainsString('Tidak ada data yang diubah', $pesan);
        $this->assertSame(
            ['LAMA-1', 'LAMA-2'],
            DB::table('sentinel')->orderBy('nilai')->pluck('nilai')->all()
        );
    }
}
