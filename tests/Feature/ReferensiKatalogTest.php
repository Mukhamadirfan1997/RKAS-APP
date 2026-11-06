<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\User;
use App\Services\KatalogRekeningResolver;
use App\Services\ReferensiDinasImporter;
use App\Services\ReferensiKatalogImporter;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji impor katalog "Referensi Barang Jasa & Barang Modal" dari Dinas
 * Pendidikan (fixture: database/seeders/data/referensi-katalog-jasa-modal-2026-11.json).
 *
 * Prinsip yang diuji:
 * - Barang baru ditambahkan, tidak ada barang lama yang dihapus/dinamai ulang.
 * - Setiap barang tanpa kode rekening mendapat rekening yang benar-benar ada
 *   di master_kode_rekening.
 * - Impor idempotent (dijalankan berulang tidak menggandakan baris).
 * - Harga 0 memakai Harga Maksimal agar batas atas SSH tetap terpakai.
 */
class ReferensiKatalogTest extends TestCase
{
    use RefreshDatabase;

    private ?string $berkasUji = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
    }

    protected function tearDown(): void
    {
        if ($this->berkasUji !== null && is_file($this->berkasUji)) {
            @unlink($this->berkasUji);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, array<string, mixed>>  $barang
     */
    protected function berkasFixture(array $barang): string
    {
        $path = storage_path('app/uji-referensi-katalog-'.uniqid().'.json');
        file_put_contents($path, json_encode(['meta' => [], 'barang' => $barang], JSON_UNESCAPED_UNICODE));
        $this->berkasUji = $path;

        return $path;
    }

    // ---------------------------------------------------------------- pemetaan

    public function test_barang_tanpa_rekening_selalu_mendapat_kode_yang_ada_di_master(): void
    {
        // Nama nyata dari file dinas yang tidak menyertakan kode rekening.
        $nama = [
            'BAHASA INDONESIA SD/MI Kls. III',
            'Buku Guru Matematika untuk Sekolah Menengah Pertama Kelas IX',
            'Spidol Whiteboard Snowman',
            'Kertas HVS A4 70 gr',
            'Kursi Guru PAUD',
            'Perangkat laptop',
            'Bola Sepak Size 5',
            'Pupuk Organik',
            'Alat Peraga Edukatif Balok Susun',
            'Tenda Gulung 4m x 6m',
            'Kandang Macan',
            'Mesin pencetak pelet',
        ];

        $resolver = app(KatalogRekeningResolver::class);
        $master = MasterKodeRekening::pluck('kode')->all();

        $this->assertNotEmpty($master, 'Master kode rekening harus tersedia untuk pengujian.');

        foreach ($nama as $satu) {
            $hasil = $resolver->resolve($satu, null, 'RBM', null, 'Eksemplar');

            $this->assertContains(
                $hasil['rekening'],
                $master,
                "Rekening hasil pemetaan untuk \"{$satu}\" tidak ada di master."
            );
        }
    }

    public function test_buku_referensi_modal_mendapat_rekening_buku(): void
    {
        $hasil = app(KatalogRekeningResolver::class)->resolve(
            'BAHASA INDONESIA SD/MI Kls. III',
            null,
            'RBM',
            '0',
            'EKSEMPLAR',
        );

        $this->assertStringStartsWith('5.2.05.01.01', $hasil['rekening'], 'Buku pelajaran harus memakai rekening Belanja Modal Buku.');
        $this->assertSame('5.2.05.01.01.0005', $hasil['rekening'], 'Buku Bahasa Indonesia harus masuk kelompok Buku Ilmu Bahasa.');
    }

    public function test_barang_bersatuan_eksemplar_tanpa_kata_buku_tetap_dianggap_buku(): void
    {
        $hasil = app(KatalogRekeningResolver::class)->resolve('Sekolah untuk Timur', null, 'RBM', '0', 'Eksemplar');

        $this->assertSame('satuan buku', $hasil['dasar']);
        $this->assertStringStartsWith('5.2.05.01.01', $hasil['rekening']);
    }

    // -------------------------------------------------------------- impor data

    public function test_impor_menambah_barang_baru_tanpa_menghapus_barang_lama(): void
    {
        $lama = KodeBarang::create([
            'kode' => 'UJI-LAMA',
            'id_barang_arkas' => 'UJI-LAMA',
            'nama' => 'Kertas HVS A4 80 gr',
            'satuan_default' => 'Rim',
            'harga_acuan' => 50000,
            'harga_min' => 0,
            'harga_max' => 55000,
            'kode_rekening' => '5.1.02.01.01.0025',
        ]);

        $sebelum = KodeBarang::count();

        $berkas = $this->berkasFixture([
            [
                'id' => 'REF-001',
                'nama' => 'Kertas HVS A4 80 gr',
                'satuan' => 'Rim',
                'harga' => 55000,
                'harga_min' => 0,
                'harga_max' => 55000,
                'belanja' => 'BHP',
                'kategori' => '0',
                'rekening_asal' => null,
                'jenis' => 'jasa',
            ],
            [
                'id' => 'REF-002',
                'nama' => 'Buku Matematika Kelas 5',
                'satuan' => 'Eksemplar',
                'harga' => 0,
                'harga_min' => 0,
                'harga_max' => 65000,
                'belanja' => 'RBM',
                'kategori' => '0',
                'rekening_asal' => null,
                'jenis' => 'modal',
            ],
            [
                'id' => 'REF-003',
                'nama' => 'Spidol Whiteboard Snowman',
                'satuan' => 'Buah',
                'harga' => 15000,
                'harga_min' => 12000,
                'harga_max' => 18000,
                'belanja' => 'BHP',
                'kategori' => '0',
                'rekening_asal' => '5.1.02.01.01.0024',
                'jenis' => 'jasa',
            ],
        ]);

        $hasil = app(ReferensiKatalogImporter::class)->jalankan(false, $berkas);

        $this->assertSame(3, $hasil['total_baris']);
        $this->assertSame(2, $hasil['baru'], 'Hanya dua barang baru yang boleh ditambahkan.');
        $this->assertSame(1, $hasil['harga_diperbarui'], 'Barang yang sudah ada hanya diperbarui harganya.');

        $this->assertSame($sebelum + 2, KodeBarang::count(), 'Jumlah baris harus bertambah tepat 2.');

        // Barang lama tetap ada, nama tidak berubah, harga mengikuti referensi.
        $lama->refresh();
        $this->assertSame('Kertas HVS A4 80 gr', $lama->nama);
        $this->assertSame(55000.0, (float) $lama->harga_acuan);
        $this->assertSame(1, KodeBarang::where('nama', 'Kertas HVS A4 80 gr')->count(), 'Barang lama tidak boleh terduplikasi.');

        // Barang baru masuk dengan kode rekening dari pemetaan.
        $buku = KodeBarang::where('nama', 'Buku Matematika Kelas 5')->first();
        $this->assertNotNull($buku);
        $this->assertSame('5.2.05.01.01.0006', $buku->kode_rekening);
        $this->assertSame(65000.0, (float) $buku->harga_acuan, 'Harga 0 harus memakai Harga Maksimal.');
        $this->assertSame(65000.0, (float) $buku->harga_max, 'Batas atas SSH harus terisi agar KONTROL bisa dinilai.');
        $this->assertSame(ReferensiKatalogImporter::SUMBER, $buku->sumber);

        $spidol = KodeBarang::where('nama', 'Spidol Whiteboard Snowman')->first();
        $this->assertNotNull($spidol);
        $this->assertSame('5.1.02.01.01.0024', $spidol->kode_rekening, 'Rekening asal yang ada di master dipakai apa adanya.');
        $this->assertSame(12000.0, (float) $spidol->harga_min);
        $this->assertSame(18000.0, (float) $spidol->harga_max);
    }

    public function test_impor_idempotent_saat_dijalankan_berulang(): void
    {
        $berkas = $this->berkasFixture([
            [
                'id' => 'REF-101',
                'nama' => 'Map Plastik Folio',
                'satuan' => 'Buah',
                'harga' => 3500,
                'harga_min' => 0,
                'harga_max' => 4000,
                'belanja' => 'BHP',
                'kategori' => '0',
                'rekening_asal' => null,
                'jenis' => 'jasa',
            ],
        ]);

        $importer = app(ReferensiKatalogImporter::class);

        $pertama = $importer->jalankan(false, $berkas);
        $this->assertSame(1, $pertama['baru']);

        $jumlah = KodeBarang::count();

        $kedua = $importer->jalankan(false, $berkas);
        $this->assertSame(0, $kedua['baru'], 'Jalan kedua tidak boleh menambah baris baru.');
        $this->assertSame($jumlah, KodeBarang::count());
        $this->assertSame(1, KodeBarang::where('nama', 'Map Plastik Folio')->count());
    }

    public function test_baris_lama_berekening_kosong_dilengkapi_tanpa_mengubah_nama(): void
    {
        $baris = KodeBarang::create([
            'kode' => 'UJI-KOSONG',
            'id_barang_arkas' => 'UJI-KOSONG',
            'nama' => 'Stop Map Biola',
            'satuan_default' => 'Buah',
            'harga_acuan' => 0,
            'kode_rekening' => '.....',
        ]);

        $berkas = $this->berkasFixture([
            [
                'id' => 'REF-201',
                'nama' => 'Stop Map Biola',
                'satuan' => 'Buah',
                'harga' => 5000,
                'harga_min' => 0,
                'harga_max' => 6000,
                'belanja' => 'BHP',
                'kategori' => '0',
                'rekening_asal' => null,
                'jenis' => 'jasa',
            ],
        ]);

        $hasil = app(ReferensiKatalogImporter::class)->jalankan(false, $berkas);

        $this->assertSame(0, $hasil['baru'], 'Baris dengan nama sama tidak boleh digandakan.');
        $this->assertSame(1, $hasil['rekening_dilengkapi']);

        $baris->refresh();
        $this->assertSame('Stop Map Biola', $baris->nama);
        $this->assertNotSame('.....', $baris->kode_rekening);
        $this->assertTrue(
            MasterKodeRekening::where('kode', $baris->kode_rekening)->exists(),
            'Rekening hasil pelengkapan harus ada di master.'
        );
    }

    public function test_perintah_uji_coba_tidak_menulis_ke_database(): void
    {
        $berkas = $this->berkasFixture([
            [
                'id' => 'REF-301',
                'nama' => 'Penghapus Papan Tulis',
                'satuan' => 'Buah',
                'harga' => 4000,
                'harga_min' => 0,
                'harga_max' => 5000,
                'belanja' => 'BHP',
                'kategori' => '0',
                'rekening_asal' => null,
                'jenis' => 'jasa',
            ],
        ]);

        $sebelum = KodeBarang::count();

        $this->artisan('katalog:impor-referensi', ['--uji-coba' => true, '--berkas' => $berkas])
            ->expectsOutputToContain('Uji coba selesai')
            ->assertSuccessful();

        $this->assertSame($sebelum, KodeBarang::count(), 'Mode uji coba tidak boleh menulis apa pun.');
        $this->assertFalse(KodeBarang::where('nama', 'Penghapus Papan Tulis')->exists());
    }

    // ------------------------------------------------------------ data nyata

    public function test_fixture_referensi_katalog_memuat_contoh_barang_yang_dilaporkan_hilang(): void
    {
        $path = app(ReferensiKatalogImporter::class)->pathBerkas();

        $this->assertFileExists($path, 'Fixture referensi katalog harus ikut ter-commit.');

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(15000, count($data['barang']), 'Fixture harus memuat seluruh Barang Jasa + Modal.');

        $contoh = null;
        foreach ($data['barang'] as $barang) {
            if (($barang['id'] ?? null) === '291225129') {
                $contoh = $barang;
                break;
            }
        }

        $this->assertNotNull($contoh, 'Barang 291225129 (BAHASA INDONESIA SD/MI Kls. III) harus ada di fixture.');
        $this->assertSame('BAHASA INDONESIA SD/MI Kls. III', $contoh['nama']);
        $this->assertSame('EKSEMPLAR', $contoh['satuan']);
        $this->assertSame(23100.0, (float) $contoh['harga_max']);
    }

    // ------------------------------------------------- referensi dinas (harga)

    public function test_harga_referensi_dinas_dicocokkan_lewat_nama_dan_rekening(): void
    {
        // Fixture referensi dinas 2026-10 memuat harga Butiran Kaca Rp 32.500.
        $barang = KodeBarang::create([
            'kode' => 'UJI-HARGA-DINAS',
            'id_barang_arkas' => 'KODE-SEKOLAH-999',
            'nama' => 'Butiran Kaca (Glass Bead)-',
            'satuan_default' => 'Kg',
            'harga_acuan' => 1000,
            'harga_min' => 0,
            'harga_max' => 1000,
            'kode_rekening' => '5.1.02.01.01.0001',
        ]);

        app(ReferensiDinasImporter::class)->jalankan();

        $barang->refresh();
        $this->assertSame(
            32500.0,
            (float) $barang->harga_acuan,
            'Harga harus dicocokkan lewat (nama + kode rekening), bukan id_barang_arkas dinas.'
        );
        $this->assertSame(32500.0, (float) $barang->harga_max);
    }

    // --------------------------------------------------------------- rollback

    public function test_rollback_migrasi_hanya_menghapus_baris_hasil_impor(): void
    {
        $impor = KodeBarang::create([
            'kode' => 'UJI-IMPOR',
            'id_barang_arkas' => 'UJI-IMPOR',
            'nama' => 'Barang Hasil Impor',
            'kode_rekening' => '5.1.02.01.01.0024',
            'sumber' => ReferensiKatalogImporter::SUMBER,
        ]);

        $milikSekolah = KodeBarang::create([
            'kode' => 'UJI-SEKOLAH',
            'id_barang_arkas' => 'UJI-SEKOLAH',
            'nama' => 'Barang Milik Sekolah',
            'kode_rekening' => '5.1.02.01.01.0024',
        ]);

        $migrasi = require database_path('migrations/2026_11_04_000001_tambah_sumber_dan_impor_katalog_referensi.php');
        $migrasi->down();

        $this->assertDatabaseMissing('kode_barang', ['id' => $impor->id]);
        $this->assertDatabaseHas('kode_barang', ['id' => $milikSekolah->id]);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('kode_barang', 'sumber'));
    }

    // ------------------------------------------------- dipakai di modal RKAS

    /**
     * Barang hasil impor harus benar-benar bisa dipakai operator: muncul di
     * saran katalog modal "Detail Anggaran Kegiatan", lalu bisa disimpan
     * sebagai baris RKAS dengan acuan SSH dari katalog.
     */
    public function test_barang_hasil_impor_bisa_dipakai_di_modal_rkas(): void
    {
        $buku = KodeBarang::create([
            'kode' => '291225129',
            'id_barang_arkas' => null,
            'nama' => 'BAHASA INDONESIA SD/MI Kls. III',
            'satuan_default' => 'EKSEMPLAR',
            'harga_acuan' => 23100,
            'harga_min' => 0,
            'harga_max' => 23100,
            'kode_rekening' => '5.2.05.01.01.0005',
            'sumber' => ReferensiKatalogImporter::SUMBER,
        ]);

        $this->actingAs(User::first());

        // 1. Muncul di autocomplete uraian modal (cari lewat kode barang).
        $this->getJson('/api/search/barang?q=291225129')
            ->assertOk()
            ->assertJsonFragment(['nama' => 'BAHASA INDONESIA SD/MI Kls. III']);

        // 2. Bisa disimpan sebagai baris RKAS dari modal.
        $this->postJson('/rkas/store', [
            'master_program_id' => MasterProgram::first()->id,
            'master_kode_rekening_id' => MasterKodeRekening::where('kode', '5.2.05.01.01.0005')->firstOrFail()->id,
            'kode_barang_id' => $buku->id,
            'uraian' => $buku->nama,
            'harga_satuan' => 23100,
            'satuan' => 'EKSEMPLAR',
            'alokasi' => [
                '1' => ['volume' => 2, 'satuan' => 'EKSEMPLAR'],
            ],
        ])->assertJson(['success' => true]);

        $item = RkasItem::where('kode_barang_id', $buku->id)->first();

        $this->assertNotNull($item, 'Baris RKAS harus tersimpan dan tetap terhubung ke katalog impor.');
        $this->assertSame(46200.0, (float) $item->jumlah, '2 EKSEMPLAR x Rp 23.100.');
        $this->assertSame(23100.0, (float) $item->harga_satuan_arkas, 'Harga acuan diambil dari katalog impor, bukan input manual.');
        $this->assertSame('OK', $item->kontrol, 'Harga tepat pada batas atas SSH masih dinilai OK.');
        $this->assertSame(46200.0, (float) $item->tahap1, 'Januari termasuk Tahap I.');
        $this->assertSame('EKSEMPLAR', $item->satuan);
    }
}
