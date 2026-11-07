<?php

namespace Tests\Feature;

use App\Models\TahunAnggaran;
use App\Models\User;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan pagu per tahap (BOSP): masing-masing tahap maksimal 50% dari
 * Pagu Total 1 tahun — Tahap I (Jan–Jun) dan Tahap II (Jul–Des).
 */
class PaguTahapTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->user = User::first();
        $this->actingAs($this->user);
        $this->ta = TahunAnggaran::where('tahun', 2026)->firstOrFail();
        $this->ta->update(['status_pengesahan' => 'Draft']);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function simpanPagu(array $override = [])
    {
        return $this->post(route('pengaturan.update-pagu'), array_merge([
            'tahun' => $this->ta->tahun,
            'pagu_total' => 100000000,
            'pagu_tahap1' => 50000000,
            'pagu_tahap2' => 50000000,
        ], $override));
    }

    public function test_tahap_1_melebihi_50_persen_ditolak(): void
    {
        $paguAwal = (int) $this->ta->pagu_total;

        $resp = $this->simpanPagu([
            'pagu_tahap1' => 50000001,
            'pagu_tahap2' => 49999999,
        ]);

        $resp->assertSessionHasErrors([
            'pagu_tahap1' => 'Pagu Tahap I (Rp 50.000.001) tidak boleh melebihi 50% dari Pagu Total 1 tahun (maksimal Rp 50.000.000).',
        ]);

        $this->assertSame($paguAwal, (int) $this->ta->fresh()->pagu_total, 'Pagu tidak boleh tersimpan saat melanggar aturan 50%');
    }

    public function test_tahap_2_melebihi_50_persen_ditolak(): void
    {
        $resp = $this->simpanPagu([
            'pagu_tahap1' => 40000000,
            'pagu_tahap2' => 60000000,
        ]);

        $resp->assertSessionHasErrors([
            'pagu_tahap2' => 'Pagu Tahap II (Rp 60.000.000) tidak boleh melebihi 50% dari Pagu Total 1 tahun (maksimal Rp 50.000.000).',
        ]);

        $this->assertNotEquals(100000000, (int) $this->ta->fresh()->pagu_total);
    }

    public function test_tepat_50_persen_diterima(): void
    {
        $resp = $this->simpanPagu();

        $resp->assertRedirect(route('pengaturan.pagu', ['tahun' => $this->ta->tahun]));
        $resp->assertSessionHas('success');

        $ta = $this->ta->fresh();
        $this->assertSame(100000000, (int) $ta->pagu_total);
        $this->assertSame(50000000, (int) $ta->pagu_tahap1);
        $this->assertSame(50000000, (int) $ta->pagu_tahap2);
    }

    public function test_kurang_dari_50_persen_diterima(): void
    {
        $resp = $this->simpanPagu([
            'pagu_tahap1' => 30000000,
            'pagu_tahap2' => 20000000,
        ]);

        $resp->assertSessionHas('success');

        $ta = $this->ta->fresh();
        $this->assertSame(30000000, (int) $ta->pagu_tahap1);
        $this->assertSame(20000000, (int) $ta->pagu_tahap2);
    }

    public function test_pagu_total_nol_tidak_boleh_punya_tahap(): void
    {
        $resp = $this->simpanPagu([
            'pagu_total' => 0,
            'pagu_tahap1' => 1000000,
            'pagu_tahap2' => 0,
        ]);

        $resp->assertSessionHasErrors(['pagu_tahap1']);
    }

    public function test_store_tahun_menolak_tahap_melebihi_50_persen(): void
    {
        $resp = $this->post(route('pengaturan.tahun.store'), [
            'tahun' => 2029,
            'pagu_total' => 100000000,
            'pagu_tahap1' => 60000000,
            'pagu_tahap2' => 40000000,
        ]);

        $resp->assertSessionHasErrors(['pagu_tahap1']);
        $this->assertDatabaseMissing('tahun_anggaran', ['tahun' => 2029]);
    }

    public function test_store_tahun_tanpa_tahap_otomatis_50_50_dan_diterima(): void
    {
        $resp = $this->post(route('pengaturan.tahun.store'), [
            'tahun' => 2029,
            'pagu_total' => 100000000,
        ]);

        $resp->assertSessionHas('success');

        $ta = TahunAnggaran::where('tahun', 2029)->firstOrFail();
        $this->assertSame(50000000, (int) $ta->pagu_tahap1);
        $this->assertSame(50000000, (int) $ta->pagu_tahap2);
    }

    public function test_status_pengesahan_tidak_bisa_diubah_lewat_form_pagu(): void
    {
        $resp = $this->simpanPagu(['status_pengesahan' => 'Disahkan']);

        $resp->assertSessionHas('success');
        $this->assertSame(
            'Draft',
            $this->ta->fresh()->status_pengesahan,
            'Status pengesahan hanya boleh berubah lewat tombol Sahkan / Buka Kembali (ada backup + audit)'
        );
    }
}
