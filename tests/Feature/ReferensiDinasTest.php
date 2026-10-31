<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Services\ReferensiDinasImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji penerapan referensi ARKAS dari Dinas Pendidikan (unduhan 2026-10-31).
 *
 * Fixture: database/seeders/data/referensi-dinas-2026-10.json
 */
class ReferensiDinasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixture(): array
    {
        $path = database_path('seeders/data/referensi-dinas-2026-10.json');
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_kegiatan_referensi_dinas_tersedia_dan_ternormalisasi(): void
    {
        $fixture = $this->fixture();
        $kode = array_column($fixture['kegiatan'], 'kode');

        $this->assertCount(23, $kode);

        foreach ($kode as $satu) {
            $program = MasterProgram::where('kode', $satu)->first();
            $this->assertNotNull($program, "Kegiatan {$satu} belum masuk master_program");
            $this->assertNotEmpty($program->nama);
            $this->assertSame(4, (int) $program->level);
        }

        // Nama program harus memakai istilah DB yang sudah dinormalisasi,
        // bukan istilah mentah dari file dinas.
        $penerimaan = MasterProgram::where('kode', '03.01.06')->first();
        $this->assertSame('Penerimaan Peserta Didik Baru', $penerimaan->sub_program);
        $this->assertSame('Pengembangan Standar Proses', $penerimaan->program);

        $lms = MasterProgram::where('kode', '05.03.03')->first();
        $this->assertSame('Pembiayaan Kegiatan Pembelajaran dan Ekstrakurikuler', $lms->sub_program);
        $this->assertSame('Pengembangan Sarana dan Prasarana Sekolah', $lms->program);

        // Tidak boleh ada sisa ejaan mentah file dinas.
        $this->assertSame(0, MasterProgram::where('sub_program', 'Penerimaan Murid baru')->count());
        $this->assertSame(0, MasterProgram::where('sub_program', 'Pelaksanaan  Administrasi Kegiatan Sekolah')->count());
        $this->assertSame(0, MasterProgram::where('sub_program', 'Pelaksanaan Kegiatan Asesmen/Evaluasi Pembelajaran')->count());
    }

    public function test_rekening_modal_baru_terhubung_ke_jenis_belanja(): void
    {
        foreach (['5.2.02.01.03.0003', '5.2.02.01.03.0004', '5.2.02.01.03.0005'] as $kode) {
            $rekening = MasterKodeRekening::where('kode', $kode)->first();

            $this->assertNotNull($rekening, "Rekening {$kode} belum masuk master");
            $this->assertNotNull($rekening->jenis_belanja_id, "Rekening {$kode} tanpa jenis belanja");
            $this->assertSame('Belanja Modal Peralatan & Mesin', $rekening->jenisBelanja->nama);
            $this->assertSame('MODAL', $rekening->kategori_belanja);
        }

        $this->assertSame('Belanja Modal Compressor', MasterKodeRekening::where('kode', '5.2.02.01.03.0003')->value('nama'));
        $this->assertSame('Belanja Modal Pompa', MasterKodeRekening::where('kode', '5.2.02.01.03.0005')->value('nama'));
    }

    public function test_barang_katalog_5_2_02_01_03_kini_punya_rekening_master(): void
    {
        KodeBarang::create([
            'kode' => 'TEST-KOMP-1',
            'id_barang_arkas' => '1.3.02.01.03.0004.00001-1-520201030004',
            'nama' => 'Genset 40-60 KVA uji',
            'satuan_default' => 'Unit',
            'harga_acuan' => 200000000,
            'harga_max' => 200000000,
            'kode_rekening' => '5.2.02.01.03.0004',
        ]);

        $kodeRekeningKatalog = KodeBarang::where('kode', 'TEST-KOMP-1')->value('kode_rekening');

        $this->assertNotNull(
            MasterKodeRekening::where('kode', $kodeRekeningKatalog)->first(),
            'Barang katalog menunjuk rekening yang tidak ada di master (tidak bisa dipilih).'
        );
    }

    public function test_entri_manual_sekolah_berkode_rekening_belum_diisi_tidak_dihapus(): void
    {
        KodeBarang::create([
            'kode' => 'MANUAL-1',
            'id_barang_arkas' => '2afkin-uji',
            'nama' => 'Perangkat komputer PC',
            'satuan_default' => 'Unit',
            'harga_acuan' => 0,
            'kode_rekening' => ReferensiDinasImporter::KODE_REKENING_BELUM_DIISI,
        ]);

        $hasil = app(ReferensiDinasImporter::class)->jalankan();

        // Entri manual sekolah (buku literasi, laptop, dll) adalah data pengguna,
        // bukan sampah impor: importer tidak boleh menghapusnya.
        $this->assertArrayNotHasKey('katalog_rusak_dihapus', $hasil);
        $this->assertDatabaseHas('kode_barang', ['kode' => 'MANUAL-1']);
    }

    public function test_harga_acuan_mengikuti_referensi_dinas_tanpa_mengubah_nama(): void
    {
        $contoh = $this->fixture()['harga'][0];
        $this->assertNotEmpty($contoh['id_barang_arkas']);

        KodeBarang::create([
            'kode' => 'HARGA-1',
            'id_barang_arkas' => $contoh['id_barang_arkas'],
            'nama' => $contoh['nama'],
            'satuan_default' => 'Unit',
            'harga_acuan' => 1,
            'harga_min' => 0,
            'harga_max' => 1,
            'kode_rekening' => $contoh['kode_rekening'],
        ]);

        app(ReferensiDinasImporter::class)->jalankan();

        $barang = KodeBarang::where('kode', 'HARGA-1')->first();

        $this->assertSame((float) $contoh['harga_acuan'], (float) $barang->harga_acuan);
        $this->assertSame((float) $contoh['harga_max'], (float) $barang->harga_max);
        $this->assertSame($contoh['nama'], $barang->nama, 'Nama barang tidak boleh ikut berubah.');
    }

    public function test_importer_idempoten_tidak_menggandakan_data(): void
    {
        $importer = app(ReferensiDinasImporter::class);

        $this->assertSame(0, $importer->jalankan()['kegiatan_baru']);
        $this->assertSame(0, $importer->jalankan()['rekening_baru']);

        $program = MasterProgram::count();
        $rekening = MasterKodeRekening::count();

        $importer->jalankan();

        $this->assertSame($program, MasterProgram::count());
        $this->assertSame($rekening, MasterKodeRekening::count());
        $this->assertSame(1, MasterProgram::where('kode', '05.03.03')->count());
        $this->assertSame(1, MasterKodeRekening::where('kode', '5.2.02.01.03.0005')->count());
    }
}
