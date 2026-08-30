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

        $pages = ['/', '/rkas', '/dashboard', '/pengaturan', '/monitoring/juknis', '/master/program', '/master/rekening', '/master/barang', '/audit-log', '/api/search/kegiatan?q=03', '/api/search/rekening?q=5.1', '/api/search/barang?q=Nasi'];

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
}
