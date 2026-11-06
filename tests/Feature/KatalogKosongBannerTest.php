<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jaring pengaman kasus "katalog kosong tapi aplikasi tampak normal".
 *
 * Bila berkas `database-seed.sqlite` gagal disalin saat pemasangan, aplikasi
 * tetap terbuka namun tidak ada satu pun kode barang. Tanpa peringatan,
 * operator hanya melihat pencarian yang "tidak jalan" tanpa sebab jelas.
 */
class KatalogKosongBannerTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::create([
            'name' => 'Operator Uji',
            'email' => 'operator@katalog.test',
            'password' => bcrypt('rahasia123'),
        ]);
    }

    public function test_banner_muncul_saat_katalog_barang_kosong(): void
    {
        // Seeder produksi hanya mengisi master (program/rekening), bukan katalog.
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, KodeBarang::count());

        $this->actingAs($this->operator())
            ->get(route('panduan.index'))
            ->assertOk()
            ->assertSee('Katalog kode barang belum terisi')
            ->assertSee(route('pengaturan.katalog'));
    }

    public function test_banner_hilang_saat_katalog_terisi(): void
    {
        $this->seed(DatabaseSeeder::class);

        KodeBarang::create([
            'kode' => 'KB-UJI-1',
            'nama' => 'Spidol Uji',
            'satuan_default' => 'buah',
            'harga_acuan' => 10000,
        ]);

        $this->actingAs($this->operator())
            ->get(route('panduan.index'))
            ->assertOk()
            ->assertDontSee('Katalog kode barang belum terisi');
    }
}
