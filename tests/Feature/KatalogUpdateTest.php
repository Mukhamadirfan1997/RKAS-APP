<?php

namespace Tests\Feature;

use App\Models\KatalogMeta;
use App\Models\KodeBarang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class KatalogUpdateTest extends TestCase
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

    private function buildZip(array $rows, string $versi = '2026.99', ?string $forceChecksum = null, ?int $forceJumlah = null): string
    {
        $tmpDir = sys_get_temp_dir().'/karsa-katalog-test-'.uniqid();
        mkdir($tmpDir, 0777, true);
        $csvPath = $tmpDir.'/katalog.csv';
        $fh = fopen($csvPath, 'w');
        $header = ['kode','id_barang_arkas','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori'];
        fputcsv($fh, $header);
        foreach ($rows as $r) {
            // Ensure 10 cols
            $r = array_pad($r, 10, '');
            fputcsv($fh, $r);
        }
        fclose($fh);

        $checksum = $forceChecksum ?? hash_file('sha256', $csvPath);
        $jumlah = $forceJumlah ?? count($rows);

        $manifest = [
            'versi' => $versi,
            'tanggal_generate' => now()->toIso8601String(),
            'jumlah_baris' => $jumlah,
            'checksum' => $checksum,
            'checksum_algo' => 'sha256',
            'source_file' => 'test.csv',
            'format' => 'csv',
        ];
        $manifestPath = $tmpDir.'/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        $zipPath = $tmpDir.'/katalog.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($csvPath, 'katalog.csv');
        $zip->addFile($manifestPath, 'manifest.json');
        $zip->close();

        return $zipPath;
    }

    private function seedRowsForSuccess(): array
    {
        // Ambil 1 barang existing untuk di-update, plus 1 barang baru
        // Seed awal (KB-001..009) punya id_barang_arkas null → set dulu agar upsert match via unique id_barang_arkas
        $existing = KodeBarang::first();
        if (empty($existing->id_barang_arkas)) {
            $existing->update(['id_barang_arkas' => $existing->kode]);
            $existing->refresh();
        }
        return [
            // update existing: same id_barang_arkas/kode, ubah harga
            [$existing->kode, $existing->id_barang_arkas, $existing->nama.' UPDATED', $existing->kode_rekening ?? '5.1.02.01.01.0001', $existing->satuan_default ?? 'unit', '99999', '90000', '110000', 'TEST', 'TestKat'],
            // barang baru
            ['KB-NEW-TEST-001', 'KB-NEW-TEST-001', 'Barang Baru Test Katalog', '5.1.02.01.01.0001', 'buah', '12345', '10000', '15000', 'TEST', 'TestKat'],
        ];
    }

    public function test_custom_barang_tidak_terhapus_setelah_update(): void
    {
        // Custom barang sekolah (id_barang_arkas null)
        $custom = KodeBarang::create([
            'kode' => 'KB-CUSTOM-001',
            'id_barang_arkas' => null,
            'nama' => 'Barang Custom Sekolah — Jangan Hapus',
            'satuan_default' => 'unit',
            'harga_acuan' => 77777,
            'kode_rekening' => '5.1.02.01.01.0001',
        ]);
        $countBefore = DB::table('kode_barang')->count();
        $metaCountBefore = KatalogMeta::count();

        $rows = $this->seedRowsForSuccess();
        $zipPath = $this->buildZip($rows, '2026.99');

        $file = new UploadedFile($zipPath, 'katalog-update-2026.99.zip', 'application/zip', null, true);
        $response = $this->post(route('pengaturan.katalog.update'), ['file' => $file]);

        $response->assertRedirect(route('pengaturan.katalog'));
        $response->assertSessionHas('success');

        // Custom tetap ada
        $this->assertDatabaseHas('kode_barang', ['kode' => 'KB-CUSTOM-001', 'nama' => 'Barang Custom Sekolah — Jangan Hapus']);
        $freshCustom = KodeBarang::where('kode', 'KB-CUSTOM-001')->first();
        $this->assertNotNull($freshCustom);
        $this->assertEquals(77777, (float) $freshCustom->harga_acuan);

        // Barang baru ter-insert, existing ter-update
        $this->assertDatabaseHas('kode_barang', ['kode' => 'KB-NEW-TEST-001']);
        $this->assertEquals($countBefore + 1, DB::table('kode_barang')->count());

        // katalog_meta bertambah 1 baris baru (versi baru)
        $this->assertEquals($metaCountBefore + 1, KatalogMeta::count());
    }

    public function test_katalog_meta_terupdate_setelah_upload_berhasil(): void
    {
        $countBefore = DB::table('kode_barang')->count();
        $rows = $this->seedRowsForSuccess();
        $zipPath = $this->buildZip($rows, '2026.88');

        $file = new UploadedFile($zipPath, 'katalog-update-2026.88.zip', 'application/zip', null, true);
        $this->post(route('pengaturan.katalog.update'), ['file' => $file])
            ->assertRedirect(route('pengaturan.katalog'))
            ->assertSessionHas('success');

        $meta = KatalogMeta::latest('id')->first();
        $this->assertNotNull($meta);
        $this->assertEquals('2026.88', $meta->versi);
        $this->assertEquals(DB::table('kode_barang')->count(), (int) $meta->jumlah_barang);
        $this->assertNotNull($meta->tanggal_update);
        // jumlah_barang harus = countBefore + 1 (satu barang baru)
        $this->assertEquals($countBefore + 1, (int) $meta->jumlah_barang);
        $this->assertNotNull($meta->checksum);
        $this->assertEquals(64, strlen($meta->checksum)); // sha256 hex
        $this->assertEquals('katalog-update-2026.88.zip', $meta->source_file);
    }

    public function test_checksum_tidak_cocok_ditolak_tanpa_perubahan_data(): void
    {
        $custom = KodeBarang::create([
            'kode' => 'KB-CUSTOM-CHKSUM',
            'id_barang_arkas' => null,
            'nama' => 'Custom Checksum Guard',
            'satuan_default' => 'unit',
            'harga_acuan' => 11111,
        ]);
        $countBefore = DB::table('kode_barang')->count();
        $metaCountBefore = KatalogMeta::count();
        $metaLatestBefore = KatalogMeta::latest('id')->first();

        $rows = $this->seedRowsForSuccess();
        // paksa checksum salah
        $zipPath = $this->buildZip($rows, '2026.77', forceChecksum: str_repeat('0', 64));

        $file = new UploadedFile($zipPath, 'katalog-update-2026.77.zip', 'application/zip', null, true);
        $response = $this->post(route('pengaturan.katalog.update'), ['file' => $file]);

        $response->assertRedirect(route('pengaturan.katalog'));
        $response->assertSessionHasErrors(['error']);
        $errors = session('errors');
        $msg = $errors->get('error')[0] ?? '';
        // Pesan ramah non-teknis, mengandung kata kunci, bukan stack trace
        $this->assertStringContainsString('rusak', strtolower($msg));
        $this->assertStringContainsString('checksum', strtolower($msg));
        $this->assertStringNotContainsString('Stack trace', $msg);
        $this->assertStringNotContainsString('Exception', $msg);

        // Tidak ada perubahan data
        $this->assertEquals($countBefore, DB::table('kode_barang')->count());
        $this->assertDatabaseHas('kode_barang', ['kode' => 'KB-CUSTOM-CHKSUM']);
        $this->assertEquals($metaCountBefore, KatalogMeta::count());
        // Meta terbaru tetap yang lama, tidak ada versi 2026.77
        $this->assertNull(KatalogMeta::where('versi', '2026.77')->first());
        if ($metaLatestBefore) {
            $this->assertEquals($metaLatestBefore->id, KatalogMeta::latest('id')->first()->id);
        }
        $this->assertDatabaseMissing('kode_barang', ['kode' => 'KB-NEW-TEST-001']);
    }

    public function test_rollback_total_jika_error_di_tengah_upsert(): void
    {
        $custom = KodeBarang::create([
            'kode' => 'KB-CUSTOM-ROLLBACK',
            'id_barang_arkas' => null,
            'nama' => 'Custom Rollback Guard',
            'satuan_default' => 'unit',
            'harga_acuan' => 22222,
        ]);
        $countBefore = DB::table('kode_barang')->count();
        $metaCountBefore = KatalogMeta::count();

        // Baris yang jika sukses akan menambah KB-NEW-ROLLBACK, tapi kita trigger mid-error
        $existing = KodeBarang::first();
        if (empty($existing->id_barang_arkas)) {
            $existing->update(['id_barang_arkas' => $existing->kode]);
            $existing->refresh();
        }
        $rows = [
            [$existing->kode, $existing->id_barang_arkas, $existing->nama.' ROLLBACK', $existing->kode_rekening ?? '5.1.02.01.01.0001', $existing->satuan_default ?? 'unit', '88888', '80000', '99999', 'TEST', 'TestKat'],
            ['KB-NEW-ROLLBACK', 'KB-NEW-ROLLBACK', 'Barang Baru Rollback Test', '5.1.02.01.01.0001', 'buah', '54321', '50000', '60000', 'TEST', 'TestKat'],
            ['KB-NEW-ROLLBACK-2', 'KB-NEW-ROLLBACK-2', 'Barang Baru Rollback 2', '5.1.02.01.01.0001', 'buah', '54321', '50000', '60000', 'TEST', 'TestKat'],
        ];
        $zipPath = $this->buildZip($rows, '2026.66');

        $file = new UploadedFile($zipPath, 'katalog-update-2026.66.zip', 'application/zip', null, true);

        // Trigger hook mid-error via header
        $response = $this->withHeaders(['X-Trigger-Mid-Error' => '1'])
            ->post(route('pengaturan.katalog.update'), ['file' => $file]);

        $response->assertRedirect(route('pengaturan.katalog'));
        $response->assertSessionHasErrors(['error']);
        $msg = session('errors')->get('error')[0] ?? '';
        $this->assertStringContainsString('Simulasi error di tengah', $msg);
        $this->assertStringNotContainsString('Stack trace', $msg);

        // Rollback total: jumlah baris tetap, barang baru tidak ada, existing tidak ter-update
        $this->assertEquals($countBefore, DB::table('kode_barang')->count());
        $this->assertDatabaseMissing('kode_barang', ['kode' => 'KB-NEW-ROLLBACK']);
        $this->assertDatabaseMissing('kode_barang', ['kode' => 'KB-NEW-ROLLBACK-2']);
        $this->assertDatabaseHas('kode_barang', ['kode' => 'KB-CUSTOM-ROLLBACK']);
        // harga existing tidak berubah menjadi 88888
        $freshExisting = KodeBarang::where('kode', $existing->kode)->first();
        $this->assertNotEquals(88888, (float) $freshExisting->harga_acuan);

        // katalog_meta tidak bertambah
        $this->assertEquals($metaCountBefore, KatalogMeta::count());
        $this->assertNull(KatalogMeta::where('versi', '2026.66')->first());
    }
}
