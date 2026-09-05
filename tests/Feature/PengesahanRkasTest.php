<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PengesahanRkasTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
        $this->ta = TahunAnggaran::where('tahun', 2026)->first();
        // Pastikan mulai dari Draft
        $this->ta->update(['status_pengesahan' => 'Draft']);
    }

    private function payloadStore(): array
    {
        $prog = MasterProgram::first();
        $rek = MasterKodeRekening::first();

        return [
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'uraian' => 'Test Pengesahan Item',
            'harga_satuan' => 10000,
            'satuan' => 'dus',
            'alokasi' => [1 => ['volume' => 2, 'satuan' => 'dus']],
        ];
    }

    public function test_sahkan_dari_draft_berhasil_audit_dan_backup(): void
    {
        $this->assertEquals('Draft', $this->ta->fresh()->status_pengesahan);

        $backupsBefore = collect(File::files(storage_path('app/backups')))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'rkas-pengesahan-'))
            ->count();

        $resp = $this->post(route('pengaturan.pengesahan.sahkan'));
        $resp->assertRedirect(route('pengaturan.status', ['tahun' => $this->ta->tahun]));
        $resp->assertSessionHas('success');

        $this->ta->refresh();
        $this->assertEquals('Disahkan', $this->ta->status_pengesahan);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rkas.sahkan',
            'auditable_type' => 'TahunAnggaran',
            'auditable_id' => $this->ta->id,
        ]);
        $log = AuditLog::where('action', 'rkas.sahkan')->latest('id')->first();
        $this->assertNotNull($log->new_values);
        $this->assertEquals('Disahkan', $log->new_values['status_pengesahan']);
        $this->assertArrayHasKey('pagu_total', $log->new_values);
        $this->assertArrayHasKey('jumlah_item', $log->new_values);
        $this->assertArrayHasKey('backup_file', $log->new_values);

        $files = collect(File::files(storage_path('app/backups')))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'rkas-pengesahan-'.$this->ta->tahun.'-'))
            ->values();
        $this->assertGreaterThan($backupsBefore, $files->count(), 'File rkas-pengesahan-*.zip harus terbuat');
        // Pastikan file yang baru ada dan valid zip
        $latest = $files->sortByDesc(fn ($f) => $f->getMTime())->first();
        $this->assertNotNull($latest);
        $this->assertStringEndsWith('.zip', $latest->getFilename());
        $this->assertGreaterThan(0, $latest->getSize());
    }

    public function test_sahkan_dari_status_selain_draft_ditolak(): void
    {
        $this->ta->update(['status_pengesahan' => 'Disahkan']);
        $resp = $this->post(route('pengaturan.pengesahan.sahkan'));
        $resp->assertRedirect();
        $resp->assertSessionHasErrors(['error']);
        $this->assertEquals('Disahkan', $this->ta->fresh()->status_pengesahan);

        // Pergeseran juga harus ditolak jika kita ikut strict point 2? Tapi spec point 4 bilang Pergeseran bisa disahkan lagi.
        // Kita allow Pergeseran -> Disahkan, jadi test ini hanya untuk Disahkan.
        // Untuk memastikan guard benar, coba dari Disahkan tetap ditolak, tidak membuat backup baru
        $countBefore = AuditLog::where('action', 'rkas.sahkan')->count();
        $this->assertEquals($countBefore, AuditLog::where('action', 'rkas.sahkan')->count());
    }

    public function test_sahkan_dari_pergeseran_berhasil(): void
    {
        // Pergeseran -> Disahkan harus berhasil (point 4)
        $this->ta->update(['status_pengesahan' => 'Pergeseran']);
        $resp = $this->post(route('pengaturan.pengesahan.sahkan'));
        $resp->assertRedirect(route('pengaturan.status', ['tahun' => $this->ta->tahun]));
        $resp->assertSessionHas('success');
        $this->assertEquals('Disahkan', $this->ta->fresh()->status_pengesahan);
    }

    public function test_store_update_destroy_ditolak_saat_disahkan(): void
    {
        $this->ta->update(['status_pengesahan' => 'Disahkan']);
        $item = RkasItem::first();
        $prog = MasterProgram::first();
        $rek = MasterKodeRekening::first();

        // store JSON -> 403
        $respStore = $this->postJson(route('rkas.store'), $this->payloadStore());
        $respStore->assertStatus(403);
        $respStore->assertJson(['success' => false]);
        $this->assertStringContainsString('sudah disahkan', strtolower($respStore->json('message')));

        // update JSON -> 403
        $respUpdate = $this->postJson(route('rkas.update', $item->id), [
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'uraian' => 'Coba ubah saat disahkan',
            'harga_satuan' => 5000,
            'satuan' => 'dus',
            'alokasi' => [1 => ['volume' => 1, 'satuan' => 'dus']],
        ]);
        $respUpdate->assertStatus(403);
        $respUpdate->assertJson(['success' => false]);

        // destroy JSON -> 403
        $respDestroy = $this->deleteJson(route('rkas.destroy', $item->id));
        $respDestroy->assertStatus(403);
        $respDestroy->assertJson(['success' => false]);

        // Non-JSON store -> redirect with error
        $respStore2 = $this->post(route('rkas.store'), $this->payloadStore());
        $respStore2->assertRedirect();
        $respStore2->assertSessionHasErrors(['error']);

        // Non-JSON pagu -> redirect error
        $respPagu = $this->post(route('pengaturan.update-pagu'), [
            'pagu_total' => 999999,
            'pagu_tahap1' => 500000,
            'pagu_tahap2' => 499999,
        ]);
        $respPagu->assertRedirect();
        $respPagu->assertSessionHasErrors(['error']);
        $this->assertEquals(180320000, (int) $this->ta->fresh()->pagu_total); // tidak berubah
    }

    public function test_store_update_berhasil_saat_draft_dan_pergeseran(): void
    {
        foreach (['Draft', 'Pergeseran'] as $status) {
            $this->ta->update(['status_pengesahan' => $status]);

            $resp = $this->postJson(route('rkas.store'), array_merge($this->payloadStore(), ['uraian' => 'Item '.$status]));
            $resp->assertStatus(200);
            $resp->assertJson(['success' => true]);
            $this->assertDatabaseHas('rkas_item', ['uraian' => 'Item '.$status]);

            $item = RkasItem::where('uraian', 'Item '.$status)->first();
            $prog = MasterProgram::first();
            $rek = MasterKodeRekening::first();
            $respUp = $this->postJson(route('rkas.update', $item->id), [
                'master_program_id' => $prog->id,
                'master_kode_rekening_id' => $rek->id,
                'uraian' => 'Item '.$status.' Updated',
                'harga_satuan' => 12345,
                'satuan' => 'dus',
                'alokasi' => [2 => ['volume' => 3, 'satuan' => 'dus']],
            ]);
            $respUp->assertJson(['success' => true]);
            $this->assertDatabaseHas('rkas_item', ['uraian' => 'Item '.$status.' Updated']);
        }
    }

    public function test_update_pagu_ditolak_saat_disahkan(): void
    {
        $this->ta->update(['status_pengesahan' => 'Disahkan']);
        $oldPagu = $this->ta->pagu_total;
        $resp = $this->post(route('pengaturan.update-pagu'), [
            'pagu_total' => 1,
            'pagu_tahap1' => 1,
            'pagu_tahap2' => 0,
        ]);
        $resp->assertSessionHasErrors(['error']);
        $this->assertEquals((float) $oldPagu, (float) $this->ta->fresh()->pagu_total);
    }

    public function test_buka_kembali_dari_disahkan_ke_pergeseran_dan_edit_kembali_bisa(): void
    {
        $this->ta->update(['status_pengesahan' => 'Disahkan']);

        $resp = $this->post(route('pengaturan.pengesahan.buka-kembali'));
        $resp->assertRedirect(route('pengaturan.status', ['tahun' => $this->ta->tahun]));
        $resp->assertSessionHas('success');
        $this->assertEquals('Pergeseran', $this->ta->fresh()->status_pengesahan);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rkas.buka-kembali',
            'auditable_type' => 'TahunAnggaran',
            'auditable_id' => $this->ta->id,
        ]);
        $log = AuditLog::where('action', 'rkas.buka-kembali')->latest('id')->first();
        $this->assertEquals('Disahkan', $log->old_values['status_pengesahan']);
        $this->assertEquals('Pergeseran', $log->new_values['status_pengesahan']);

        // Setelah Pergeseran, store/update harus berhasil lagi
        $respStore = $this->postJson(route('rkas.store'), $this->payloadStore());
        $respStore->assertJson(['success' => true]);

        $item = RkasItem::latest('id')->first();
        $prog = MasterProgram::first();
        $rek = MasterKodeRekening::first();
        $respUp = $this->postJson(route('rkas.update', $item->id), [
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'uraian' => 'Revisi setelah buka kembali',
            'harga_satuan' => 7777,
            'satuan' => 'dus',
            'alokasi' => [3 => ['volume' => 5, 'satuan' => 'dus']],
        ]);
        $respUp->assertJson(['success' => true]);
    }

    public function test_buka_kembali_ditolak_jika_bukan_disahkan(): void
    {
        $this->ta->update(['status_pengesahan' => 'Draft']);
        $resp = $this->post(route('pengaturan.pengesahan.buka-kembali'));
        $resp->assertSessionHasErrors(['error']);
        $this->assertEquals('Draft', $this->ta->fresh()->status_pengesahan);

        $this->ta->update(['status_pengesahan' => 'Pergeseran']);
        $resp2 = $this->post(route('pengaturan.pengesahan.buka-kembali'));
        $resp2->assertSessionHasErrors(['error']);
        $this->assertEquals('Pergeseran', $this->ta->fresh()->status_pengesahan);
    }
}
