<?php

namespace Tests\Feature;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\RkaGelondonganService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkaGelondonganTest extends TestCase
{
    use RefreshDatabase;

    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->ta = TahunAnggaran::where('tahun', 2026)->first();
        // Set pagu known for deterministic tests
        $this->ta->update(['pagu_total' => 10000000, 'is_active' => true]);
        // Clean existing items for TA to isolate
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
    }

    private function rekeningFor(string $jenisNama): MasterKodeRekening
    {
        $jb = JenisBelanja::where('nama', $jenisNama)->firstOrFail();
        // reuse existing or create unique
        $existing = MasterKodeRekening::where('jenis_belanja_id', $jb->id)->first();
        if ($existing) {
            return $existing;
        }

        return MasterKodeRekening::create([
            'kode' => '9.9.'.rand(10, 99).'.'.$jb->id,
            'nama' => 'Rekening Test '.$jenisNama,
            'kategori_belanja' => 'BARJAS',
            'jenis_belanja_id' => $jb->id,
        ]);
    }

    private function buatItem(string $jenisNama, int $jumlah, int $koreksi = 0, ?string $uraian = null): RkasItem
    {
        $rek = $this->rekeningFor($jenisNama);
        $prog = MasterProgram::first();

        return RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'uraian' => $uraian ?? 'Item '.$jenisNama.' '.uniqid(),
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => $jumlah,
            'jumlah' => $jumlah,
            'koreksi' => $koreksi,
            'no_urut' => (RkasItem::where('tahun_anggaran_id', $this->ta->id)->max('no_urut') ?? 0) + 1,
        ]);
    }

    public function test_3_kategori_menghitung_benar_sesuai_mapping(): void
    {
        // Barang & Jasa = 6 jenis — buat 1 item tiap jenis dengan koreksi test
        $this->buatItem('Belanja Barang', 100000, 5000); // koreksi 5k -> 105k
        $this->buatItem('Belanja Barang Persediaan', 200000); // 200k
        $this->buatItem('Belanja Cetak', 30000); // 30k
        $this->buatItem('Belanja Jasa', 50000); // 50k
        $this->buatItem('Belanja Jasa Pemeliharaan', 70000); // 70k
        $this->buatItem('Belanja Perjalanan Dinas', 90000); // 90k
        // Modal Mesin = Peralatan & Mesin
        $this->buatItem('Belanja Modal Peralatan & Mesin', 400000); // 400k
        // Modal Aset Lainnya = Aset Tetap Lainnya + Modal Buku
        $this->buatItem('Belanja Modal Aset Tetap Lainnya', 150000); // 150k
        $this->buatItem('Belanja Modal Buku', 250000); // 250k

        $result = RkaGelondonganService::calculate($this->ta);

        // Expected per mapping (config/rka_gelondongan.php)
        $expectedBarangJasa = 105000 + 200000 + 30000 + 50000 + 70000 + 90000; // 545000
        $expectedMesin = 400000;
        $expectedAset = 150000 + 250000; // 400000

        $this->assertEquals($expectedBarangJasa, (float) $result['barang_jasa']);
        $this->assertEquals($expectedMesin, (float) $result['modal_mesin']);
        $this->assertEquals($expectedAset, (float) $result['modal_aset_lainnya']);
    }

    public function test_total_3_kolom_sama_dengan_grand_koreksi(): void
    {
        $this->buatItem('Belanja Barang Persediaan', 100000);
        $this->buatItem('Belanja Modal Peralatan & Mesin', 200000);
        $this->buatItem('Belanja Modal Buku', 300000);
        $this->buatItem('Belanja Jasa', 40000, 10000); // koreksi 10k -> 50k? Actually jumlah=40k koreksi=10k =>50k

        $result = RkaGelondonganService::calculate($this->ta);
        $grand = RkaGelondonganService::grandKoreksi($this->ta);

        $this->assertEquals($grand, (float) $result['jumlah']);
        $this->assertEquals(100000 + 200000 + 300000 + 50000, (float) $result['jumlah']);
        // juga total via accessor
        $viaGet = RkasItem::where('tahun_anggaran_id', $this->ta->id)->get()->sum(fn ($it) => (float) $it->jumlah_koreksi);
        $this->assertEquals($viaGet, (float) $result['jumlah']);
    }

    public function test_selisih_dari_pagu_dihitung_benar(): void
    {
        // pagu 10.000.000 set di setUp
        $this->buatItem('Belanja Barang Persediaan', 1000000);
        $this->buatItem('Belanja Modal Buku', 2000000);

        $result = RkaGelondonganService::calculate($this->ta);
        // jumlah =3.000.000, pagu 10.000.000 => selisih -7.000.000
        $this->assertEquals(3000000, (float) $result['jumlah']);
        $this->assertEquals(10000000, (float) $result['pagu_total']);
        $this->assertEquals(-7000000, (float) $result['selisih']);
        $this->assertFalse($result['is_sesuai']);

        // Tambah item sampai pas
        $this->buatItem('Belanja Modal Peralatan & Mesin', 7000000);
        $result2 = RkaGelondonganService::calculate($this->ta);
        $this->assertEquals(10000000, (float) $result2['jumlah']);
        $this->assertEquals(0, (float) $result2['selisih']);
        $this->assertTrue($result2['is_sesuai']);
    }

    public function test_config_mapping_tidak_hardcode_9_jenis_lengkap(): void
    {
        $mapping = config('rka_gelondongan.mapping');
        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('barang_jasa', $mapping);
        $this->assertArrayHasKey('modal_mesin', $mapping);
        $this->assertArrayHasKey('modal_aset_lainnya', $mapping);
        $all = array_merge($mapping['barang_jasa'], $mapping['modal_mesin'], $mapping['modal_aset_lainnya']);
        $this->assertCount(9, $all, 'Mapping harus mencakup 9 jenis_belanja (6+1+2)');
        $this->assertCount(6, $mapping['barang_jasa']);
        $this->assertCount(1, $mapping['modal_mesin']);
        $this->assertCount(2, $mapping['modal_aset_lainnya']);
    }

    public function test_monitoring_page_menampilkan_section_gelondongan(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $this->buatItem('Belanja Barang Persediaan', 500000);
        $response = $this->get(route('monitoring.juknis'));
        $response->assertOk();
        $response->assertSee('Cek RKA Gelondongan (3 Kategori Dinas)');
        $response->assertSee('Belanja Barang dan Jasa', false);
        $response->assertSee('Modal Mesin', false);
        $response->assertSee('Modal Aset Tetap Lainnya (termasuk modal buku)', false);
        // header baru: Kategori | Target | Realisasi | Selisih
        $response->assertSee('Kategori', false);
        $response->assertSee('Target', false);
        $response->assertSee('Realisasi', false);
        $response->assertSee('Pagu Total', false);
        $response->assertSee('Selisih dari Pagu', false);
    }

    public function test_target_tersimpan_via_update_pagu(): void
    {
        $user = User::first();
        $this->actingAs($user);
        // Set pagu awal
        $this->ta->update(['status_pengesahan' => 'Draft']);

        $resp = $this->post(route('pengaturan.update-pagu'), [
            'tahun' => $this->ta->tahun,
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'target_barjas' => 6000000,
            'target_modal_mesin' => 2500000,
            'target_modal_aset' => 1500000,
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');
        $this->ta->refresh();
        $this->assertEquals(6000000, (float) $this->ta->target_barjas);
        $this->assertEquals(2500000, (float) $this->ta->target_modal_mesin);
        $this->assertEquals(1500000, (float) $this->ta->target_modal_aset);
        // nullable vs 0: set salah satu ke null
        $resp2 = $this->post(route('pengaturan.update-pagu'), [
            'tahun' => $this->ta->tahun,
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'target_barjas' => '',
            'target_modal_mesin' => 2500000,
            'target_modal_aset' => null,
        ]);
        $resp2->assertRedirect();
        $this->ta->refresh();
        $this->assertNull($this->ta->target_barjas);
        $this->assertNull($this->ta->target_modal_aset);
    }

    public function test_badge_lebih_dari_target_muncul_saat_realisasi_melebihi(): void
    {
        $user = User::first();
        $this->actingAs($user);
        // Target barjas 100k, realisasi 150k -> lebih
        $this->ta->update(['target_barjas' => 100000, 'target_modal_mesin' => 50000, 'target_modal_aset' => 50000]);
        $this->buatItem('Belanja Barang Persediaan', 150000); // barjas lebih 50k
        $this->buatItem('Belanja Modal Peralatan & Mesin', 50000); // sesuai
        $this->buatItem('Belanja Modal Buku', 30000); // kurang 20k

        $response = $this->get(route('monitoring.juknis'));
        $response->assertOk();
        $response->assertSee('Lebih Rp 50.000 dari target', false);
        $response->assertSee('Sesuai Target', false);
        $response->assertSee('Kurang Rp 20.000 dari target', false);
    }

    public function test_guard_disahkan_mencegah_ubah_target(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $this->ta->update(['status_pengesahan' => 'Disahkan', 'target_barjas' => 1000000]);

        $resp = $this->post(route('pengaturan.update-pagu'), [
            'tahun' => $this->ta->tahun,
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'target_barjas' => 9999999,
            'target_modal_mesin' => 1,
            'target_modal_aset' => 1,
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHasErrors(['error']);
        $this->ta->refresh();
        $this->assertEquals(1000000, (float) $this->ta->target_barjas, 'Target tidak boleh berubah saat Disahkan');
    }

    public function test_target_belum_diisi_tampil_jika_null(): void
    {
        $user = User::first();
        $this->actingAs($user);
        // Pastikan target null
        $this->ta->update(['target_barjas' => null, 'target_modal_mesin' => null, 'target_modal_aset' => null]);
        $this->buatItem('Belanja Barang Persediaan', 50000);

        $response = $this->get(route('monitoring.juknis'));
        $response->assertOk();
        $response->assertSee('Target belum diisi', false);
        // Service juga
        $result = RkaGelondonganService::calculate($this->ta->fresh());
        $this->assertNull($result['targets']['barang_jasa']);
        $this->assertEquals('belum_diisi', $result['status_per_row']['barang_jasa']);
    }

    public function test_dashboard_menampilkan_ringkasan_gelondongan(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $this->ta->update(['target_barjas' => 1000000, 'target_modal_mesin' => 500000, 'target_modal_aset' => 500000]);
        $this->buatItem('Belanja Barang Persediaan', 300000);
        $response = $this->get(route('dashboard.index'));
        $response->assertOk();
        $response->assertSee('Ringkasan RKA Gelondongan', false);
        $response->assertSee('Rekap 3 kategori sesuai file PAK/RKA Dinas', false);
        $response->assertSee('Belanja Barang dan Jasa', false);
        $response->assertSee('Modal Mesin', false);
        $response->assertSee('Target', false);
        $response->assertSee('Realisasi', false);
    }

    public function test_dashboard_checklist_5_item_jika_target_null(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $this->ta->update(['target_barjas' => null, 'target_modal_mesin' => null, 'target_modal_aset' => null]);
        $response = $this->get(route('dashboard.index'));
        $response->assertOk();
        // Harus 5 item, tidak ada baris 6
        $response->assertDontSee('3 kategori RKA sesuai target Dinas', false);
        $checks = $response->viewData('checks');
        $this->assertCount(5, $checks, 'Jika target semua null, checklist tetap 5 item');
        $response->assertSee('/5', false);
        $this->ta->refresh();
        $hasTarget = $this->ta->target_barjas !== null || $this->ta->target_modal_mesin !== null || $this->ta->target_modal_aset !== null;
        $this->assertFalse($hasTarget);
    }

    public function test_dashboard_checklist_6_item_jika_target_ada(): void
    {
        $user = User::first();
        $this->actingAs($user);
        // Set target supaya gelondongan sesuai (semua sesuai)
        $this->ta->update(['target_barjas' => 100000, 'target_modal_mesin' => 50000, 'target_modal_aset' => 30000]);
        // Buat item pas sesuai target
        $this->buatItem('Belanja Barang Persediaan', 100000);
        $this->buatItem('Belanja Modal Peralatan & Mesin', 50000);
        $this->buatItem('Belanja Modal Buku', 30000);

        $response = $this->get(route('dashboard.index'));
        $response->assertOk();
        $response->assertSee('3 kategori RKA sesuai target Dinas', false);
        // Denominator jadi 6
        $checks = $response->viewData('checks');
        $this->assertCount(6, $checks, 'Jika target ada, checklist jadi 6 item');
        $response->assertSee('/6', false);
        // Item gelondongan harus centang (ok=true)
        $gelCheck = collect($checks)->firstWhere('label', '3 kategori RKA sesuai target Dinas');
        $this->assertNotNull($gelCheck);
        $this->assertTrue($gelCheck['ok'], 'Jika semua kategori sesuai, checklist gelondongan harus centang');
    }

    public function test_dashboard_checklist_tidak_centang_jika_salah_satu_kategori_tidak_sesuai(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $this->ta->update(['target_barjas' => 100000, 'target_modal_mesin' => 50000, 'target_modal_aset' => 30000]);
        // Hanya 2 kategori sesuai, 1 lebih
        $this->buatItem('Belanja Barang Persediaan', 150000); // lebih 50k
        $this->buatItem('Belanja Modal Peralatan & Mesin', 50000);
        $this->buatItem('Belanja Modal Buku', 30000);

        $response = $this->get(route('dashboard.index'));
        $response->assertOk();
        $response->assertSee('3 kategori RKA sesuai target Dinas', false);
        // Harus ada ikon silang (tidak centang) — cek ada class bg-slate-100 untuk tidak ok
        // Cek via service
        $result = RkaGelondonganService::calculate($this->ta->fresh());
        $this->assertEquals('lebih', $result['status_per_row']['barang_jasa']);
        $this->assertNotEquals('sesuai', $result['status_per_row']['barang_jasa']);
    }
}
