<?php

namespace Tests\Feature;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class KoreksiExportBackupTest extends TestCase
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

    private function storeItem(array $overrides = []): RkasItem
    {
        $program = MasterProgram::first();
        $rekening = MasterKodeRekening::first();

        $this->postJson('/rkas/store', array_merge([
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Item Koreksi',
            'harga_satuan' => 10000,
            'satuan' => 'dus',
            'koreksi' => 0,
            'alokasi' => [
                1 => ['volume' => 10, 'satuan' => 'dus'],
                2 => ['volume' => 0, 'satuan' => ''],
            ],
        ], $overrides))->assertJson(['success' => true]);

        return RkasItem::latest('id')->first();
    }

    public function test_store_item_with_koreksi_updates_jumlah_koreksi()
    {
        $item = $this->storeItem(['uraian' => 'Dengan Koreksi', 'koreksi' => 5000]);

        $this->assertEquals(100000, (float) $item->jumlah);
        $this->assertEquals(5000, (float) $item->koreksi);
        $this->assertEquals(105000, (float) $item->jumlah_koreksi);
        $this->assertEquals('OK', $item->kontrol);
    }

    public function test_update_item_with_negative_koreksi()
    {
        $item = $this->storeItem(['uraian' => 'Koreksi Negatif']);
        $program = MasterProgram::first();
        $rekening = MasterKodeRekening::first();

        $this->postJson('/rkas/'.$item->id.'/update', [
            'master_program_id' => $program->id,
            'master_kode_rekening_id' => $rekening->id,
            'uraian' => 'Koreksi Negatif',
            'harga_satuan' => 10000,
            'satuan' => 'dus',
            'koreksi' => -25000,
            'alokasi' => [
                3 => ['volume' => 10, 'satuan' => 'dus'],
            ],
        ])->assertJson(['success' => true]);

        $item->refresh();
        $this->assertEquals(-25000, (float) $item->koreksi);
        $this->assertEquals(75000, (float) $item->jumlah_koreksi);
    }

    public function test_kontrol_badge_selisih_when_harga_berbeda_acuan_arkas()
    {
        $item = $this->storeItem(['uraian' => 'Cek Kontrol']);
        $item->forceFill(['harga_satuan_arkas' => 9000])->save();

        $this->assertEquals(1000, (float) $item->selisih_harga);
        $this->assertEquals('SELISIH', $item->kontrol);

        $item->forceFill(['harga_satuan_arkas' => 10000])->save();
        $this->assertEquals('OK', $item->kontrol);
    }

    public function test_export_excel_downloads()
    {
        $this->storeItem(['uraian' => 'Item Export']);

        $response = $this->get('/rkas/export');
        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type')
        );
    }

    public function test_backup_create_and_download()
    {
        $this->post('/backup/create')->assertRedirect(route('backup.index'));

        $dir = storage_path('app/backups');
        $zips = collect(File::files($dir))->filter(fn ($f) => $f->getExtension() === 'zip');
        $this->assertGreaterThanOrEqual(1, $zips->count());

        $last = $zips->sortByDesc(fn ($f) => $f->getMTime())->first();
        $this->assertNotNull($last);

        // Isi zip harus mengandung database.sqlite yang valid
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($last->getPathname()) === true);
        $zip->close();

        $this->get('/backup/'.$last->getFilename().'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_backup_restore_from_existing_file()
    {
        $this->post('/backup/create');

        $dir = storage_path('app/backups');
        $zips = collect(File::files($dir))->filter(fn ($f) => $f->getExtension() === 'zip');
        $last = $zips->sortByDesc(fn ($f) => $f->getMTime())->first();

        $this->assertNotNull($last);
        $this->post(route('backup.restore-file', $last->getFilename()))
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tahun_anggaran', ['tahun' => 2026]);
    }

    public function test_backup_restore_rejects_non_db_zip()
    {
        // Buat zip tanpa database.sqlite
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $fakeZip = $dir.'/rkas-fake.zip';
        $zip = new ZipArchive;
        $zip->open($fakeZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('berkas.txt', 'bukan database');
        $zip->close();

        $this->post('/backup/restore', ['file' => new UploadedFile(
            $fakeZip, 'rkas-fake.zip', 'application/zip', null, true
        )])->assertSessionHasErrors(['error']);

        File::delete($fakeZip);
    }
}
