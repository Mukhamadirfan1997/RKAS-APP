<?php

namespace Tests\Feature;

use App\Models\KategoriJuknis;
use App\Models\KodeRekeningKategoriJuknis;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\JuknisValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringJuknisTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
        $this->ta = TahunAnggaran::where('tahun', 2026)->first();
    }

    private function buatItem(string $progKode, string $rekKode, string $uraian, float $jumlah, array $alokasi = []): RkasItem
    {
        $prog = MasterProgram::where('kode', $progKode)->firstOrFail();
        $rek = MasterKodeRekening::where('kode', $rekKode)->firstOrFail();
        $item = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'uraian' => $uraian,
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => $jumlah,
            'jumlah' => $jumlah,
        ]);
        foreach ($alokasi as $bulan => $j) {
            $item->alokasiBulan()->create(['bulan' => $bulan, 'volume' => 1, 'satuan' => 'paket', 'jumlah' => $j]);
        }
        return $item;
    }

    public function test_index_menampilkan_summary_honor_benar(): void
    {
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();

        // Honor valid: program 07.12.01 + rekening 5.1.02.02.01.0013 (Belanja Jasa, prefix honor)
        $this->buatItem('07.12.01', '5.1.02.02.01.0013', 'Honorarium Guru Honorer', 100000, [1 => 100000]);

        $resp = $this->get(route('monitoring.juknis'));
        $resp->assertOk();
        $resp->assertViewHas('summary');

        $summary = $resp->viewData('summary');
        $this->assertEquals(100000, (float) $summary['honor']['total']);
        $this->assertEquals(round(100000 / 180320000 * 100, 2), (float) $summary['honor']['persen']);
        $this->assertEquals('sesuai', $summary['honor']['status']); // 0.05% < 20% maksimal
        $this->assertEquals(20, (float) $summary['honor']['batas_persen']);
    }

    public function test_mapping_sync_berhasil(): void
    {
        $kategori = KategoriJuknis::firstOrFail();
        $reks = MasterKodeRekening::limit(2)->pluck('id')->all();

        $resp = $this->post(route('monitoring.juknis.mapping'), [
            'kategori_juknis_id' => $kategori->id,
            'kode_rekening' => $reks,
        ]);
        $resp->assertRedirect(route('monitoring.juknis'));
        $resp->assertSessionHas('success');

        $this->assertEqualsCanonicalizing($reks, $kategori->fresh()->rekenings()->pluck('master_kode_rekening_id')->all());
    }

    public function test_unmapped_list_menampilkan_rekening_belum_terpetakan(): void
    {
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        // Buat rekening custom tanpa mapping
        $prog = MasterProgram::firstOrFail();
        $rekUnmapped = MasterKodeRekening::create(['kode' => '5.9.99.99.99.9999', 'nama' => 'Rekening Unmapped Test']);
        $item = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rekUnmapped->id,
            'uraian' => 'Belanja unmapped',
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => 50000,
            'jumlah' => 50000,
        ]);

        $resp = $this->get(route('monitoring.juknis'));
        $resp->assertViewHas('unmapped');
        $unmapped = $resp->viewData('unmapped');
        $this->assertTrue($unmapped->contains(fn($i) => $i->id === $item->id), 'Item dengan rekening belum terpetakan harus muncul di unmapped');
    }

    public function test_program_honor_tanpa_jenis_prefix_keyword_tidak_terhitung_honor(): void
    {
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();

        // Program honor 07.12.01, tapi rekening bukan honor (5.1.02.01.01.0031 = Bahan Alat Listrik, Persediaan)
        // dan uraian tanpa kata honor/honorarium → harus TIDAK terhitung
        $this->buatItem('07.12.01', '5.1.02.01.01.0031', 'Pembelian lampu LED', 200000);

        $validator = new JuknisValidator($this->ta);
        $summary = $validator->summary();
        $this->assertEquals(0, (float) $summary['honor']['total'], 'AND: program saja tidak cukup tanpa jenis/prefix/keyword');
        // Pastikan juga tidak masuk buku/sarpras (karena program honor tidak ada di list buku/sarpras)
        $this->assertEquals(0, (float) $summary['buku']['total']);
        $this->assertEquals(0, (float) $summary['sarpras']['total']);
    }

    public function test_prioritas_honor_lebih_tinggi_dari_buku_tidak_dobel(): void
    {
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();

        // Skenario double kalau OR: program Honor (07.12.01) + uraian mengandung kata "buku"
        // Dengan logika AND+prioritas, harus hanya honor, bukan buku
        // Rekening honor: 5.1.02.02.01.0013 (Belanja Jasa, prefix honor) → honor kombinasi true
        $this->buatItem('07.12.01', '5.1.02.02.01.0013', 'Honorarium pembelian buku paket', 150000);

        $validator = new JuknisValidator($this->ta);
        $s = $validator->summary();
        $this->assertEquals(150000, (float) $s['honor']['total']);
        $this->assertEquals(0, (float) $s['buku']['total'], 'Harus 0 di buku karena sudah diambil honor (prioritas honor > buku)');
        $this->assertEquals(150000, (float) $s['total_kategori'], 'total_kategori tidak boleh dobel (harus 150k, bukan 300k)');
    }

    public function test_status_melebihi_kurang_sesuai_di_batas(): void
    {
        // Uji honor maksimal 20% = 36,064,000 ; buku minimal 10% = 18,032,000
        $pagu = (float) $this->ta->pagu_total; // 180320000
        $batasHonor = $pagu * 20 / 100; // 36064000
        $batasBuku = $pagu * 10 / 100; // 18032000

        // --- Honor tepat di batas => sesuai
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        $this->buatItem('07.12.01', '5.1.02.02.01.0013', 'Honorarium batas', $batasHonor);
        $v = new JuknisValidator($this->ta);
        $this->assertEquals('sesuai', $v->honor()['status'], 'Honor tepat di batas maksimal harus sesuai');
        $this->assertEquals($batasHonor, (float) $v->honor()['batas_nominal']);

        // Honor melebihi 1 rupiah => melebihi
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        $this->buatItem('07.12.01', '5.1.02.02.01.0013', 'Honorarium melebihi', $batasHonor + 1);
        $v2 = new JuknisValidator($this->ta);
        $this->assertEquals('melebihi', $v2->honor()['status']);
        $this->assertLessThan(0, (float) $v2->honor()['sisa']);

        // Buku tepat di batas minimal => sesuai
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        // program buku 03.02.02 + rekening buku 5.2.05.xx + uraian buku
        $rekBuku = MasterKodeRekening::where('kode', 'like', '5.2.05%')->firstOrFail();
        $progBuku = MasterProgram::where('kode', '03.02.02')->firstOrFail();
        RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $progBuku->id,
            'master_kode_rekening_id' => $rekBuku->id,
            'uraian' => 'Pembelian buku siswa',
            'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => $batasBuku, 'jumlah' => $batasBuku,
        ]);
        $v3 = new JuknisValidator($this->ta);
        $this->assertEquals('sesuai', $v3->buku()['status'], 'Buku tepat di batas minimal harus sesuai');

        // Buku kurang 1 rupiah => kurang
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $progBuku->id,
            'master_kode_rekening_id' => $rekBuku->id,
            'uraian' => 'Buku kurang',
            'volume' => 1, 'satuan' => 'paket', 'harga_satuan' => $batasBuku - 1, 'jumlah' => $batasBuku - 1,
        ]);
        $v4 = new JuknisValidator($this->ta);
        $this->assertEquals('kurang', $v4->buku()['status']);
        $this->assertGreaterThan(0, (float) $v4->buku()['sisa']);
    }
}
