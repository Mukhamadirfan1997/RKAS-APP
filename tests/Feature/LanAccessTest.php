<?php

namespace Tests\Feature;

use App\Models\RkasItem;
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
 * Penjaga akses jaringan KARSA (Tahap 2).
 *
 * Fokus: mode Desktop Offline tidak terpengaruh, LAN mati menolak komputer lain,
 * LAN aktif hanya menerima subnet fisik, VPN/adapter virtual ditolak, dan
 * status tersimpan tanpa menyentuh database.
 */
class LanAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $berkasLan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);

        $folder = storage_path('framework/testing');
        File::ensureDirectoryExists($folder);
        $this->berkasLan = $folder.'/lan-'.bin2hex(random_bytes(4)).'.json';

        config(['karsa.lan.berkas' => $this->berkasLan]);
    }

    protected function tearDown(): void
    {
        if (isset($this->berkasLan) && is_file($this->berkasLan)) {
            @unlink($this->berkasLan);
        }
        if (isset($this->berkasLan) && is_file($this->berkasLan.'.tmp')) {
            @unlink($this->berkasLan.'.tmp');
        }

        parent::tearDown();
    }

    /**
     * Ganti deteksi jaringan dengan daftar adapter buatan.
     *
     * @param  list<array{nama?:string,ip:string,netmask?:string,up?:bool,virtual?:bool}>  $daftar
     */
    private function jaringan(array $daftar): void
    {
        $this->app->instance(
            JaringanService::class,
            new JaringanUji($daftar, $this->app->make(LanConfigService::class))
        );
    }

    private function lan(bool $aktif): void
    {
        $this->app->make(LanConfigService::class)->aturAktif($aktif);
    }

    // ------------------------------------------------------------------
    // Default & penyimpanan status
    // ------------------------------------------------------------------

    public function test_lan_mati_secara_bawaan_pada_instalasi_baru(): void
    {
        $servis = $this->app->make(LanConfigService::class);

        $this->assertFalse($servis->aktif());
        $this->assertFileDoesNotExist($this->berkasLan);

        // Tanpa pengaturan khusus, berkas status berada di storage/app/lan.json.
        config(['karsa.lan.berkas' => null]);
        $this->assertSame(storage_path('app/lan.json'), (new LanConfigService)->berkas());
    }

    public function test_status_lan_tersimpan_di_berkas_tanpa_mengubah_database(): void
    {
        $jumlahTabelSebelum = count(DB::select(
            "select name from sqlite_master where type = 'table'"
        ));

        $this->app->make(LanConfigService::class)->aturAktif(true, 1);

        $this->assertFileExists($this->berkasLan);
        $data = json_decode((string) file_get_contents($this->berkasLan), true);
        $this->assertTrue($data['aktif']);
        $this->assertNotEmpty($data['diubah_pada']);

        // Instance baru (setara proses berikutnya) membaca dari berkas.
        $this->assertTrue((new LanConfigService)->aktif());

        $jumlahTabelSesudah = count(DB::select(
            "select name from sqlite_master where type = 'table'"
        ));
        $this->assertSame($jumlahTabelSebelum, $jumlahTabelSesudah);
    }

    public function test_berkas_status_rusak_dianggap_lan_mati(): void
    {
        file_put_contents($this->berkasLan, '{ini bukan json');

        $this->assertFalse((new LanConfigService)->aktif());
    }

    public function test_status_lan_bisa_dinyalakan_dan_dimatikan_ulang(): void
    {
        $servis = $this->app->make(LanConfigService::class);

        $servis->aturAktif(true);
        $this->assertTrue($servis->aktif());

        $servis->aturAktif(false);
        $this->assertFalse($servis->aktif());
        $this->assertFalse((new LanConfigService)->aktif());
    }

    // ------------------------------------------------------------------
    // Middleware
    // ------------------------------------------------------------------

    public function test_localhost_selalu_boleh_walau_lan_mati(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);

        $respons = $this->get('/');

        // Guest diarahkan ke halaman login — bukan ditolak jaringan.
        $respons->assertStatus(302);
        $respons->assertRedirect(route('login'));
    }

    public function test_komputer_lain_ditolak_saat_lan_mati(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);

        $respons = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->get('/');

        $respons->assertStatus(403);
        $respons->assertSee('Akses LAN Tidak Aktif');
        $respons->assertSee('Akses melalui jaringan lokal sedang dimatikan');
    }

    public function test_permintaan_json_ditolak_dengan_json_bukan_halaman(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->getJson('/api/search/barang?q=spidol')
            ->assertStatus(403)
            ->assertJsonPath('alasan', 'nonaktif')
            ->assertJsonPath('success', false);
    }

    public function test_komputer_lain_di_subnet_sama_boleh_saat_lan_aktif(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->lan(true);

        $respons = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->get('/');

        $respons->assertStatus(302);
        $respons->assertRedirect(route('login'));
    }

    public function test_komputer_lain_di_subnet_berbeda_ditolak(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->lan(true);

        $respons = $this->withServerVariables(['REMOTE_ADDR' => '192.168.5.20'])->get('/');

        $respons->assertStatus(403);
        $respons->assertSee('Jaringan Tidak Diizinkan');
        $respons->assertSee('tidak termasuk jaringan lokal yang diizinkan');
    }

    public function test_klien_vpn_ditolak_walau_alamatnya_privat_rfc1918(): void
    {
        // 10.8.0.1 adalah adapter VPN yang aktif; 192.168.1.17 adalah Wi-Fi fisik.
        $this->jaringan([
            ['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17'],
            ['nama' => 'WireGuard Tunnel', 'ip' => '10.8.0.1'],
        ]);
        $this->lan(true);

        // 10.8.0.7 privat RFC1918, tetapi bukan bagian jaringan fisik mana pun.
        $this->withServerVariables(['REMOTE_ADDR' => '10.8.0.7'])
            ->get('/')
            ->assertStatus(403);

        // Subnet VPN tidak boleh ikut dianggap jaringan yang diizinkan.
        $ringkasan = $this->app->make(JaringanService::class)->ringkasan(9200);
        $this->assertSame(['192.168.1.0/24'], $ringkasan['subnet']);
    }

    public function test_alamat_publik_dan_apipa_ditolak(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->lan(true);

        foreach (['8.8.8.8', '169.254.10.10', '0.0.0.1'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('/')->assertStatus(403);
        }
    }

    public function test_klien_lan_bisa_login_dan_membuka_lembar_kerja(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->lan(true);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->post('/login', ['email' => 'uji@karsa.local', 'password' => 'rahasia123'])
            ->assertRedirect('/');

        $this->assertAuthenticated();

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->get('/rkas')
            ->assertOk()
            ->assertSee('Lembar Kerja');
    }

    public function test_autentikasi_tetap_diwajibkan_untuk_klien_lan(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->lan(true);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->get('/rkas')
            ->assertRedirect(route('login'));
    }

    public function test_lan_diminta_aktif_tetapi_tidak_ada_jaringan_jadi_status_terputus(): void
    {
        $this->jaringan([]);
        $this->lan(true);

        $servis = $this->app->make(JaringanService::class);
        $this->assertSame([], $servis->subnetDiizinkan());
        $this->assertNull($servis->ipUtama());
        $this->assertNull($servis->alamatLan(9200));
        $this->assertSame('terputus', $servis->status());

        // Desktop tetap bisa dipakai, klien LAN ditolak dengan pesan jelas.
        $this->get('/')->assertRedirect(route('login'));
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])->get('/')->assertStatus(403);
    }

    public function test_status_dan_alamat_lan_memakai_port_aktif(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);

        $servis = $this->app->make(JaringanService::class);
        $this->assertSame('nonaktif', $servis->status());

        $this->lan(true);
        $this->assertSame('aktif', $servis->status());

        $ringkasan = $servis->ringkasan(9200);
        $this->assertSame('http://127.0.0.1:9200', $ringkasan['alamat_lokal']);
        $this->assertSame('http://192.168.1.17:9200', $ringkasan['alamat_lan']);
        $this->assertSame('192.168.1.17', $ringkasan['ip_utama']);
        $this->assertTrue($ringkasan['aktif']);
    }

    public function test_ip_lan_utama_mengikuti_perubahan_alamat_dhcp(): void
    {
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
        $this->assertSame('192.168.1.17', $this->app->make(JaringanService::class)->ipUtama());

        // DHCP memberi alamat baru: service baru (permintaan berikutnya) langsung ikut.
        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.99']]);
        $this->assertSame('192.168.1.99', $this->app->make(JaringanService::class)->ipUtama());
    }

    // ------------------------------------------------------------------
    // Helper CIDR & throttle login
    // ------------------------------------------------------------------

    public function test_perhitungan_subnet_cidr(): void
    {
        $this->assertTrue(JaringanService::ipDiCidr('192.168.1.20', '192.168.1.0/24'));
        $this->assertFalse(JaringanService::ipDiCidr('192.168.2.20', '192.168.1.0/24'));
        $this->assertTrue(JaringanService::ipDiCidr('10.8.0.7', '10.8.0.0/24'));
        $this->assertFalse(JaringanService::ipDiCidr('10.8.1.7', '10.8.0.0/24'));
        $this->assertTrue(JaringanService::ipDiCidr('10.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(JaringanService::ipDiCidr('192.168.1.17', '192.168.1.17/32'));
        $this->assertFalse(JaringanService::ipDiCidr('192.168.1.18', '192.168.1.17/32'));

        $this->assertSame(24, JaringanService::prefixDariNetmask('255.255.255.0'));
        $this->assertSame(16, JaringanService::prefixDariNetmask('255.255.0.0'));
        $this->assertSame(8, JaringanService::prefixDariNetmask('255.0.0.0'));
        $this->assertNull(JaringanService::prefixDariNetmask('255.0.255.0'));
        $this->assertSame('192.168.1.0/24', JaringanService::cidr('192.168.1.17', '255.255.255.0'));
        $this->assertSame('192.168.1.17/32', JaringanService::cidr('192.168.1.17', '255.255.255.255'));
    }

    public function test_nama_adapter_virtual_dan_vpn_dikenali(): void
    {
        $this->jaringan([
            ['nama' => 'Wintun Userspace Tunnel', 'ip' => '10.8.0.1'],
            ['nama' => 'TAP-Windows Adapter V9', 'ip' => '10.9.0.1'],
            ['nama' => 'VMware Network Adapter VMnet8', 'ip' => '192.168.200.1'],
            ['nama' => 'Realtek PCIe GbE Family Controller', 'ip' => '10.10.10.10'],
        ]);

        $servis = $this->app->make(JaringanService::class);
        $this->assertSame(['10.10.10.0/24'], $servis->subnetDiizinkan());
        $this->assertSame('10.10.10.10', $servis->ipUtama());
    }

    public function test_login_dibatasi_setelah_lima_percobaan(): void
    {
        $kredensial = ['email' => 'uji@karsa.local', 'password' => 'salah-sekali'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $kredensial)->assertSessionHasErrors('email');
        }

        $respons = $this->post('/login', $kredensial);

        $respons->assertSessionHasErrors([
            'email' => 'Terlalu banyak percobaan masuk (maksimal 5 per menit). Tunggu sebentar lalu coba lagi.',
        ]);
        $this->assertGuest();

        // Sandi benar pun tetap ditahan sampai jendela waktu habis.
        $this->post('/login', ['email' => 'uji@karsa.local', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_tombol_aktif_lan_tidak_mengubah_pengguna_dan_data_rkas(): void
    {
        $jumlahUser = User::count();
        $jumlahItem = RkasItem::count();

        $this->app->make(LanConfigService::class)->aturAktif(true, 1);

        $this->assertSame($jumlahUser, User::count());
        $this->assertSame($jumlahItem, RkasItem::count());
    }
}
