<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Services\KatalogRekeningManualService;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji pelengkapan kode rekening 133 entri manual sekolah di katalog barang.
 *
 * Fixture pemetaan: database/seeders/data/katalog-rekening-manual-2026-10.json
 */
class KatalogRekeningManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fixtureItems(): array
    {
        return app(KatalogRekeningManualService::class)->items();
    }

    protected function buatBarang(string $kode, string $idBarangArk, string $nama, string $rekening = '.....'): KodeBarang
    {
        return KodeBarang::create([
            'kode' => $kode,
            'id_barang_arkas' => $idBarangArk,
            'nama' => $nama,
            'satuan_default' => 'Unit',
            'harga_acuan' => 0,
            'kode_rekening' => $rekening,
        ]);
    }

    public function test_pemetaan_mencakup_seluruh_133_baris_dan_rekening_tujuannya_ada_di_master(): void
    {
        $items = $this->fixtureItems();

        $this->assertCount(133, $items, 'Jumlah baris pemetaan harus sama dengan jumlah entri manual sekolah.');

        $idBarang = array_column($items, 'id_barang_arkas');
        $this->assertCount(133, array_unique($idBarang), 'Setiap ID barang hanya boleh muncul sekali.');

        foreach ($items as $item) {
            $this->assertNotSame('', (string) $item['kode_rekening']);
            $this->assertNotSame(KatalogRekeningManualService::KODE_REKENING_KOSONG, (string) $item['kode_rekening']);
            $this->assertNotEmpty($item['basis'] ?? null, 'Setiap baris harus menyertakan alasan pemetaan.');
        }

        // Rekening tujuan wajib ada di master, kalau tidak barang tidak bisa dipilih.
        $tujuan = array_values(array_unique(array_column($items, 'kode_rekening')));
        $ada = MasterKodeRekening::whereIn('kode', $tujuan)->pluck('kode')->all();
        $this->assertSame([], array_values(array_diff($tujuan, $ada)));

        // Sebaran: 105 buku + 28 barang non-buku = 133.
        $buku = array_filter($items, fn ($i) => $i['kode_rekening'] === '5.1.02.01.01.0026');
        $this->assertCount(105, $buku);
        $this->assertCount(133 - 105, array_filter($items, fn ($i) => $i['kode_rekening'] !== '5.1.02.01.01.0026'));
    }

    public function test_entri_manual_mendapat_rekening_sesuai_jenis_barang(): void
    {
        // Contoh nyata dari 133 baris (id_barang_arkas diambil dari katalog xlsm).
        $this->buatBarang('UJI-BUKU', '1.3.05.01.1001', 'Kamus Bahasa Indonesia');
        $this->buatBarang('UJI-LAPTOP', '3afkin', 'Perangkat laptop');
        $this->buatBarang('UJI-MIC', '1234-BM-4783', 'STAND MIC');
        $this->buatBarang('UJI-DRUM', '1.3.05.01.1018', 'Drumband 1 set');
        $this->buatBarang('UJI-PUPUK', '5.2.2.02.011', 'Pupuk Organik');
        $this->buatBarang('UJI-LISTRIK', '8.1.02.02.01.100014', 'Pembayaran Listrik Bulanan');
        $this->buatBarang('UJI-RUNTAX', '1.3.05.01.1005', 'running tax-running tax');

        $hasil = app(KatalogRekeningManualService::class)->jalankan();

        $this->assertSame(7, $hasil['diperbarui']);
        $this->assertSame([], $hasil['rekening_tidak_dikenal']);

        $harapan = [
            'UJI-BUKU' => '5.1.02.01.01.0026',      // Bahan Cetak dan Penggandaan (buku)
            'UJI-LAPTOP' => '5.2.02.10.01.0002',    // Belanja Modal Personal Computer
            'UJI-MIC' => '5.2.02.06.01.0001',       // Belanja Modal Peralatan Studio Audio
            'UJI-DRUM' => '5.2.05.02.01.0001',      // Belanja Modal Alat Musik
            'UJI-PUPUK' => '5.1.02.01.01.0008',     // Belanja Bahan-Bahan/Bibit Tanaman
            'UJI-LISTRIK' => '5.1.02.02.01.0061',   // Belanja Tagihan Listrik
            'UJI-RUNTAX' => '5.2.02.05.01.0005',    // Belanja Modal Alat Kantor Lainnya
        ];

        foreach ($harapan as $kode => $rekening) {
            $this->assertSame($rekening, KodeBarang::where('kode', $kode)->value('kode_rekening'), "Rekening {$kode} tidak sesuai.");
        }

        // Tidak boleh ada lagi baris manual yang rekeningnya ".....".
        $this->assertSame(0, KodeBarang::where('kode_rekening', KatalogRekeningManualService::KODE_REKENING_KOSONG)->count());
    }

    public function test_idempoten_dan_tidak_menimpa_rekening_yang_sudah_diisi(): void
    {
        $this->buatBarang('UJI-TETAP', '3afkin', 'Perangkat laptop', '5.2.02.10.02.0005');
        $this->buatBarang('UJI-KOSONG', '1.3.05.01.1005', 'running tax-running tax');

        $service = app(KatalogRekeningManualService::class);

        $pertama = $service->jalankan();
        $this->assertSame(1, $pertama['diperbarui'], 'Hanya baris berkode "....." yang boleh diperbarui.');
        $this->assertSame('5.2.02.10.02.0005', KodeBarang::where('kode', 'UJI-TETAP')->value('kode_rekening'));

        $kedua = $service->jalankan();
        $this->assertSame(0, $kedua['diperbarui'], 'Jalan kedua tidak boleh mengubah apa pun lagi.');
        $this->assertSame(1, KodeBarang::where('kode', 'UJI-KOSONG')->where('kode_rekening', '5.2.02.05.01.0005')->count());
    }

    public function test_rekening_tujuan_yang_belum_ada_di_master_dilewati(): void
    {
        $this->buatBarang('UJI-TANPA-REK', '8.1.02.02.01.100014', 'Pembayaran Listrik Bulanan');

        // Simulasi instalasi yang master rekeningnya belum lengkap.
        MasterKodeRekening::where('kode', '5.1.02.02.01.0061')->delete();

        $hasil = app(KatalogRekeningManualService::class)->jalankan();

        $this->assertSame(0, $hasil['diperbarui']);
        $this->assertContains('5.1.02.02.01.0061', $hasil['rekening_tidak_dikenal']);
        $this->assertSame(
            KatalogRekeningManualService::KODE_REKENING_KOSONG,
            KodeBarang::where('kode', 'UJI-TANPA-REK')->value('kode_rekening'),
            'Baris tidak boleh diberi rekening yang tidak ada di master.'
        );
    }

    public function test_migrasi_menjalankan_pemetaan_saat_aplikasi_dimulai(): void
    {
        $migrasi = require database_path('migrations/2026_10_31_000002_lengkapi_rekening_entri_manual_katalog.php');

        $this->buatBarang('UJI-MIGRASI', '4afkin', 'Perangkat proyektor');
        $migrasi->up();

        $this->assertSame('5.2.02.10.02.0005', KodeBarang::where('kode', 'UJI-MIGRASI')->value('kode_rekening'));

        $migrasi->down();
        $this->assertSame(
            KatalogRekeningManualService::KODE_REKENING_KOSONG,
            KodeBarang::where('kode', 'UJI-MIGRASI')->value('kode_rekening'),
            'Rollback harus mengembalikan penanda "....." untuk baris hasil pemetaan.'
        );
    }
}
