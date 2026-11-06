<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\User;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stabilitas paginasi pencarian katalog + jalan keluar filter rekening.
 */
class RkasSearchPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->actingAs(User::first());
    }

    public function test_paginasi_barang_stabil_saat_nama_kembar(): void
    {
        // Nama identik membuat ORDER BY nama saja tidak deterministik → halaman
        // scroll bisa menggandakan/melewatkan baris. Tiebreaker id wajib.
        for ($i = 1; $i <= 5; $i++) {
            KodeBarang::create([
                'kode' => 'KB-DUP-'.$i,
                'nama' => 'DUPTEST Barang Sama',
                'satuan_default' => 'buah',
                'harga_acuan' => 1000,
            ]);
        }

        $halaman1 = collect($this->getJson(route('api.search.barang', ['q' => 'DUPTEST', 'limit' => 2, 'offset' => 0]))->json('results'))
            ->pluck('kode');
        $halaman2 = collect($this->getJson(route('api.search.barang', ['q' => 'DUPTEST', 'limit' => 2, 'offset' => 2]))->json('results'))
            ->pluck('kode');

        $this->assertCount(2, $halaman1);
        $this->assertCount(2, $halaman2);
        $this->assertCount(0, $halaman1->intersect($halaman2)->all(), 'Halaman 1 dan 2 tidak boleh beririsan.');
        $this->assertSame(
            ['KB-DUP-1', 'KB-DUP-2', 'KB-DUP-3', 'KB-DUP-4'],
            $halaman1->concat($halaman2)->all(),
            'Urutan harus deterministik (id sebagai tiebreaker).'
        );
    }

    public function test_filter_rekening_katalog_bisa_dilepas_untuk_cari_semua_rekening(): void
    {
        KodeBarang::create([
            'kode' => 'KB-REK-A',
            'nama' => 'FILTERTEST Barang Rekening A',
            'kode_rekening' => '5.1.02.01.01.0001',
            'satuan_default' => 'buah',
            'harga_acuan' => 1000,
        ]);
        KodeBarang::create([
            'kode' => 'KB-REK-B',
            'nama' => 'FILTERTEST Barang Rekening B',
            'kode_rekening' => '5.2.02.05.02.0001',
            'satuan_default' => 'buah',
            'harga_acuan' => 2000,
        ]);

        // Dengan filter rekening: hanya barang rekening itu (penyebab sah "0 hasil").
        $terfilter = collect($this->getJson(route('api.search.barang', [
            'q' => 'FILTERTEST',
            'kode_rekening' => '5.2.02.05.02.0001',
        ]))->json('results'))->pluck('kode');
        $this->assertSame(['KB-REK-B'], $terfilter->all());

        // Tanpa filter (aksi "Cari di semua rekening"): dua-duanya ditemukan.
        $semua = collect($this->getJson(route('api.search.barang', ['q' => 'FILTERTEST']))->json('results'))->pluck('kode');
        $this->assertTrue($semua->contains('KB-REK-A') && $semua->contains('KB-REK-B'));
    }
}
