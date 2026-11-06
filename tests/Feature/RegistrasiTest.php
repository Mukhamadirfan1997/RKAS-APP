<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PengaturanSekolah;
use App\Models\TahunAnggaran;
use App\Models\User;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Registrasi awal (instalasi baru) & pemulihan tanpa registrasi.
 *
 * Aturan yang dijaga test ini:
 * - tidak ada akun default: aplikasi baru selalu mendarat di halaman Registrasi;
 * - pengguna lama (sudah punya akun) tidak pernah melihat halaman Registrasi/Pulihkan;
 * - registrasi berhasil mengarah ke halaman login (tidak langsung masuk);
 * - pulihkan backup bisa dilakukan tanpa akun (instalasi baru).
 */
class RegistrasiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $ubah = []): array
    {
        return array_merge([
            'npsn' => '20512345',
            'nama_sekolah' => 'SDN Contoh Satu',
            'status_sekolah' => 'negeri',
            'nama_kepala_sekolah' => 'Kepala Sekolah',
            'nip_kepala_sekolah' => '19700101 199001 1 001',
            'nama_bendahara' => 'Bendahara Sekolah',
            'nip_bendahara' => '19800101 200001 2 002',
            'alamat' => 'Jl. Pendidikan No. 1',
            'desa_kelurahan' => 'Desa Contoh',
            'kecamatan' => 'Kecamatan Contoh',
            'kabupaten_kota' => 'Kabupaten Contoh',
            'provinsi' => 'Jawa Timur',
            'name' => 'Operator Sekolah',
            'email' => 'operator@sekolah.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ubah);
    }

    public function test_instalasi_baru_diarahkan_ke_halaman_registrasi(): void
    {
        $this->assertSame(0, User::count());

        $this->get('/login')->assertRedirect(route('registrasi'));
        $this->get(route('auth.forgot'))->assertRedirect(route('registrasi'));
        $this->get(route('registrasi'))->assertOk()->assertSee('Registrasi');
    }

    public function test_halaman_registrasi_menampilkan_form_profil_dan_akun(): void
    {
        $resp = $this->get(route('registrasi'));

        $resp->assertOk()
            ->assertSee('NPSN')
            ->assertSee('Nama Sekolah')
            ->assertSee('Kata Sandi')
            ->assertSee('Pulihkan tanpa registrasi');
    }

    public function test_registrasi_membuat_akun_profil_dan_tahun_anggaran(): void
    {
        $resp = $this->post(route('registrasi.simpan'), $this->payload());

        $resp->assertRedirect(route('login'));
        $resp->assertSessionHas('status');
        $this->assertGuest();

        $user = User::where('email', 'operator@sekolah.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('rahasia123', $user->password));

        $profil = PengaturanSekolah::first();
        $this->assertSame('20512345', $profil->npsn);
        $this->assertSame('SDN Contoh Satu', $profil->nama_sekolah);
        $this->assertSame('negeri', $profil->status_sekolah);

        // Pagu tidak dikarang: tahun anggaran dibuat dengan nilai 0.
        $tahun = TahunAnggaran::where('tahun', (int) date('Y'))->first();
        $this->assertNotNull($tahun);
        $this->assertEquals(0, (float) $tahun->pagu_total);
        $this->assertTrue((bool) $tahun->is_active);
        $this->assertSame('Draft', $tahun->status_pengesahan);

        $this->assertDatabaseHas('audit_logs', ['action' => 'instalasi.registrasi']);
    }

    public function test_registrasi_gagal_bila_konfirmasi_kata_sandi_beda(): void
    {
        $this->post(route('registrasi.simpan'), $this->payload(['password_confirmation' => 'lainlagi99']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
        $this->assertSame(0, PengaturanSekolah::count());
    }

    public function test_registrasi_tidak_bisa_dipakai_setelah_ada_akun(): void
    {
        // Pengguna lama: sudah punya akun → halaman registrasi ditutup.
        $this->seed(DataUjiSeeder::class);
        $jumlahAwal = User::count();

        $this->get(route('registrasi'))->assertRedirect(route('login'));
        $this->post(route('registrasi.simpan'), $this->payload())->assertRedirect(route('login'));

        $this->assertSame($jumlahAwal, User::count());
        $this->assertNull(User::where('email', 'operator@sekolah.id')->first());
    }

    public function test_halaman_pulihkan_tersedia_tanpa_registrasi(): void
    {
        $this->get(route('pulihkan'))->assertOk()->assertSee('Pulihkan');
    }

    public function test_pulihkan_ditolak_setelah_ada_akun(): void
    {
        $this->seed(DataUjiSeeder::class);

        $this->get(route('pulihkan'))->assertRedirect(route('login'));
        $this->post(route('pulihkan.proses'), [])->assertRedirect(route('login'));
    }

    public function test_pulihkan_menolak_zip_tanpa_database_sqlite(): void
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $zipPath = $dir.'/rkas-pulihkan-kosong.zip';

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('catatan.txt', 'bukan backup');
        $zip->close();

        $resp = $this->post(route('pulihkan.proses'), [
            'file' => new UploadedFile($zipPath, 'rkas-pulihkan-kosong.zip', 'application/zip', null, true),
        ]);

        $resp->assertSessionHasErrors('file');
        $pesan = session('errors')->first('file');
        $this->assertStringContainsString('Gagal memulihkan', $pesan);

        @unlink($zipPath);
    }

    public function test_login_tidak_lagi_menampilkan_akun_default(): void
    {
        $this->seed(DataUjiSeeder::class);

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('admin@sekolah.id', $html);
        $this->assertStringNotContainsString('value="password"', $html);
        $this->assertSame(0, AuditLog::where('action', 'login')->count());
    }
}
