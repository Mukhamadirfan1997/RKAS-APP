<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UpdateService;
use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Progres unduhan installer harus terlihat (NDJSON), bukan diam lalu file
 * tiba-tiba ada. Lihat UpdateController::streamUnduh().
 */
class UpdateUnduhProgressTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $namaFile = 'KARSA_9.9.9_x64-setup.exe';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DataUjiSeeder::class);
        $this->user = User::first();
        $this->actingAs($this->user);
        config(['karsa.version' => '1.0.0']);
        File::ensureDirectoryExists(UpdateService::updatesDir());
    }

    protected function tearDown(): void
    {
        File::delete(UpdateService::updatesDir().'/'.$this->namaFile);
        parent::tearDown();
    }

    private function fakeGithub(int $size = 2048): void
    {
        Http::fake([
            UpdateService::GITHUB_API => Http::response([
                'tag_name' => 'v9.9.9',
                'assets' => [[
                    'name' => $this->namaFile,
                    'browser_download_url' => 'https://github.com/test/download/'.$this->namaFile,
                    'size' => $size,
                ]],
            ], 200),
            'https://github.com/test/download/*' => Http::response(str_repeat('A', $size), 200),
        ]);
    }

    public function test_stream_unduh_mengirim_fase_start_dan_done(): void
    {
        $this->fakeGithub();

        $response = $this->post('/update/unduh', [
            'url_unduh' => 'https://github.com/test/download/'.$this->namaFile,
        ], [
            'X-Progress-Stream' => '1',
            'Accept' => 'application/x-ndjson',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/x-ndjson', (string) $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('"phase":"start"', $body);
        $this->assertStringContainsString('"phase":"done"', $body);
        $this->assertStringContainsString('"success":true', $body);
        $this->assertFileExists(UpdateService::updatesDir().'/'.$this->namaFile);
    }

    public function test_stream_memberi_tahu_file_sudah_ada_tanpa_mengunduh_ulang(): void
    {
        $dest = UpdateService::updatesDir().'/'.$this->namaFile;
        File::put($dest, str_repeat('A', 2048));
        $this->fakeGithub(2048);

        $response = $this->post('/update/unduh', [
            'url_unduh' => 'https://github.com/test/download/'.$this->namaFile,
        ], [
            'X-Progress-Stream' => '1',
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('"already":true', $body);
        $this->assertStringContainsString('"phase":"done"', $body);
    }

    public function test_tanpa_header_stream_tetap_json_biasa(): void
    {
        $this->fakeGithub();

        $response = $this->postJson('/update/unduh', [
            'url_unduh' => 'https://github.com/test/download/'.$this->namaFile,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }
}
