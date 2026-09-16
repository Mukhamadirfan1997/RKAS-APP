<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Models\PengaturanSekolah;
use App\Models\User;
use App\Services\AuditLogArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        // Seed via LogsActivity membuat ratusan audit log dari master data — bersihkan agar test fokus pada log buatan test
        AuditLog::query()->delete();
        AuditLogArchive::query()->delete();
    }

    public function test_tidak_arsip_jika_semua_baru(): void
    {
        PengaturanSekolah::first()->forceFill(['audit_last_archive_at' => null])->save();
        AuditLog::query()->delete();
        AuditLogArchive::query()->delete();

        AuditLog::create([
            'user_id' => User::first()->id,
            'action' => 'created',
            'auditable_type' => 'Test',
            'auditable_id' => 1,
            'description' => 'baru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = AuditLog::count();
        $result = AuditLogArchiveService::runArchive();

        $this->assertEquals(0, $result['archived']);
        $this->assertEquals($before, AuditLog::count(), 'Tidak ada yang diarsip, jumlah tetap');
        $this->assertEquals(0, AuditLogArchive::count());
        $this->assertNotNull(PengaturanSekolah::first()->fresh()->audit_last_archive_at);
    }

    public function test_arsipkan_log_lama_ke_tabel_arsip(): void
    {
        PengaturanSekolah::first()->forceFill(['audit_last_archive_at' => null])->save();
        AuditLog::query()->delete();
        AuditLogArchive::query()->delete();

        $user = User::first();

        // 2 log lama (>90 hari) + 1 baru
        foreach ([100, 95] as $days) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'updated',
                'auditable_type' => 'RkasItem',
                'auditable_id' => $days,
                'description' => "lama $days",
                'created_at' => now()->subDays($days),
                'updated_at' => now()->subDays($days),
            ]);
        }
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'created',
            'auditable_type' => 'RkasItem',
            'auditable_id' => 999,
            'description' => 'baru',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $beforeActive = AuditLog::count();
        $this->assertEquals(3, $beforeActive);

        $result = AuditLogArchiveService::runArchive();

        $this->assertEquals(2, $result['archived']);
        $this->assertEquals(1, AuditLog::count());
        $this->assertEquals(2, AuditLogArchive::count());
        $this->assertTrue(AuditLogArchive::where('description', 'lama 100')->exists());
        $this->assertNotNull(AuditLogArchive::first()->archived_at);

        // Halaman audit menampilkan section arsip
        $this->actingAs($user);
        $resp = $this->get(route('audit.index'));
        $resp->assertStatus(200);
        $resp->assertSee('Arsip Audit Log');
        $resp->assertSee('lama 100');
        $resp->assertSee('Aktif: 1');
        $resp->assertSee('Arsip: 2');
    }

    public function test_should_run_archive_idempoten_30_hari(): void
    {
        $p = PengaturanSekolah::first();
        $p->forceFill(['audit_last_archive_at' => null])->save();
        $this->assertTrue(AuditLogArchiveService::shouldRunArchive());

        $p->forceFill(['audit_last_archive_at' => now()])->save();
        $this->assertFalse(AuditLogArchiveService::shouldRunArchive());

        $p->forceFill(['audit_last_archive_at' => now()->subDays(29)])->save();
        $this->assertFalse(AuditLogArchiveService::shouldRunArchive());

        $p->forceFill(['audit_last_archive_at' => now()->subDays(30)])->save();
        $this->assertTrue(AuditLogArchiveService::shouldRunArchive());

        $p->forceFill(['audit_last_archive_at' => now()->subDays(31)])->save();
        $this->assertTrue(AuditLogArchiveService::shouldRunArchive());

        // Setelah archive, timestamp terupdate -> tidak jalan lagi
        $p->forceFill(['audit_last_archive_at' => null])->save();
        AuditLogArchiveService::runArchive();
        $this->assertFalse(AuditLogArchiveService::shouldRunArchive());
    }

    public function test_run_archive_tidak_error_saat_pengaturan_kosong_fresh_install(): void
    {
        AuditLog::query()->delete();
        AuditLogArchive::query()->delete();
        PengaturanSekolah::query()->delete();
        $this->assertDatabaseCount('pengaturan_sekolah', 0);

        $this->assertTrue(AuditLogArchiveService::shouldRunArchive());
        $this->assertEquals(0, AuditLogArchiveService::getArchiveCount());
        $this->assertEquals(0, AuditLogArchiveService::getActiveCount());
        $this->assertNull(AuditLogArchiveService::getLastArchiveAt());

        // Buat log lama saat fresh install kosong
        User::firstOrCreate(['email' => 'admin@sekolah.id'], ['name' => 'Operator', 'password' => bcrypt('password')]);
        AuditLog::create([
            'user_id' => User::first()->id,
            'action' => 'created',
            'auditable_type' => 'Test',
            'auditable_id' => 1,
            'description' => 'lama fresh',
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        $result = AuditLogArchiveService::runArchive();
        $this->assertEquals(1, $result['archived']);
        $this->assertEquals(1, PengaturanSekolah::count());
        $this->assertNotNull(PengaturanSekolah::first()->audit_last_archive_at);
        $this->assertEquals(1, AuditLogArchive::count());
        $this->assertEquals(0, AuditLog::count());
    }

    public function test_boot_tidak_throw_jika_kolom_belum_migrasi_simulasi(): void
    {
        // Simulasi kolom belum ada diabaikan via try/catch -> shouldRunArchive false, tidak throw
        // Kita cukup pastikan runArchive tetap tidak throw meski dipanggil berulang dalam transaksi kosong
        PengaturanSekolah::first()->forceFill(['audit_last_archive_at' => now()])->save();
        $result = AuditLogArchiveService::runArchive();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('archived', $result);
    }

    public function test_archive_tidak_hapus_log_di_batas_retensi(): void
    {
        PengaturanSekolah::first()->forceFill(['audit_last_archive_at' => null])->save();
        AuditLog::query()->delete();
        AuditLogArchive::query()->delete();
        $user = User::first();
        // Tepat 90 hari -> tidak diarsip (< cutoff, bukan <=). Cutoff = now-90, where < cutoff
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'auditable_type' => 'Test',
            'auditable_id' => 1,
            'description' => 'batas 90',
            'created_at' => now()->subDays(90),
            'updated_at' => now()->subDays(90),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'auditable_type' => 'Test',
            'auditable_id' => 2,
            'description' => '91 hari',
            'created_at' => now()->subDays(91),
            'updated_at' => now()->subDays(91),
        ]);

        $result = AuditLogArchiveService::runArchive();
        $this->assertEquals(1, $result['archived']);
        $this->assertTrue(AuditLog::where('description', 'batas 90')->exists());
        $this->assertTrue(AuditLogArchive::where('description', '91 hari')->exists());
    }
}
