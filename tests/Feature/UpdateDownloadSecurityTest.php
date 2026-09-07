<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpdateDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
        File::ensureDirectoryExists(storage_path('app/updates'));
        // buat file exe dummy untuk positive case
        File::put(storage_path('app/updates/KARSA-Test-1.0.0.exe'), str_repeat('x', 2048));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/updates/KARSA-Test-1.0.0.exe'));
        parent::tearDown();
    }

    public function test_download_file_valid_exe_succeeds(): void
    {
        $this->get('/update/download-file/KARSA-Test-1.0.0.exe')->assertStatus(200);
    }

    public function test_path_traversal_dot_dot_slash_is_rejected(): void
    {
        // coba akses .env via traversal — harus 404, bukan 200
        $this->get('/update/download-file/..%2F..%2F..%2F.env')->assertStatus(404);
        $this->get('/update/download-file/..%2F.env')->assertStatus(404);
        // encoded slash
        $this->get('/update/download-file/%2e%2e%2f.env')->assertStatus(404);
    }

    public function test_path_traversal_with_slash_is_rejected(): void
    {
        // Laravel akan decode %2F jadi /, basename check harus tolak
        $this->get('/update/download-file/subdir%2Ffile.exe')->assertStatus(404);
    }

    public function test_non_exe_msi_extension_is_rejected(): void
    {
        File::put(storage_path('app/updates/evil.txt'), 'evil');
        $this->get('/update/download-file/evil.txt')->assertStatus(404);
        File::delete(storage_path('app/updates/evil.txt'));

        File::put(storage_path('app/updates/evil.zip'), 'evil');
        $this->get('/update/download-file/evil.zip')->assertStatus(404);
        File::delete(storage_path('app/updates/evil.zip'));
    }

    public function test_dot_env_without_extension_is_rejected(): void
    {
        $this->get('/update/download-file/.env')->assertStatus(404);
    }

    public function test_status_endpoint_rejects_traversal(): void
    {
        $this->get('/update/status-unduhan?file=..%2F..%2F.env')->assertJson(['exists' => false]);
        $this->get('/update/status-unduhan?file=evil.txt')->assertJson(['exists' => false]);
    }
}
