<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Pengaman khusus lingkungan tes: restore TIDAK boleh menimpa
 * `database/database.sqlite` milik pengembang.
 *
 * Sebelum perbaikan, restore pada koneksi `:memory:` menyalin isi backup ke
 * berkas database dev, sehingga menjalankan test dapat merusak data kerja.
 */
class BackupRestoreMemoryTest extends TestCase
{
    public function test_restore_pada_koneksi_memory_tidak_menyentuh_database_dev(): void
    {
        $dev = database_path('database.sqlite');
        $hashSebelum = is_file($dev) ? hash_file('sha256', $dev) : null;

        $dir = storage_path('app/uji-memory-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($dir);
        $sumber = $dir.'/sumber.sqlite';

        $pdo = new \PDO('sqlite:'.$sumber);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sentinel (nilai TEXT)');
        $pdo->exec("INSERT INTO sentinel (nilai) VALUES ('AMAN')");
        $pdo = null;

        $zipPath = $dir.'/backup.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFile($sumber, 'database/database.sqlite');
        $zip->close();

        try {
            BackupService::restoreFromZip($zipPath);
        } finally {
            $hashSesudah = is_file($dev) ? hash_file('sha256', $dev) : null;
            $adaTargetNyasar = is_file(database_path('restore-target.sqlite'));

            File::deleteDirectory($dir);

            $this->assertSame($hashSebelum, $hashSesudah, 'Restore di lingkungan tes tidak boleh mengubah database dev.');
            $this->assertFalse($adaTargetNyasar, 'Restore di lingkungan tes tidak boleh membuat berkas di folder database dev.');
        }
    }

    public function test_batas_unggah_mengikuti_php_ini_bukan_angka_karangan(): void
    {
        $byte = BackupService::batasUnggahByte();
        $mb = BackupService::batasUnggahMb();

        $this->assertGreaterThan(0, $byte);
        $this->assertGreaterThanOrEqual(1, $mb);
        $this->assertLessThanOrEqual(1024, $mb, 'Batas unggah tidak masuk akal (>1 GB) — periksa parsing php.ini.');
        $this->assertSame((int) floor($byte / 1048576), $mb);
        $this->assertSame($mb, max(1, $mb));
    }
}
