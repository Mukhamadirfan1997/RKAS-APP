<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\User;
use App\Services\JaringanService;
use App\Services\LanConfigService;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\JaringanUji;
use Tests\TestCase;

/**
 * Pengaturan Akses LAN (Tahap 3): siapa yang boleh mengubah, apa yang dicatat,
 * dan apa yang TIDAK boleh berubah.
 */
class LanPengaturanTest extends TestCase
{
    use RefreshDatabase;

    private string $berkasLan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);

        $folder = storage_path('framework/testing');
        File::ensureDirectoryExists($folder);
        $this->berkasLan = $folder.'/lan-pengaturan-'.bin2hex(random_bytes(4)).'.json';

        config(['karsa.lan.berkas' => $this->berkasLan]);

        $this->jaringan([['nama' => 'MediaTek Wi-Fi 6', 'ip' => '192.168.1.17']]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->berkasLan ?? null, isset($this->berkasLan) ? $this->berkasLan.'.tmp' : null] as $berkas) {
            if (is_string($berkas) && is_file($berkas)) {
                @unlink($berkas);
            }
        }

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

    private function lan(): LanConfigService
    {
        return $this->app->make(LanConfigService::class);
    }

    public function test_komputer_utama_bisa_mengaktifkan_lan_dan_tercatat_di_audit(): void
    {
        $this->actingAs(User::first());

        $respons = $this->post('/jaringan/lan', ['aktif' => 1]);

        $respons->assertRedirect(route('tentang.index'));
        $respons->assertSessionHas('success');
        $this->assertTrue($this->lan()->aktif());
        $this->assertTrue((new LanConfigService)->aktif());

        $log = AuditLog::where('action', 'lan.aktifkan')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['aktif' => false], $log->old_values);
        $this->assertSame(['aktif' => true], $log->new_values);
    }

    public function test_komputer_utama_bisa_mematikan_lan_kembali(): void
    {
        $this->actingAs(User::first());
        $this->lan()->aturAktif(true);

        $this->post('/jaringan/lan', ['aktif' => 0])->assertRedirect(route('tentang.index'));

        $this->assertFalse($this->lan()->aktif());
        $this->assertNotNull(AuditLog::where('action', 'lan.matikan')->latest('id')->first());
    }

    public function test_klien_lan_tidak_bisa_mengubah_status_lan(): void
    {
        $pengguna = User::first();
        $this->lan()->aturAktif(true);

        $respons = $this->actingAs($pengguna)
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->from(route('tentang.index'))
            ->post('/jaringan/lan', ['aktif' => 0]);

        $respons->assertRedirect(route('tentang.index'));
        $respons->assertSessionHasErrors('lan');
        $this->assertTrue($this->lan()->aktif());
    }

    public function test_permintaan_json_dari_klien_lan_ditolak_403(): void
    {
        $this->lan()->aturAktif(true);

        $this->actingAs(User::first())
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
            ->postJson('/jaringan/lan', ['aktif' => false])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertTrue($this->lan()->aktif());
    }

    public function test_status_lan_wajib_diisi(): void
    {
        $this->actingAs(User::first())
            ->post('/jaringan/lan', [])
            ->assertSessionHasErrors('aktif');

        $this->assertFalse($this->lan()->aktif());
    }

    public function test_tamu_tidak_bisa_mengubah_status_lan(): void
    {
        $this->post('/jaringan/lan', ['aktif' => 1])->assertRedirect(route('login'));

        $this->assertFalse($this->lan()->aktif());
    }

    public function test_status_json_memuat_alamat_lokal_alamat_lan_dan_jumlah_klien(): void
    {
        $this->lan()->aturAktif(true);

        $respons = $this->actingAs(User::first())->getJson('/jaringan/status');

        $respons->assertOk()
            ->assertJsonPath('aktif', true)
            ->assertJsonPath('status', 'aktif')
            ->assertJsonPath('ip_utama', '192.168.1.17')
            ->assertJsonPath('subnet', ['192.168.1.0/24'])
            ->assertJsonStructure(['alamat_lokal', 'alamat_lan', 'klien_aktif', 'antarmuka']);

        $this->assertStringStartsWith('http://192.168.1.17:', (string) $respons->json('alamat_lan'));
        $this->assertStringStartsWith('http://127.0.0.1:', (string) $respons->json('alamat_lokal'));
    }

    public function test_mengaktifkan_lan_tidak_mengubah_data_rkas(): void
    {
        $jumlahItem = RkasItem::count();
        $jumlahBulan = RkasItemBulan::count();

        $this->actingAs(User::first())->post('/jaringan/lan', ['aktif' => 1]);

        $this->assertSame($jumlahItem, RkasItem::count());
        $this->assertSame($jumlahBulan, RkasItemBulan::count());
    }
}
