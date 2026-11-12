<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JaringanService;
use App\Services\LanConfigService;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\JaringanUji;
use Tests\TestCase;

/**
 * Halaman Tentang harus menampilkan port server yang SEDANG dipakai.
 *
 * Port tidak disimpan di config karena src-tauri/src/lib.rs memilih port bebas
 * setiap aplikasi dibuka (find_free_port). Karena itu nilai yang benar hanya
 * bisa berasal dari request yang sedang dilayani — test ini mengunci hal itu:
 * dua request ke port berbeda HARUS menghasilkan tampilan berbeda.
 *
 * Bagian akhir berkas ini mengunci kartu "Akses Jaringan (LAN)".
 */
class TentangPortTest extends TestCase
{
    use RefreshDatabase;

    private string $berkasLan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->actingAs(User::first());

        $folder = storage_path('framework/testing');
        File::ensureDirectoryExists($folder);
        $this->berkasLan = $folder.'/lan-tentang-'.bin2hex(random_bytes(4)).'.json';
        config(['karsa.lan.berkas' => $this->berkasLan]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->berkasLan ?? null, isset($this->berkasLan) ? $this->berkasLan.'.tmp' : null] as $berkas) {
            if (is_string($berkas) && is_file($berkas)) {
                @unlink($berkas);
            }
        }

        putenv('KARSA_DATA_DIR');
        unset($_ENV['KARSA_DATA_DIR'], $_SERVER['KARSA_DATA_DIR']);
        parent::tearDown();
    }

    /**
     * @param  list<array{nama?:string,ip:string,netmask?:string,up?:bool,virtual?:bool}>  $daftar
     */
    private function jaringan(array $daftar): void
    {
        $this->app->instance(
            JaringanService::class,
            new JaringanUji($daftar, $this->app->make(LanConfigService::class))
        );
    }

    public function test_tentang_menampilkan_port_yang_sedang_dipakai(): void
    {
        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertStatus(200);
        $resp->assertViewHas('portServer', 54321);
        $resp->assertViewHas('hostServer', '127.0.0.1');
        $resp->assertViewHas('alamatServer', 'http://127.0.0.1:54321');

        // Benar-benar tercetak di halaman, bukan hanya ada di view data.
        $resp->assertSee('Port Aktif');
        $resp->assertSee('54321');
        $resp->assertSee('http://127.0.0.1:54321');
    }

    public function test_port_ikut_berubah_mengikuti_request_bukan_nilai_tetap(): void
    {
        $pertama = $this->get('http://127.0.0.1:54321/tentang');
        $pertama->assertSee('54321');

        // Simulasi aplikasi dibuka ulang: lib.rs memilih port lain.
        $kedua = $this->get('http://127.0.0.1:61007/tentang');
        $kedua->assertViewHas('portServer', 61007);
        $kedua->assertSee('61007');
        $kedua->assertDontSee('54321');
    }

    public function test_mode_desktop_mengikuti_env_karsa_data_dir_dari_tauri(): void
    {
        // Tanpa env (mis. `php artisan serve`) → mode browser/dev.
        $dev = $this->get('http://127.0.0.1:9200/tentang');
        $dev->assertViewHas('modeDesktop', false);
        $dev->assertSee('Mode Browser (dev)');

        // lib.rs menyuntik KARSA_DATA_DIR pada aplikasi terpasang.
        putenv('KARSA_DATA_DIR=C:\Users\uji\AppData\Roaming\id.karsa.rkas2026');
        $_ENV['KARSA_DATA_DIR'] = 'C:\Users\uji\AppData\Roaming\id.karsa.rkas2026';

        $desktop = $this->get('http://127.0.0.1:61007/tentang');
        $desktop->assertViewHas('modeDesktop', true);
        $desktop->assertSee('Mode Desktop');
    }

    public function test_menampilkan_versi_php_dan_laravel_untuk_laporan_masalah(): void
    {
        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertViewHas('versiPhp', PHP_VERSION);
        $resp->assertViewHas('versiLaravel', app()->version());
        $resp->assertSee(PHP_VERSION);
    }

    public function test_halaman_tentang_tetap_menampilkan_versi_aplikasi(): void
    {
        config()->set('karsa.version', '1.7.3');

        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertStatus(200);
        $resp->assertSee('Versi 1.7.3');
        // Port & versi aplikasi tampil bersamaan tanpa saling menimpa.
        $resp->assertSee('54321');
    }

    // ------------------------------------------------------------------
    // Kartu "Akses Jaringan (LAN)"
    // ------------------------------------------------------------------

    public function test_kartu_lan_tampil_dengan_status_tidak_aktif_secara_bawaan(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);

        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertOk();
        $resp->assertSee('Akses Jaringan (LAN)');
        $resp->assertSee('LAN Tidak Aktif');
        $resp->assertSee('Binding server');
        $resp->assertSee('0.0.0.0');
        $resp->assertSee('Aktifkan Akses LAN');
        $resp->assertSee('http://192.168.1.17:54321');
        $resp->assertSee('192.168.1.0/24');
        $resp->assertSee('Buat Aturan Firewall');
        $resp->assertSee('id="btn-lan-toggle"', false);
        $resp->assertSee('id="btn-firewall-buat"', false);

        $resp->assertViewHas('lan', function (array $lan): bool {
            return $lan['status'] === 'nonaktif'
                && $lan['aktif'] === false
                && $lan['alamat_lan'] === 'http://192.168.1.17:54321'
                && $lan['alamat_lokal'] === 'http://127.0.0.1:54321';
        });
    }

    public function test_kartu_lan_menampilkan_status_aktif_dan_alamat_saat_diaktifkan(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->app->make(LanConfigService::class)->aturAktif(true);

        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertOk();
        $resp->assertSee('LAN Aktif');
        $resp->assertSee('Matikan Akses LAN');
        $resp->assertSee('http://192.168.1.17:54321');
    }

    public function test_kartu_lan_menampilkan_jaringan_terputus_bila_tidak_ada_adapter(): void
    {
        $this->jaringan([]);
        $this->app->make(LanConfigService::class)->aturAktif(true);

        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertOk();
        $resp->assertSee('Jaringan Terputus');
        $resp->assertSee('Belum tersedia');
        $resp->assertSee('Tidak ada adapter jaringan fisik yang aktif');
    }

    public function test_klien_lan_tidak_melihat_tombol_pengaturan_lan(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->app->make(LanConfigService::class)->aturAktif(true);

        $resp = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->get('http://192.168.1.17:54321/tentang');

        $resp->assertOk();
        $resp->assertViewHas('dariKomputerUtama', false);
        // Tombol pengaturan tidak dirender untuk klien LAN (string di JS tetap ada,
        // jadi yang diperiksa adalah elemen tombolnya).
        $resp->assertDontSee('id="btn-lan-toggle"', false);
        $resp->assertDontSee('id="btn-firewall-buat"', false);
        $resp->assertSee('hanya bisa diubah dari komputer utama');
    }

    public function test_kartu_lan_menghitung_komputer_lain_yang_sedang_terhubung(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->app->make(LanConfigService::class)->aturAktif(true);

        DB::table('sessions')->insert([
            'id' => 'uji-sesi-lan-1',
            'user_id' => User::first()->id,
            'ip_address' => '192.168.1.20',
            'user_agent' => 'uji',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $resp = $this->get('http://127.0.0.1:54321/tentang');

        $resp->assertOk();
        $resp->assertViewHas('lan', fn (array $lan): bool => $lan['klien_aktif'] === 1);
    }
}
