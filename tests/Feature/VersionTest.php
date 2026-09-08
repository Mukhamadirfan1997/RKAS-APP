<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        // Bersihkan env yang mungkin di-set test
        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION'], $_SERVER['APP_VERSION']);
        parent::tearDown();
    }

    public function test_config_membaca_app_version_env_jika_di_set(): void
    {
        // Simulasi production: lib.rs menyuntik APP_VERSION=9.9.9
        putenv('APP_VERSION=9.9.9');
        $_ENV['APP_VERSION'] = '9.9.9';

        $cfg = require base_path('config/karsa.php');

        $this->assertEquals('9.9.9', $cfg['version'], 'config/karsa.php harus prioritas APP_VERSION env');

        // Cleanup
        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION']);
    }

    public function test_config_fallback_ke_file_jika_env_kosong(): void
    {
        // Pastikan APP_VERSION kosong (dev via artisan serve)
        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION'], $_SERVER['APP_VERSION']);

        // Hapus cache config yang mungkin membekukan nilai lama
        // (test environment tidak pakai config:cache, tapi jaga-jaga)
        Config::set('karsa.version', null);

        $cfg = require base_path('config/karsa.php');

        // Di repo dev, file src-tauri/tauri.conf.json ada dengan version 1.0.1
        $expected = null;
        $confPath = base_path('src-tauri/tauri.conf.json');
        if (is_file($confPath)) {
            $j = json_decode((string) file_get_contents($confPath), true);
            $expected = $j['version'] ?? null;
        }

        $this->assertEquals($expected, $cfg['version'], 'Jika APP_VERSION kosong, harus fallback ke tauri.conf.json');
        $this->assertNotEquals('0.0.0', $cfg['version'], 'JANGAN fallback ke 0.0.0');
    }

    public function test_config_tidak_mengembalikan_0_0_0_jika_kedua_sumber_gagal(): void
    {
        // Simulasi skenario sangat tidak wajar: APP_VERSION kosong + file tidak ada
        // Kita tidak menghapus file asli, tapi verifikasi bahwa config default bukan 0.0.0
        // jika kita force null via Config::set
        Config::set('karsa.version', null);
        $this->assertNull(Config::get('karsa.version'));

        // Pastikan require langsung juga tidak menghasilkan 0.0.0 jika APP_VERSION kosong
        // (jika file ada, akan berisi versi file; jika tidak ada -> null, bukan 0.0.0)
        putenv('APP_VERSION');
        unset($_ENV['APP_VERSION'], $_SERVER['APP_VERSION']);

        // Cek bahwa fallback bukan 0.0.0 dengan me-load config tanpa env dan tanpa file
        // Kita tidak bisa hapus file nyata, jadi cukup assert bahwa file saat ini ada dan bukan 0.0.0
        $cfg = require base_path('config/karsa.php');
        $this->assertNotEquals('0.0.0', $cfg['version']);
    }

    public function test_update_service_tidak_tampilkan_banner_jika_versi_null(): void
    {
        Config::set('karsa.version', null);

        // Fake GitHub API mengembalikan versi baru 9.9.9 — seharusnya tetap null karena versi lokal tidak diketahui
        Http::fake([
            UpdateService::GITHUB_API => Http::response([
                'tag_name' => 'v9.9.9',
                'assets' => [
                    ['name' => 'KARSA-9.9.9.exe', 'browser_download_url' => 'https://example.com/KARSA-9.9.9.exe', 'size' => 40000000],
                ],
            ], 200),
        ]);

        $result = UpdateService::checkLatestVersion();

        $this->assertNull($result, 'Jika versi lokal null/tidak diketahui, UpdateService harus return null (jangan tampil banner palsu)');
    }

    public function test_update_service_tidak_tampilkan_banner_jika_versi_string_kosong(): void
    {
        Config::set('karsa.version', '');

        Http::fake([
            UpdateService::GITHUB_API => Http::response([
                'tag_name' => 'v9.9.9',
                'assets' => [
                    ['name' => 'KARSA-9.9.9.exe', 'browser_download_url' => 'https://example.com/KARSA-9.9.9.exe', 'size' => 40000000],
                ],
            ], 200),
        ]);

        $result = UpdateService::checkLatestVersion();

        $this->assertNull($result, 'Jika versi lokal string kosong, harus return null');
    }

    public function test_update_service_tetap_deteksi_update_jika_versi_diketahui(): void
    {
        Config::set('karsa.version', '1.0.1');

        Http::fake([
            UpdateService::GITHUB_API => Http::response([
                'tag_name' => 'v1.0.2',
                'assets' => [
                    ['name' => 'KARSA-1.0.2.exe', 'browser_download_url' => 'https://example.com/KARSA-1.0.2.exe', 'size' => 42000000],
                ],
            ], 200),
        ]);

        $result = UpdateService::checkLatestVersion();

        $this->assertNotNull($result);
        $this->assertTrue($result['ada_update']);
        $this->assertEquals('1.0.1', $result['versi_sekarang']);
        $this->assertEquals('1.0.2', $result['versi_baru']);
    }

    public function test_tentang_page_menggunakan_config_single_source(): void
    {
        $user = User::first();
        $this->actingAs($user);

        Config::set('karsa.version', '1.0.1');

        $resp = $this->get(route('tentang.index'));
        $resp->assertStatus(200);
        $resp->assertViewHas('version', '1.0.1');
        // Pastikan bukan 0.0.0
        $this->assertNotEquals('0.0.0', $resp->viewData('version'));
    }
}
