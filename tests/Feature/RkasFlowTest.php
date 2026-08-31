<?php

namespace Tests\Feature;

use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\JuknisValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkasFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
    }

    public function test_store_rkas_item_with_monthly_allocation()
    {
        $program = MasterProgram::first();
        $rekening = MasterKodeRekening::first();
        $ta = TahunAnggaran::where('tahun', 2026)->first();

        $response = $this->postJson('/rkas/store', [
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Test Belanja Baru',
            'keterangan_kustom' => 'Untuk Ruang Kelas 2',
            'harga_satuan' => 10000,
            'alokasi' => [
                1 => ['volume' => 5, 'satuan' => 'dus'],
                7 => ['volume' => 3, 'satuan' => 'dus'],
                2 => ['volume' => 0, 'satuan' => ''],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $item = RkasItem::where('uraian', 'Test Belanja Baru')->first();
        $this->assertNotNull($item);
        $this->assertEquals(8, (float) $item->volume); // 5 + 3
        $this->assertEquals(80000, (float) $item->jumlah); // 8 x 10000
        $this->assertEquals(2, $item->alokasiBulan()->count());
        $this->assertEquals(50000, (float) $item->tahap1);
        $this->assertEquals(30000, (float) $item->tahap2);
    }

    public function test_update_rkas_item_resyncs_allocation()
    {
        $item = RkasItem::first();
        $program = MasterProgram::first();
        $rekening = MasterKodeRekening::first();

        $this->postJson('/rkas/'.$item->id.'/update', [
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Uraian Diubah',
            'harga_satuan' => 5000,
            'alokasi' => [
                '12' => ['volume' => 10, 'satuan' => 'dus'],
            ],
        ])->assertJson(['success' => true]);

        $item->refresh();
        $this->assertEquals('Uraian Diubah', $item->uraian);
        $this->assertEquals(10, (float) $item->volume);
        $this->assertEquals(50000, (float) $item->jumlah);
        $this->assertEquals(1, $item->alokasiBulan()->count());
        // Desember = Tahap II
        $this->assertEquals(50000, (float) $item->tahap2);
    }

    public function test_pdf_export_downloads()
    {
        $response = $this->get('/rkas/pdf');
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_juknis_mapping_and_monitoring()
    {
        $kategori = KategoriJuknis::first();
        $rekening = MasterKodeRekening::first();

        $this->post('/monitoring/juknis/mapping', [
            'kategori_juknis_id' => $kategori->id,
            'kode_rekening' => [$rekening->id],
        ])->assertRedirect(route('monitoring.juknis'));

        $this->assertEquals(1, $kategori->rekenings()->count());
    }

    public function test_master_data_crud()
    {
        // Program
        $this->post('/master/program', ['kode' => '99.99.99', 'nama' => 'Program Uji'])->assertRedirect();
        $this->assertDatabaseHas('master_program', ['kode' => '99.99.99']);

        // Rekening
        $this->post('/master/rekening', ['kode' => '5.9.99.99.99.9999', 'nama' => 'Rekening Uji', 'kategori_belanja' => 'BARJAS'])->assertRedirect();
        $this->assertDatabaseHas('master_kode_rekening', ['kode' => '5.9.99.99.99.9999']);

        // Barang
        $this->post('/master/barang', ['kode' => 'KB-TEST', 'nama' => 'Barang Uji', 'satuan_default' => 'unit', 'harga_acuan' => 1000])->assertRedirect();
        $this->assertDatabaseHas('kode_barang', ['nama' => 'Barang Uji']);
    }

    public function test_pengaturan_sekolah_and_pagu()
    {
        $this->post('/pengaturan/sekolah', [
            'nama_sekolah' => 'SD UJI COBA',
            'npsn' => '999999',
        ])->assertRedirect();

        $this->assertDatabaseHas('pengaturan_sekolah', ['nama_sekolah' => 'SD UJI COBA']);

        $this->post('/pengaturan/pagu', [
            'pagu_total' => 100000000,
            'pagu_tahap1' => 50000000,
            'pagu_tahap2' => 50000000,
        ])->assertRedirect();

        $this->assertDatabaseHas('tahun_anggaran', ['pagu_total' => 100000000]);
    }

    public function test_juknis_kombinasi_honor_dan_non_honor()
    {
        $ta = TahunAnggaran::where('tahun', 2026)->first();

        // Kombinasi benar: program honor 07.12.01 + rekening honor -> terhitung HONOR
        $progHonor = MasterProgram::where('kode', '07.12.01')->firstOrFail();
        $rekHonor = MasterKodeRekening::where('kode', '5.1.02.02.01.0013')->firstOrFail();
        $itemHonor = RkasItem::create([
            'tahun_anggaran_id' => $ta->id,
            'master_program_id' => $progHonor->id,
            'master_kode_rekening_id' => $rekHonor->id,
            'uraian' => 'Honorarium Guru Honorer',
            'volume' => 1,
            'satuan' => 'bln',
            'harga_satuan' => 100000,
            'jumlah' => 100000,
        ]);
        $itemHonor->alokasiBulan()->create(['bulan' => 1, 'volume' => 1, 'satuan' => 'bln', 'jumlah' => 100000]);

        // Kombinasi SALAH: program non-honor + rekening Belanja Jasa (Sewa) -> TIDAK masuk HONOR
        $progLain = MasterProgram::where('kode', '04.06.05')->first()
            ?? MasterProgram::whereNotIn('kode', config('juknis.honor.kode_program'))->firstOrFail();
        $rekSewa = MasterKodeRekening::where('kode', '5.1.02.02.04.0036')->firstOrFail();
        RkasItem::create([
            'tahun_anggaran_id' => $ta->id,
            'master_program_id' => $progLain->id,
            'master_kode_rekening_id' => $rekSewa->id,
            'uraian' => 'Sewa Kendaraan Kegiatan',
            'volume' => 1,
            'satuan' => 'unit',
            'harga_satuan' => 200000,
            'jumlah' => 200000,
        ]);

        $validator = new JuknisValidator($ta);
        $summary = $validator->summary();

        // Hanya item kombinasi benar yang dihitung honor, bukan belanja jasa sewa
        $this->assertEquals(100000, (float) $summary['honor']['total']);
    }

    public function test_juknis_sarpras_tidak_menangkap_bahan_alat_listrik_persediaan()
    {
        $ta = TahunAnggaran::where('tahun', 2026)->first();
        $progSarpras = MasterProgram::where('kode', '05.08.01')->firstOrFail();

        // Benar: pemeliharaan bangunan/program sarpras + rekening pemeliharaan -> terhitung SARPRAS
        $rekPemeliharaan = MasterKodeRekening::where('kode', '5.1.02.03.03.0010')->firstOrFail();
        RkasItem::create([
            'tahun_anggaran_id' => $ta->id,
            'master_program_id' => $progSarpras->id,
            'master_kode_rekening_id' => $rekPemeliharaan->id,
            'uraian' => 'Pemeliharaan gedung ruang kelas',
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => 100000,
            'jumlah' => 100000,
        ]);

        // Salah: Bahan Alat Listrik (Barang Persediaan) di program sarpras -> TIDAK masuk SARPRAS
        $rekListrik = MasterKodeRekening::where('kode', '5.1.02.01.01.0031')->firstOrFail();
        RkasItem::create([
            'tahun_anggaran_id' => $ta->id,
            'master_program_id' => $progSarpras->id,
            'master_kode_rekening_id' => $rekListrik->id,
            'uraian' => 'Pembelian lampu LED penerangan kantor',
            'volume' => 1,
            'satuan' => 'unit',
            'harga_satuan' => 50000,
            'jumlah' => 50000,
        ]);

        $validator = new JuknisValidator($ta);
        $summary = $validator->summary();

        // Hanya yang benar yang dihitung sarpras; bahan alat listrik persediaan TIDAK masuk
        $this->assertEquals(100000, (float) $summary['sarpras']['total']);
    }
}
