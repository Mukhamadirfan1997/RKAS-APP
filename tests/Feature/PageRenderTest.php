<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_page_shows()
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_pages_render_ok()
    {
        $user = User::first();
        $this->actingAs($user);

        // /pengaturan lama redirect ke /pengaturan/profil (302)
        $this->get('/pengaturan')->assertRedirect('/pengaturan/profil');

        $pages = ['/', '/rkas', '/dashboard', '/pengaturan/profil', '/pengaturan/akun', '/pengaturan/pagu', '/pengaturan/status', '/pengaturan/tahun', '/monitoring/juknis', '/master/program', '/master/rekening', '/master/barang', '/audit-log', '/backup', '/api/search/kegiatan?q=03', '/api/search/rekening?q=5.1', '/api/search/barang?q=Nasi'];

        foreach ($pages as $page) {
            $response = $this->get($page);
            $response->assertStatus(200);
        }

        $this->assertTrue(true);
    }

    public function test_login_flow()
    {
        $response = $this->post('/login', [
            'email' => 'admin@sekolah.id',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_rkas_worksheet_grouped_by_kegiatan()
    {
        $user = User::first();
        $this->actingAs($user);

        $response = $this->get('/rkas');
        $response->assertStatus(200);
        $response->assertViewHas('kegiatanGroups');

        $groups = $response->viewData('kegiatanGroups');
        $this->assertTrue($groups->isNotEmpty(), 'Kertas kerja harus punya minimal 1 kelompok kegiatan.');

        foreach ($groups as $g) {
            $this->assertArrayHasKey('kode', $g);
            $this->assertArrayHasKey('nama', $g);
            $this->assertArrayHasKey('items', $g);
            $this->assertArrayHasKey('total_sudah', $g);
            $this->assertIsNumeric($g['total_sudah']);
        }
    }
}
