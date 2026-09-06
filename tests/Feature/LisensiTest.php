<?php

namespace Tests\Feature;

use App\Models\Lisensi;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\LisensiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LisensiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::first());
    }

    private function setKecamatan(string $kec): void
    {
        $ps = PengaturanSekolah::first();
        $ps->kecamatan = $kec;
        $ps->save();
    }

    private function setInstalledAtDaysAgo(int $days): void
    {
        $lis = Lisensi::first() ?? LisensiService::getLisensiRow();
        $lis->installed_at = now()->subDays($days);
        $lis->save();
    }

    public function test_rejoso_variasi_selalu_tidak_readonly()
    {
        foreach (['Rejoso', 'REJOSO ', 'rejoso', '  ReJoSo  ', 'Kec. Rejoso', 'rejoso barat'] as $v) {
            $this->setKecamatan($v);
            // even if trial expired 40 days ago and no activation
            $this->setInstalledAtDaysAgo(40);
            Lisensi::first()->fresh();
            // ensure no aktivasi
            \App\Models\LisensiAktivasi::truncate();
            $this->assertFalse(LisensiService::isReadOnlyMode(), "Failed for kecamatan variation: {$v}");
            $this->assertTrue(LisensiService::isRejosoExempt());
        }
    }

    public function test_luar_rejoso_dalam_trial_tidak_readonly()
    {
        $this->setKecamatan('Kraksaan');
        $this->setInstalledAtDaysAgo(5);
        \App\Models\LisensiAktivasi::truncate();
        $this->assertFalse(LisensiService::isRejosoExempt());
        $this->assertTrue(LisensiService::isTrialActive());
        $this->assertFalse(LisensiService::isReadOnlyMode());
    }

    public function test_luar_rejoso_lewat_30_hari_tanpa_kode_readonly_dan_blokir_tulis()
    {
        $this->setKecamatan('Kraksaan');
        $this->setInstalledAtDaysAgo(31);
        \App\Models\LisensiAktivasi::truncate();
        $this->assertFalse(LisensiService::isTrialActive());
        $this->assertTrue(LisensiService::isReadOnlyMode());

        $program = MasterProgram::first();
        $rekening = MasterKodeRekening::first();

        // POST should redirect to aktivasi
        $resp = $this->post('/rkas/store', [
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Blokir test',
            'harga_satuan' => 1000,
            'satuan' => 'pcs',
            'alokasi' => [1 => ['volume' => 1, 'satuan' => 'pcs']],
        ]);
        $resp->assertRedirect(route('aktivasi.index'));

        // also block update/delete and export
        $item = \App\Models\RkasItem::first();
        $this->post('/rkas/'.$item->id.'/update', [
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Update blokir',
            'harga_satuan' => 1000,
            'satuan' => 'pcs',
            'alokasi' => [1 => ['volume' => 1, 'satuan' => 'pcs']],
        ])->assertRedirect(route('aktivasi.index'));

        $this->delete('/rkas/'.$item->id.'/delete')->assertRedirect(route('aktivasi.index'));
        $this->get('/rkas/pdf')->assertRedirect(route('aktivasi.index'));
        $this->get('/rkas/export')->assertRedirect(route('aktivasi.index'));
        $this->get('/pengaturan/katalog')->assertOk(); // GET tetap boleh
        $this->get('/rkas')->assertOk(); // GET tetap boleh
    }

    public function test_kode_aktivasi_valid_tidak_lagi_readonly()
    {
        $this->setKecamatan('Kraksaan');
        $this->setInstalledAtDaysAgo(35);
        $device = LisensiService::getOrCreateDeviceCode();
        $tahun = (int) TahunAnggaran::where('is_active', true)->first()->tahun;
        $kode = LisensiService::generateActivationCode($device, $tahun);
        $this->assertTrue(LisensiService::validateActivationCode($device, $tahun, $kode));
        LisensiService::tryActivate($device, $tahun, $kode);
        $this->assertTrue(LisensiService::isYearLicensed($tahun));
        $this->assertFalse(LisensiService::isReadOnlyMode());

        // now POST should succeed
        $program = MasterProgram::first();
        $rekening = MasterKodeRekening::first();
        $resp = $this->postJson('/rkas/store', [
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Setelah aktivasi ok',
            'harga_satuan' => 2000,
            'satuan' => 'pcs',
            'alokasi' => [1 => ['volume' => 2, 'satuan' => 'pcs']],
        ]);
        $resp->assertStatus(200);
    }

    public function test_kode_untuk_tahun_2027_tidak_valid_di_2028()
    {
        $this->setKecamatan('Kraksaan');
        $this->setInstalledAtDaysAgo(40);
        $device = LisensiService::getOrCreateDeviceCode();
        $kode2027 = LisensiService::generateActivationCode($device, 2027);
        // try validate for 2028 should fail
        $this->assertFalse(LisensiService::validateActivationCode($device, 2028, $kode2027));
        // tryActivate for 2028 with code 2027 should not save
        $result = LisensiService::tryActivate($device, 2028, $kode2027);
        $this->assertFalse($result);
        $this->assertFalse(LisensiService::isYearLicensed(2028));
    }

    public function test_kode_acak_ditolak()
    {
        $this->setKecamatan('Kraksaan');
        $this->setInstalledAtDaysAgo(40);
        $device = LisensiService::getOrCreateDeviceCode();
        $tahun = (int) TahunAnggaran::where('is_active', true)->first()->tahun;
        $random = 'XXXX-YYYY-ZZZZ-WWWW';
        $this->assertFalse(LisensiService::validateActivationCode($device, $tahun, $random));
        $this->assertFalse(LisensiService::tryActivate($device, $tahun, $random));
        $this->assertDatabaseMissing('lisensi_aktivasi', ['tahun' => $tahun, 'kode_aktivasi' => $random]);
    }

    public function test_generate_dan_validate_konsisten()
    {
        $device = LisensiService::getOrCreateDeviceCode();
        foreach ([2026,2027,2028,2030] as $t) {
            $kode = LisensiService::generateActivationCode($device, $t);
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $kode);
            // ensure no ambiguous chars
            $this->assertDoesNotMatchRegularExpression('/[0O1I]/', $kode);
            $this->assertTrue(LisensiService::validateActivationCode($device, $t, $kode), "Generate+validate failed for tahun $t");
            // lowercase and without dash also should validate (normalize)
            $lower = strtolower(str_replace('-', '', $kode));
            $this->assertTrue(LisensiService::validateActivationCode($device, $t, $lower));
        }
    }
}
