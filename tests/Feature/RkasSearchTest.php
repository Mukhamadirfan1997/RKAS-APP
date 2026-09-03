<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\MasterProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkasSearchTest extends TestCase
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
        // Cari dengan prefix nama — seeder punya "Kertas HVS A4..."
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
}
