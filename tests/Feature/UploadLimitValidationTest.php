<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pesan batas unggah harus mengikuti php.ini nyata.
 *
 * Sebelumnya validasi menjanjikan "500 MB" padahal `post_max_size` 64 MB.
 * Saat melewati post_max_size, PHP membuang seluruh body sehingga pengguna
 * hanya melihat "pilih berkas" (kegagalan senyap).
 */
class UploadLimitValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulihkan_menolak_berkas_melebihi_post_max_size_dengan_pesan_jelas(): void
    {
        // Instalasi baru (belum ada akun) → halaman pulihkan terbuka.
        $this->assertSame(0, User::count());

        $batas = BackupService::batasUnggahByte();
        $maksMb = BackupService::batasUnggahMb();

        $response = $this->call('POST', route('pulihkan.proses'), [], [], [], [
            'CONTENT_LENGTH' => (string) ($batas + 1048576),
        ]);

        $response->assertStatus(413);
        $response->assertSee("melebihi batas server ({$maksMb} MB)", false);
        $response->assertSee('Data aplikasi');
        $response->assertDontSee('500 MB');
    }

    public function test_pesan_validasi_tidak_menjanjikan_500_mb(): void
    {
        $this->assertLessThanOrEqual(1024, BackupService::batasUnggahMb());
        $this->assertStringNotContainsString('500 MB', file_get_contents(app_path('Http/Controllers/RegistrasiController.php')));
    }
}
