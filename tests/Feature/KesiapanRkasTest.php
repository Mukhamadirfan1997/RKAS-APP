<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\KesiapanRkasService;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kesiapan RKAS: 12 syarat (pagu, JUKNIS, kelengkapan & mutu data item) +
 * gerbang pengesahan + halaman drill-down.
 */
class KesiapanRkasTest extends TestCase
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
     * @param  array<int, float>  $jumlahPerBulan
     */
    private function buatItem(
        MasterProgram $program,
        MasterKodeRekening $rekening,
        string $uraian,
        float $harga,
        array $jumlahPerBulan,
        ?KodeBarang $barang = null,
        string $keterangan = ''
    ): RkasItem {
        $total = array_sum($jumlahPerBulan);
        $item = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'kode_barang_id' => $barang?->id,
            'uraian' => $uraian,
            'keterangan_kustom' => $keterangan,
            'volume' => $harga > 0 ? $total / $harga : 0,
            'satuan' => 'unit',
            'harga_satuan' => $harga,
            'harga_satuan_arkas' => (float) ($barang->harga_acuan ?? 0),
            'jumlah' => $total,
            'no_urut' => ((int) RkasItem::where('tahun_anggaran_id', $this->ta->id)->max('no_urut')) + 1,
        ]);

        foreach ($jumlahPerBulan as $bulan => $jumlah) {
            $item->alokasiBulan()->create([
                'bulan' => $bulan,
                'volume' => $harga > 0 ? $jumlah / $harga : 0,
                'satuan' => 'unit',
                'jumlah' => $jumlah,
            ]);
        }

        return $item;
    }

    /**
     * Susun RKAS yang benar-benar siap: pagu 10 juta, buku 20%, Tahap I tepat 50%,
     * sisa pagu Rp 0, tanpa temuan data.
     */
    private function siapkanRkasSiap(): void
    {
        RkasItem::query()->delete();

        $this->ta->update([
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'target_barjas' => null,
            'target_modal_mesin' => null,
            'target_modal_aset' => null,
        ]);

        $programBuku = MasterProgram::where('kode', config('juknis.buku.kode_program')[0])->firstOrFail();
        $rekeningBuku = MasterKodeRekening::where('kode', 'like', '5.2.05%')->firstOrFail();
        $programUmum = MasterProgram::where('kode', '04.06.05')->firstOrFail();
        $rekeningUmum = MasterKodeRekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();

        // Buku Rp 2.000.000 (20%) — Jan (Tahap I) & Jul (Tahap II)
        $this->buatItem($programBuku, $rekeningBuku, 'Buku Pelajaran Kurikulum Merdeka', 200000, [
            1 => 1000000,
            7 => 1000000,
        ]);

        // Belanja umum Rp 8.000.000 — Tahap I Rp 4.000.000, Tahap II Rp 4.000.000
        $this->buatItem($programUmum, $rekeningUmum, 'Alat Tulis Kantor Sekolah', 500000, [
            2 => 2000000,
            3 => 2000000,
            8 => 2000000,
            9 => 2000000,
        ]);
    }

    public function test_halaman_kesiapan_tampil_dengan_skor_dan_syarat(): void
    {
        $resp = $this->get(route('kesiapan.index', ['tahun' => $this->ta->tahun]));

        $resp->assertOk();
        $resp->assertSee('Kesiapan RKAS');
        $resp->assertSee('Seluruh pagu teranggarkan');
        $resp->assertSee('Pagu Tahap I + Tahap II = Pagu Total');
        $resp->assertSee('Tidak ada harga di atas batas atas SSH');
    }

    public function test_data_contoh_belum_siap_karena_sisa_pagu(): void
    {
        $hasil = (new KesiapanRkasService($this->ta->fresh()))->evaluasi();

        $this->assertFalse($hasil['siap']);
        $this->assertLessThan(100, $hasil['skor']);
        $this->assertContains('Seluruh pagu teranggarkan (sisa Rp 0)', $hasil['gagal']);

        $sisaPagu = collect($hasil['checks'])->firstWhere('kunci', 'sisa_pagu');
        $this->assertNotNull($sisaPagu);
        $this->assertFalse($sisaPagu['ok']);
        $this->assertNotEmpty($sisaPagu['masalah']);
        $this->assertStringContainsString('rkas', $sisaPagu['masalah'][0]['tautan']);
    }

    public function test_sahkan_ditolak_bila_kesiapan_belum_seratus_persen(): void
    {
        $resp = $this->post(route('pengaturan.pengesahan.sahkan'), ['tahun' => $this->ta->tahun]);

        $resp->assertRedirect();
        $resp->assertSessionHasErrors(['error']);

        $pesan = session('errors')->first('error');
        $this->assertStringContainsString('belum siap disahkan', $pesan);
        $this->assertStringContainsString('Kesiapan RKAS', $pesan);

        $this->assertSame('Draft', $this->ta->fresh()->status_pengesahan, 'RKAS tidak boleh disahkan saat belum siap');
        $this->assertSame(0, AuditLog::where('action', 'rkas.sahkan')->count());
    }

    public function test_sahkan_paksa_wajib_alasan_minimal_sepuluh_karakter(): void
    {
        $resp = $this->post(route('pengaturan.pengesahan.sahkan'), [
            'tahun' => $this->ta->tahun,
            'konfirmasi_paksa' => 1,
            'alasan_paksa' => 'pendek',
        ]);

        $resp->assertSessionHasErrors(['error']);
        $this->assertStringContainsString('minimal 10 karakter', session('errors')->first('error'));
        $this->assertSame('Draft', $this->ta->fresh()->status_pengesahan);
    }

    public function test_sahkan_diizinkan_saat_semua_syarat_terpenuhi(): void
    {
        $this->siapkanRkasSiap();

        $hasil = (new KesiapanRkasService($this->ta->fresh()))->evaluasi();
        $this->assertTrue($hasil['siap'], 'Syarat yang gagal: '.implode('; ', $hasil['gagal']));
        $this->assertSame(100, $hasil['skor']);

        $resp = $this->post(route('pengaturan.pengesahan.sahkan'), ['tahun' => $this->ta->tahun]);
        $resp->assertSessionHas('success');
        $this->assertSame('Disahkan', $this->ta->fresh()->status_pengesahan);

        $log = AuditLog::where('action', 'rkas.sahkan')->latest('id')->first();
        $this->assertFalse($log->new_values['kesiapan']['paksa']);
        $this->assertSame([], $log->new_values['kesiapan']['belum_terpenuhi']);
    }

    public function test_item_tanpa_volume_dan_harga_di_atas_ssh_terdeteksi(): void
    {
        $this->siapkanRkasSiap();

        // Barang dengan batas atas SSH, dibeli jauh di atas batas.
        $barang = KodeBarang::where('kode', 'KB-003')->firstOrFail(); // harga acuan 55.000
        $barang->update(['harga_max' => 60000]);

        $program = MasterProgram::where('kode', '04.06.05')->firstOrFail();
        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();

        $this->buatItem($program, $rekening, 'Kertas HVS di atas SSH', 90000, [4 => 90000], $barang);

        // Item tanpa alokasi bulan sama sekali.
        $itemKosong = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Item Tanpa Volume Bulan',
            'volume' => 1,
            'satuan' => 'unit',
            'harga_satuan' => 0,
            'jumlah' => 0,
            'no_urut' => ((int) RkasItem::where('tahun_anggaran_id', $this->ta->id)->max('no_urut')) + 1,
        ]);
        $this->assertNotNull($itemKosong->id);

        $hasil = (new KesiapanRkasService($this->ta->fresh()))->evaluasi();
        $cek = collect($hasil['checks'])->keyBy('kunci');

        $this->assertFalse($cek['ssh']['ok']);
        $this->assertStringContainsString('melebihi batas atas SSH', $cek['ssh']['masalah'][0]['detail']);
        $this->assertStringContainsString('90.000', $cek['ssh']['masalah'][0]['detail']);

        $this->assertFalse($cek['item_nilai']['ok']);
        $this->assertStringContainsString('Item Tanpa Volume Bulan', collect($cek['item_nilai']['masalah'])->pluck('judul')->implode(' | '));
    }

    public function test_uraian_kosong_dan_baris_kembar_terdeteksi(): void
    {
        $this->siapkanRkasSiap();

        $program = MasterProgram::where('kode', '04.06.05')->firstOrFail();
        $rekening = MasterKodeRekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();

        // Dua baris identik (uraian, rekening, harga, satuan sama) + satu uraian kosong.
        $this->buatItem($program, $rekening, 'Kertas HVS A4', 50000, [5 => 50000]);
        $this->buatItem($program, $rekening, 'Kertas HVS A4', 50000, [5 => 50000]);
        $this->buatItem($program, $rekening, '', 50000, [5 => 50000]);

        $hasil = (new KesiapanRkasService($this->ta->fresh()))->evaluasi();
        $cek = collect($hasil['checks'])->firstWhere('kunci', 'uraian');

        $this->assertFalse($cek['ok']);
        $judul = collect($cek['masalah'])->pluck('judul')->implode(' | ');
        $this->assertStringContainsString('(uraian kosong)', $judul);
        $this->assertStringContainsString('Kertas HVS A4', $judul);
        $this->assertStringContainsString('2 baris identik', collect($cek['masalah'])->pluck('detail')->implode(' | '));
    }

    public function test_pagu_tahap_tidak_sama_dengan_pagu_total_terdeteksi(): void
    {
        $this->ta->update([
            'pagu_total' => 10000000,
            'pagu_tahap1' => 4000000,
            'pagu_tahap2' => 4000000,
        ]);

        $hasil = (new KesiapanRkasService($this->ta->fresh()))->evaluasi();
        $cek = collect($hasil['checks'])->firstWhere('kunci', 'pagu_tahap_total');

        $this->assertFalse($cek['ok']);
        $this->assertStringContainsString('selisih', $cek['ringkas']);
        $this->assertStringContainsString('8.000.000', $cek['masalah'][0]['detail']);
        $this->assertStringContainsString('10.000.000', $cek['masalah'][0]['detail']);
    }

    public function test_item_tanpa_rekening_terdeteksi(): void
    {
        $this->siapkanRkasSiap();

        $program = MasterProgram::where('kode', '04.06.05')->firstOrFail();
        RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => null,
            'uraian' => 'Item Tanpa Rekening',
            'volume' => 1,
            'satuan' => 'unit',
            'harga_satuan' => 1000,
            'jumlah' => 1000,
            'no_urut' => ((int) RkasItem::where('tahun_anggaran_id', $this->ta->id)->max('no_urut')) + 1,
        ]);

        $hasil = (new KesiapanRkasService($this->ta->fresh()))->evaluasi();
        $cek = collect($hasil['checks'])->firstWhere('kunci', 'item_lengkap');

        $this->assertFalse($cek['ok']);
        $this->assertStringContainsString('Item Tanpa Rekening', collect($cek['masalah'])->pluck('judul')->implode(' | '));
    }
}
