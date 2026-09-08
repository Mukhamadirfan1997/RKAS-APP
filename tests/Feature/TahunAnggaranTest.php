<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TahunAnggaranTest extends TestCase
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

    public function test_activate_tahun_transaksi_tepat_satu_aktif(): void
    {
        // Buat TA 2027
        $ta2027 = TahunAnggaran::create([
            'tahun' => 2027,
            'sumber_dana' => 'BOSP REGULER',
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'is_active' => false,
            'status_pengesahan' => 'Draft',
        ]);
        $ta2026 = TahunAnggaran::where('tahun', 2026)->first();
        $this->assertTrue((bool) $ta2026->is_active);
        $this->assertFalse((bool) $ta2027->is_active);

        // Aktifkan 2027
        $this->post(route('pengaturan.tahun.aktifkan', $ta2027->id))->assertRedirect();
        $this->assertEquals(1, TahunAnggaran::where('is_active', true)->count());
        $this->assertTrue((bool) $ta2027->fresh()->is_active);
        $this->assertFalse((bool) $ta2026->fresh()->is_active);

        // Aktifkan lagi 2026 — tetap tepat 1
        $this->post(route('pengaturan.tahun.aktifkan', $ta2026->id))->assertRedirect();
        $this->assertEquals(1, TahunAnggaran::where('is_active', true)->count());
        $this->assertTrue((bool) $ta2026->fresh()->is_active);
        $this->assertFalse((bool) $ta2027->fresh()->is_active);

        // Audit log tercatat
        $this->assertDatabaseHas('audit_logs', ['action' => 'tahun.aktifkan', 'auditable_id' => $ta2027->id]);
    }

    public function test_resolve_tahun_self_heal_ketika_0_aktif(): void
    {
        // Set semua jadi tidak aktif
        TahunAnggaran::query()->update(['is_active' => false]);
        $this->assertEquals(0, TahunAnggaran::where('is_active', true)->count());
        // Buat TA 2027 biar tahun terbesar yang dipilih adalah 2027
        $ta2027 = TahunAnggaran::create([
            'tahun' => 2027,
            'sumber_dana' => 'BOSP REGULER',
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'is_active' => false,
            'status_pengesahan' => 'Draft',
        ]);
        $ta2026 = TahunAnggaran::where('tahun', 2026)->first();

        // Trigger resolveTahun via Dashboard (auth)
        $this->get(route('dashboard.index'))->assertOk();

        // Harus self-heal: TA terbaru (2027) jadi aktif, tepat 1
        $this->assertEquals(1, TahunAnggaran::where('is_active', true)->count());
        $this->assertTrue((bool) $ta2027->fresh()->is_active);
        $this->assertFalse((bool) $ta2026->fresh()->is_active);

        // Audit log tahun.auto-fix tercatat
        $this->assertDatabaseHas('audit_logs', ['action' => 'tahun.auto-fix']);
        $log = AuditLog::where('action', 'tahun.auto-fix')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($ta2027->id, $log->auditable_id);
    }

    public function test_destroy_ditolak_kalau_is_active(): void
    {
        $ta = TahunAnggaran::where('tahun', 2026)->first();
        $this->assertTrue((bool) $ta->is_active);
        $resp = $this->delete(route('pengaturan.tahun.destroy', $ta->id));
        $resp->assertRedirect();
        $resp->assertSessionHasErrors(['error']);
        $this->assertStringContainsString('sedang aktif', strtolower(collect($resp->getSession()->get('errors')->get('error'))->first() ?? ''));
        $this->assertDatabaseHas('tahun_anggaran', ['tahun' => 2026]);
    }

    public function test_destroy_ditolak_kalau_punya_item_rkas(): void
    {
        // Buat TA 2027 tidak aktif tapi punya item
        $ta2027 = TahunAnggaran::create([
            'tahun' => 2027,
            'sumber_dana' => 'BOSP REGULER',
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'is_active' => false,
            'status_pengesahan' => 'Draft',
        ]);
        // Buat 1 item untuk 2027
        $prog = MasterProgram::first();
        $rek = MasterKodeRekening::first();
        RkasItem::create([
            'tahun_anggaran_id' => $ta2027->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'uraian' => 'Item TA 2027',
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => 1000,
            'jumlah' => 1000,
            'no_urut' => 1,
        ]);

        $resp = $this->delete(route('pengaturan.tahun.destroy', $ta2027->id));
        $resp->assertRedirect();
        $resp->assertSessionHasErrors(['error']);
        $this->assertStringContainsString('item rkas', strtolower(collect($resp->getSession()->get('errors')->get('error'))->first() ?? ''));
        $this->assertDatabaseHas('tahun_anggaran', ['tahun' => 2027]);
    }

    public function test_destroy_berhasil_kalau_aman(): void
    {
        $ta2027 = TahunAnggaran::create([
            'tahun' => 2027,
            'sumber_dana' => 'BOSP REGULER',
            'pagu_total' => 10000000,
            'pagu_tahap1' => 5000000,
            'pagu_tahap2' => 5000000,
            'is_active' => false,
            'status_pengesahan' => 'Draft',
        ]);
        $this->assertEquals(0, RkasItem::where('tahun_anggaran_id', $ta2027->id)->count());
        $resp = $this->delete(route('pengaturan.tahun.destroy', $ta2027->id));
        $resp->assertRedirect(route('pengaturan.tahun.index'));
        $resp->assertSessionHas('success');
        $this->assertDatabaseMissing('tahun_anggaran', ['tahun' => 2027]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tahun.hapus', 'auditable_id' => $ta2027->id]);
    }

    public function test_hint_onboarding_tampil(): void
    {
        $this->get(route('pengaturan.tahun.index'))->assertOk()
            ->assertSee('Buat/aktifkan tahun anggaran di sini dulu', false)
            ->assertSee('baru isi nominal pagu di tab', false);
        $this->get(route('pengaturan.pagu'))->assertOk()
            ->assertSee('Mengatur pagu untuk TA', false)
            ->assertSee('TA lain? Ganti dulu di tab', false);
        // Tombol Hapus harus ada
        $this->get(route('pengaturan.tahun.index'))->assertSee('Hapus', false);
    }
}
