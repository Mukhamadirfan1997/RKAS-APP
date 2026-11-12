<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kompresi gzip respons dinamis (app/Http/Middleware/KompresiResponse.php).
 *
 * Fakta yang dikunci: halaman HTML & JSON dikirim ter-gzip bila klien
 * mendukungnya, sedangkan unduhan berkas (PDF/Excel) dan respons kecil
 * TIDAK boleh dikompresi (supaya ukuran & unduhan tetap utuh).
 */
class KompresiResponseTest extends TestCase
{
    use RefreshDatabase;

    private string $pengguna = 'uji@karsa.local';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);

        // Rute uji: isi besar (agar melewati ambang 1 KB) dan isi kecil.
        Route::middleware('web')->get('/uji-gzip-besar', function () {
            return response('<html><body>'.str_repeat('KARSA kompresi uji. ', 200).'</body></html>')
                ->header('Content-Type', 'text/html; charset=UTF-8');
        });

        Route::middleware('web')->get('/uji-gzip-kecil', function () {
            return response('ya')->header('Content-Type', 'text/plain; charset=UTF-8');
        });

        Route::middleware('web')->get('/uji-gzip-json', function () {
            return response()->json(['pesan' => str_repeat('data ', 500)]);
        });
    }

    private function masuk(): User
    {
        return User::where('email', $this->pengguna)->firstOrFail();
    }

    public function test_halaman_html_dikompresi_saat_klien_mendukung_gzip(): void
    {
        $resp = $this->actingAs($this->masuk())->get('/uji-gzip-besar', ['Accept-Encoding' => 'gzip, deflate']);

        $resp->assertOk();
        $this->assertSame('gzip', $resp->headers->get('Content-Encoding'));
        $this->assertStringContainsString('Accept-Encoding', (string) $resp->headers->get('Vary'));

        // Isi setelah didekompresi harus identik dengan aslinya.
        $asli = (string) gzdecode($resp->getContent());
        $this->assertStringContainsString('KARSA kompresi uji.', $asli);
        $this->assertLessThan(strlen($asli), strlen($resp->getContent()), 'Hasil gzip harus lebih kecil dari aslinya.');
    }

    public function test_tanpa_accept_encoding_tidak_dikompresi(): void
    {
        $resp = $this->actingAs($this->masuk())->get('/uji-gzip-besar');

        $resp->assertOk();
        $this->assertNull($resp->headers->get('Content-Encoding'));
        $this->assertStringContainsString('KARSA kompresi uji.', $resp->getContent());
    }

    public function test_gzip_q_nol_dihormati(): void
    {
        $resp = $this->actingAs($this->masuk())->get('/uji-gzip-besar', ['Accept-Encoding' => 'gzip;q=0']);

        $resp->assertOk();
        $this->assertNull($resp->headers->get('Content-Encoding'));
    }

    public function test_isi_kecil_tidak_dikompresi(): void
    {
        $resp = $this->actingAs($this->masuk())->get('/uji-gzip-kecil', ['Accept-Encoding' => 'gzip']);

        $resp->assertOk();
        $this->assertNull($resp->headers->get('Content-Encoding'));
        $this->assertSame('ya', $resp->getContent());
    }

    public function test_json_dikompresi(): void
    {
        $resp = $this->actingAs($this->masuk())->get('/uji-gzip-json', ['Accept-Encoding' => 'gzip']);

        $resp->assertOk();
        $this->assertSame('gzip', $resp->headers->get('Content-Encoding'));

        $data = json_decode((string) gzdecode($resp->getContent()), true);
        $this->assertArrayHasKey('pesan', $data);
    }

    public function test_unduhan_pdf_tidak_dikompresi_ulang(): void
    {
        $resp = $this->actingAs($this->masuk())->get('/rkas/pdf', ['Accept-Encoding' => 'gzip']);

        $resp->assertOk();
        $this->assertNull($resp->headers->get('Content-Encoding'), 'PDF tidak boleh dikompresi middleware (berkas biner).');
        $this->assertSame('%PDF', substr((string) $resp->getContent(), 0, 4));
    }
}
