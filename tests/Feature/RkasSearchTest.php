<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\MasterProgram;
use App\Models\User;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkasSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->user = User::first();
        $this->actingAs($this->user);
    }

    public function test_search_kegiatan_match_query(): void
    {
        $resp = $this->getJson(route('api.search.kegiatan', ['q' => '03.02']));
        $resp->assertOk();
        $resp->assertJsonStructure(['results']);
        $results = $resp->json('results');
        $this->assertNotEmpty($results);
        $this->assertTrue(collect($results)->contains(fn ($r) => str_contains($r['kode'], '03.02') || str_contains($r['nama'], '03.02')));
    }

    public function test_search_kegiatan_empty_query_tidak_error_dan_limit_25(): void
    {
        $resp = $this->getJson(route('api.search.kegiatan', ['q' => '']));
        $resp->assertOk();
        $this->assertLessThanOrEqual(25, count($resp->json('results')));
    }

    public function test_search_kegiatan_limit_ditegakkan(): void
    {
        // Buat 30 program dengan prefix unik LIMITTEST
        for ($i = 1; $i <= 30; $i++) {
            MasterProgram::create(['kode' => '99.99.'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'nama' => 'LIMITTEST Program '.$i]);
        }
        $resp = $this->getJson(route('api.search.kegiatan', ['q' => 'LIMITTEST']));
        $resp->assertOk();
        $this->assertCount(25, $resp->json('results'), 'Limit 25 harus ditegakkan');
    }

    public function test_search_rekening_match_query(): void
    {
        $resp = $this->getJson(route('api.search.rekening', ['q' => '5.1.02.02.01']));
        $resp->assertOk();
        $results = $resp->json('results');
        $this->assertNotEmpty($results);
        $this->assertTrue(collect($results)->contains(fn ($r) => str_contains($r['kode'], '5.1.02.02.01')));
    }

    public function test_search_rekening_empty_query_limit_25(): void
    {
        $resp = $this->getJson(route('api.search.rekening', ['q' => '']));
        $resp->assertOk();
        $this->assertLessThanOrEqual(25, count($resp->json('results')));
    }

    public function test_search_barang_match_prefix_nama(): void
    {
        // Cari dengan prefix nama â€” seeder punya "Kertas HVS A4..."
        $resp = $this->getJson(route('api.search.barang', ['q' => 'Kertas HVS']));
        $resp->assertOk();
        $results = $resp->json('results');
        $this->assertNotEmpty($results);
        $this->assertTrue(collect($results)->contains(fn ($r) => str_starts_with($r['nama'], 'Kertas HVS')));
    }

    public function test_search_barang_custom_prefix_dan_kode(): void
    {
        KodeBarang::create(['kode' => 'KB-UNIQ-SEARCH', 'nama' => 'UNIQTEST Barang Cari', 'satuan_default' => 'buah', 'harga_acuan' => 9999]);
        $resp = $this->getJson(route('api.search.barang', ['q' => 'UNIQTEST']));
        $resp->assertOk();
        $this->assertTrue(collect($resp->json('results'))->contains(fn ($r) => $r['kode'] === 'KB-UNIQ-SEARCH'));

        // Cari via kode substring
        $resp2 = $this->getJson(route('api.search.barang', ['q' => 'KB-UNIQ']));
        $resp2->assertOk();
        $this->assertNotEmpty($resp2->json('results'));
    }

    public function test_search_barang_empty_query_limit_30_dan_tidak_error(): void
    {
        $resp = $this->getJson(route('api.search.barang', ['q' => '']));
        $resp->assertOk();
        $this->assertLessThanOrEqual(30, count($resp->json('results')));
    }

    public function test_search_barang_limit_30_ditegakkan(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            KodeBarang::create(['kode' => 'KB-LIMIT-'.$i, 'nama' => 'LIMITBARANG Nama '.$i, 'satuan_default' => 'buah', 'harga_acuan' => 1000]);
        }
        $resp = $this->getJson(route('api.search.barang', ['q' => 'LIMITBARANG']));
        $resp->assertOk();
        $this->assertCount(30, $resp->json('results'), 'Limit 30 untuk barang harus ditegakkan');
    }

    public function test_search_barang_query_kosong_tidak_full_scan_error(): void
    {
        // Pastikan endpoint tetap 200 walau tanpa q
        $this->getJson('/api/search/barang')->assertOk();
        $this->getJson('/api/search/kegiatan')->assertOk();
        $this->getJson('/api/search/rekening')->assertOk();
    }

    public function test_search_barang_kata_tengah_di_nama_tetap_ditemukan(): void
    {
        KodeBarang::create([
            'kode' => 'KB-LOOSE-MIDDLE',
            'nama' => 'Spidol Whiteboard Snowman Hitam',
            'satuan_default' => 'buah',
            'harga_acuan' => 12000,
        ]);

        // Kata kunci bukan di awal nama harus tetap menemukan barang.
        $results = $this->getJson(route('api.search.barang', ['q' => 'snowman']))->assertOk()->json('results');
        $this->assertTrue(collect($results)->contains(fn ($r) => $r['kode'] === 'KB-LOOSE-MIDDLE'));
    }

    public function test_search_barang_beberapa_kata_urutan_bebas(): void
    {
        KodeBarang::create([
            'kode' => 'KB-LOOSE-MULTI',
            'nama' => 'Kertas HVS A4 70gr Merk LOOSETEST',
            'satuan_default' => 'rim',
            'harga_acuan' => 55000,
        ]);

        $urutNormal = $this->getJson(route('api.search.barang', ['q' => 'kertas hvs loosetest']))->json('results');
        $this->assertTrue(collect($urutNormal)->contains(fn ($r) => $r['kode'] === 'KB-LOOSE-MULTI'));

        // Urutan kata dibalik dan tetap harus ditemukan.
        $urutBebas = $this->getJson(route('api.search.barang', ['q' => 'loosetest a4 hvs']))->json('results');
        $this->assertTrue(collect($urutBebas)->contains(fn ($r) => $r['kode'] === 'KB-LOOSE-MULTI'));
    }

    public function test_search_barang_bisa_lewat_kode_rekening_dan_wildcard_aman(): void
    {
        KodeBarang::create([
            'kode' => 'KB-LOOSE-REK',
            'nama' => 'Barang Uji Rekening LOOSEREK',
            'kode_rekening' => '5.1.02.01.01.0099',
            'satuan_default' => 'buah',
            'harga_acuan' => 1000,
        ]);

        $results = $this->getJson(route('api.search.barang', ['q' => '5.1.02.01.01.0099']))->json('results');
        $this->assertTrue(collect($results)->contains(fn ($r) => $r['kode'] === 'KB-LOOSE-REK'));

        // Karakter wildcard tidak boleh bikin error / pola aneh.
        $this->getJson(route('api.search.barang', ['q' => '%_%']))->assertOk()->assertJsonStructure(['results']);
    }
}
