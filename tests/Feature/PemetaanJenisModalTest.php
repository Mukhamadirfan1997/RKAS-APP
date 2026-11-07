<?php

namespace Tests\Feature;

use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Services\JenisBelanjaResolver;
use App\Services\JuknisValidator;
use App\Services\RkaGelondonganService;
use Database\Seeders\DataUjiSeeder;
use Database\Seeders\JuknisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengaman "tidak salah hitung lagi" untuk kelompok Belanja Modal.
 *
 * Aturan final (7 Nov 2026):
 *   5.2.02* = Belanja Modal Peralatan & Mesin  (termasuk 5.2.02.05 Alat Kantor/Mebel)
 *   5.2.05* = Belanja Modal Aset Tetap Lainnya (termasuk buku & alat musik/software)
 *
 * Keputusan Dinas tidak boleh mengubah kepatuhan JUKNIS: komponen buku
 * dikenali dari prefix rekening 5.2.05, bukan dari nama jenis belanja.
 */
class PemetaanJenisModalTest extends TestCase
{
    use RefreshDatabase;

    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->ta = TahunAnggaran::where('tahun', 2026)->firstOrFail();
    }

    public function test_rekening_5_2_02_peralatan_mesin_dan_5_2_05_aset_tetap_lainnya(): void
    {
        $peralatan = MasterKodeRekening::with('jenisBelanja')->where('kode', 'like', '5.2.02%')->get();
        $asetLain = MasterKodeRekening::with('jenisBelanja')->where('kode', 'like', '5.2.05%')->get();

        $this->assertNotEmpty($peralatan, 'Master harus punya rekening 5.2.02');
        $this->assertNotEmpty($asetLain, 'Master harus punya rekening 5.2.05');

        foreach ($peralatan as $rekening) {
            $this->assertSame(
                'Belanja Modal Peralatan & Mesin',
                $rekening->jenisBelanja?->nama,
                "Rekening {$rekening->kode} harus berjenis Peralatan & Mesin"
            );
        }

        foreach ($asetLain as $rekening) {
            $this->assertSame(
                'Belanja Modal Aset Tetap Lainnya',
                $rekening->jenisBelanja?->nama,
                "Rekening {$rekening->kode} harus berjenis Aset Tetap Lainnya"
            );
        }
    }

    public function test_mebel_5_2_02_05_masuk_peralatan_mesin_bukan_aset_lainnya(): void
    {
        $mebel = MasterKodeRekening::with('jenisBelanja')->where('kode', '5.2.02.05.02.0001')->firstOrFail();

        $this->assertSame('Belanja Modal Mebel', $mebel->nama);
        $this->assertSame('Belanja Modal Peralatan & Mesin', $mebel->jenisBelanja->nama);
    }

    public function test_tidak_ada_lagi_rekening_berjenis_belanjamodal_buku(): void
    {
        $jumlah = MasterKodeRekening::whereHas('jenisBelanja', fn ($q) => $q->where('nama', 'Belanja Modal Buku'))->count();

        $this->assertSame(0, $jumlah, 'Rekening standar tidak boleh lagi berjenis Belanja Modal Buku (5.2.05 = Aset Tetap Lainnya)');
    }

    public function test_resolver_memakai_prefix_terpanjang(): void
    {
        $resolver = app(JenisBelanjaResolver::class);

        $this->assertSame('Belanja Modal Peralatan & Mesin', $resolver->namaRekening('5.2.02.05.02.0001'));
        $this->assertSame('Belanja Modal Peralatan & Mesin', $resolver->namaRekening('5.2.02.10.01.0002'));
        $this->assertSame('Belanja Modal Aset Tetap Lainnya', $resolver->namaRekening('5.2.05.01.01.0003'));
        $this->assertSame('Belanja Modal Aset Tetap Lainnya', $resolver->namaRekening('5.2.05.02.01.0001'));
        $this->assertSame('Belanja Cetak', $resolver->namaRekening('5.1.02.01.01.0026'));
        $this->assertSame('Belanja Barang Persediaan', $resolver->namaRekening('5.1.02.01.01.0052'));
        $this->assertSame('Belanja Modal Peralatan & Mesin', $resolver->namaRekening('5.2.04.04.02.0002'));
    }

    public function test_seluruh_rekening_master_selaras_dengan_aturan_kode(): void
    {
        $resolver = app(JenisBelanjaResolver::class);

        $rekenings = MasterKodeRekening::with('jenisBelanja')->whereNotNull('jenis_belanja_id')->get();
        $this->assertNotEmpty($rekenings);

        foreach ($rekenings as $rekening) {
            $this->assertSame(
                $resolver->namaRekening($rekening->kode),
                $rekening->jenisBelanja?->nama,
                "Rekening {$rekening->kode} tidak sesuai aturan prefix kode"
            );
        }
    }

    public function test_kepatuhan_buku_tetap_dihitung_setelah_jenis_diselaraskan(): void
    {
        $program = MasterProgram::where('kode', '05.02.03')->firstOrFail();
        $rekening = MasterKodeRekening::where('kode', '5.2.05.01.01.0005')->firstOrFail();

        $this->assertSame('Belanja Modal Aset Tetap Lainnya', $rekening->jenisBelanja->nama);

        RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Pembelian buku Bahasa Indonesia',
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => 20000000,
            'jumlah' => 20000000,
        ]);

        $summary = (new JuknisValidator($this->ta))->summary();

        $this->assertEquals(20000000, (float) $summary['buku']['total'], 'Buku harus tetap terhitung walau jenisnya Aset Tetap Lainnya');
        $this->assertSame('sesuai', $summary['buku']['status']);
    }

    public function test_juknis_seeder_memetakan_rekening_buku_dari_prefix_kode(): void
    {
        $this->seed(JuknisSeeder::class);

        $kategori = KategoriJuknis::with('rekenings')->where('nama', 'Pengadaan Buku')->firstOrFail();
        $rekeningBuku = MasterKodeRekening::where('kode', 'like', '5.2.05%')->pluck('kode');

        $this->assertSame(
            $rekeningBuku->count(),
            $kategori->rekenings->count(),
            'Semua rekening 5.2.05 harus terpetakan ke kategori Pengadaan Buku'
        );

        foreach ($kategori->rekenings as $rekening) {
            $this->assertStringStartsWith('5.2.05', $rekening->kode);
        }
    }

    public function test_gelondongan_membagi_modal_menurut_kode_rekening(): void
    {
        $program = MasterProgram::where('kode', '05.08.02')->firstOrFail();
        $mebel = MasterKodeRekening::where('kode', '5.2.02.05.02.0001')->firstOrFail();
        $laptop = MasterKodeRekening::where('kode', '5.2.02.10.01.0002')->firstOrFail();
        $buku = MasterKodeRekening::where('kode', '5.2.05.01.01.0003')->firstOrFail();

        foreach ([[$mebel, 17500000], [$laptop, 15000000], [$buku, 21672700]] as [$rekening, $jumlah]) {
            RkasItem::create([
                'tahun_anggaran_id' => $this->ta->id,
                'master_program_id' => $program->id,
                'master_kode_rekening_id' => $rekening->id,
                'uraian' => 'Uji gelondongan '.$rekening->kode,
                'volume' => 1,
                'satuan' => 'unit',
                'harga_satuan' => $jumlah,
                'jumlah' => $jumlah,
            ]);
        }

        $hasil = RkaGelondonganService::calculate($this->ta);

        $this->assertEquals(32500000, (float) $hasil['modal_mesin'], 'Mebel 5.2.02.05 + laptop 5.2.02.10 harus masuk Modal Mesin');
        $this->assertEquals(21672700, (float) $hasil['modal_aset_lainnya'], 'Buku 5.2.05 harus masuk Modal Aset Tetap Lainnya');
        $this->assertEquals(
            (float) $hasil['barang_jasa'] + (float) $hasil['modal_mesin'] + (float) $hasil['modal_aset_lainnya'],
            (float) RkaGelondonganService::grandKoreksi($this->ta),
            'Jumlah 3 kategori harus sama dengan grand total semua item (tidak ada yang hilang)'
        );
    }
}
