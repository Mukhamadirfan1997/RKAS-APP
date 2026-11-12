<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JaringanService;
use App\Services\LanConfigService;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\JaringanUji;
use Tests\TestCase;

/**
 * Halaman "Riwayat Versi" di Tentang.
 *
 * Tiga hal yang dikunci di sini (semuanya pernah bermasalah):
 * 1. Isi halaman benar-benar dibaca dari CHANGELOG.md setiap kali dibuka —
 *    bukan teks tertanam di view. Diuji dengan mengganti isi berkas lalu
 *    membuka halaman lagi pada request berikutnya.
 * 2. Tautan "CHANGELOG.md" mengarah ke route unduhan berkas mentah, bukan
 *    me-reload halaman Tentang seperti sebelumnya.
 * 3. Bila berkas tidak ada (mis. belum ikut bundel), halaman tetap ramah dan
 *    route unduh menjawab 404 — bukan error 500.
 */
class ChangelogTentangTest extends TestCase
{
    use RefreshDatabase;

    private string $berkasChangelog;

    private string $folderUji;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->actingAs(User::first());

        // Jaringan tiruan supaya halaman Tentang tidak bergantung kartu jaringan nyata.
        $this->app->instance(
            JaringanService::class,
            new JaringanUji([['nama' => 'Wi-Fi', 'ip' => '192.168.1.17']], $this->app->make(LanConfigService::class))
        );
        config(['karsa.lan.berkas' => storage_path('framework/testing/lan-changelog.json')]);

        $this->folderUji = storage_path('framework/testing');
        File::ensureDirectoryExists($this->folderUji);
        $this->berkasChangelog = $this->folderUji.'/changelog-'.bin2hex(random_bytes(4)).'.md';
    }

    protected function tearDown(): void
    {
        File::delete($this->berkasChangelog ?? '');
        parent::tearDown();
    }

    private function tulisChangelog(string $isi): void
    {
        File::put($this->berkasChangelog, $isi);
        config(['karsa.changelog_path' => $this->berkasChangelog]);
    }

    public function test_riwayat_versi_dibaca_ulang_setiap_halaman_dibuka(): void
    {
        $this->tulisChangelog("# Changelog\n\n## [9.9.9] - 2026-01-01\n\n### Ditambahkan\n- Entri pertama dari berkas.\n");

        $this->get(route('tentang.index'))
            ->assertOk()
            ->assertSee('9.9.9', false)
            ->assertSee('Entri pertama dari berkas.', false);

        // Berkas diubah: halaman berikutnya WAJIB ikut berubah (tidak ada cache).
        $this->tulisChangelog("# Changelog\n\n## [8.8.8] - 2026-02-02\n\n### Diperbaiki\n- Entri kedua dari berkas.\n");

        $this->get(route('tentang.index'))
            ->assertOk()
            ->assertSee('8.8.8', false)
            ->assertSee('Entri kedua dari berkas.', false)
            ->assertDontSee('9.9.9', false);
    }

    public function test_hanya_tiga_pembaruan_terakhir_yang_ditampilkan(): void
    {
        $isi = "# Changelog Uji\n\nSemua perubahan dicatat di sini.\n\n";
        foreach ([['5.5.5', 'terbaru'], ['4.4.4', 'kedua'], ['3.3.3', 'ketiga'], ['2.2.2', 'keempat'], ['1.1.1', 'kelima']] as [$versi, $label]) {
            $isi .= "## [{$versi}] - 2026-01-0".substr($versi, 0, 1)."\n\n### Ditambahkan\n- Entri {$label}.\n\n";
        }
        $this->tulisChangelog($isi);

        $resp = $this->get(route('tentang.index'))->assertOk();

        // Tiga terbaru tampil...
        $resp->assertSee('5.5.5', false)
            ->assertSee('4.4.4', false)
            ->assertSee('3.3.3', false);

        // ...dua sisanya tidak (halaman tidak memanjang).
        $resp->assertDontSee('2.2.2', false)
            ->assertDontSee('1.1.1', false)
            ->assertDontSee('Entri kelima.', false);

        // Pengantar (baris sebelum entri versi pertama) tetap ikut tampil.
        $resp->assertSee('Semua perubahan dicatat di sini.', false);

        $resp->assertSee('Menampilkan 3 dari 5 pembaruan terbaru.', false);
    }

    public function test_unduh_changelog_tetap_memuat_seluruh_riwayat(): void
    {
        $isi = "# Changelog Uji\n\n";
        foreach (['5.5.5', '4.4.4', '3.3.3', '2.2.2', '1.1.1'] as $versi) {
            $isi .= "## [{$versi}] - 2026-01-01\n\n- Entri {$versi}.\n\n";
        }
        $this->tulisChangelog($isi);

        $resp = $this->get(route('tentang.changelog'))->assertOk();

        // Walaupun halaman hanya menampilkan 3, berkas unduhan harus utuh.
        $this->assertSame($isi, $resp->getContent());
    }

    public function test_judul_riwayat_menampilkan_versi_terpasang(): void
    {
        $this->tulisChangelog("# Changelog\n\n## [1.0.0] - 2026-01-01\n");

        $versi = (string) config('karsa.version');

        $this->get(route('tentang.index'))
            ->assertOk()
            ->assertSee('Riwayat Versi', false)
            ->assertSee('Anda di v'.($versi !== '' ? $versi : '—'), false);
    }

    public function test_tautan_changelog_mengarah_ke_route_unduh(): void
    {
        $this->tulisChangelog("# Changelog\n\n## [1.0.0] - 2026-01-01\n");

        $this->get(route('tentang.index'))
            ->assertOk()
            ->assertSee('Unduh CHANGELOG.md', false)
            ->assertSee(route('tentang.changelog'), false);
    }

    public function test_unduh_changelog_mengembalikan_berkas_mentah(): void
    {
        $isi = "# Changelog\n\n## [2.2.2] - 2026-03-03\n\n- Uji unduh berkas mentah.\n";
        $this->tulisChangelog($isi);

        $resp = $this->get(route('tentang.changelog'));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('attachment', (string) $resp->headers->get('content-disposition'));
        $this->assertStringContainsString('CHANGELOG.md', (string) $resp->headers->get('content-disposition'));
        $this->assertSame($isi, $resp->getContent());
    }

    public function test_unduh_changelog_404_bila_berkas_tidak_ada(): void
    {
        config(['karsa.changelog_path' => $this->folderUji.'/tidak-ada-'.bin2hex(random_bytes(3)).'.md']);

        $this->get(route('tentang.changelog'))->assertNotFound();
    }

    public function test_halaman_tentang_tetap_ramah_bila_changelog_tidak_ada(): void
    {
        config(['karsa.changelog_path' => $this->folderUji.'/kosong-'.bin2hex(random_bytes(3)).'.md']);

        $this->get(route('tentang.index'))
            ->assertOk()
            ->assertSee('Belum ada riwayat versi.', false);
    }

    public function test_changelog_asli_memuat_versi_terpasang_sekarang(): void
    {
        // Penjaga isi: berkas yang dibundel harus mencatat versi yang sedang
        // berjalan, agar pengguna tidak melihat riwayat yang basi.
        config(['karsa.changelog_path' => base_path('CHANGELOG.md')]);

        $isi = (string) file_get_contents(base_path('CHANGELOG.md'));
        $versi = (string) config('karsa.version');

        if ($versi === '') {
            $this->markTestSkipped('Versi aplikasi tidak terbaca pada lingkungan ini.');
        }

        $this->assertMatchesRegularExpression('/^##\s+\['.preg_quote($versi, '/').'\]/m', $isi);
    }
}
