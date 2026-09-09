<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_baru_kolom_tour_default_false(): void
    {
        $user = User::first();
        $this->assertFalse((bool) $user->tour_dashboard_seen);
        $this->assertFalse((bool) $user->tour_rkas_seen);
        $this->assertFalse((bool) $user->tour_monitoring_seen);
    }

    public function test_window_karsa_tours_seen_false_saat_render_awal(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $resp = $this->get(route('dashboard.index'));
        $resp->assertOk();
        // window.karsaToursSeen diinject di layout — cek ada script dengan false
        $resp->assertSee('window.karsaToursSeen', false);
        $resp->assertSee('dashboard: false', false);
        $resp->assertSee('rkas: false', false);
        $resp->assertSee('monitoring: false', false);

        $resp2 = $this->get(route('rkas.index'));
        $resp2->assertOk();
        $resp2->assertSee('window.karsaToursSeen', false);
    }

    public function test_tandai_selesai_dashboard_jadi_true(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $this->post(route('tur.tandai', ['nama' => 'dashboard']))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertTrue((bool) $user->tour_dashboard_seen);
        $this->assertFalse((bool) $user->tour_rkas_seen);
        $this->assertFalse((bool) $user->tour_monitoring_seen);

        // Render ulang harus true
        $resp = $this->get(route('dashboard.index'));
        $resp->assertSee('dashboard: true', false);
        $resp->assertSee('rkas: false', false);
    }

    public function test_tandai_selesai_rkas_dan_monitoring(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $this->post(route('tur.tandai', ['nama' => 'rkas']))->assertOk();
        $this->post(route('tur.tandai', ['nama' => 'monitoring']))->assertOk();

        $user->refresh();
        $this->assertTrue((bool) $user->tour_rkas_seen);
        $this->assertTrue((bool) $user->tour_monitoring_seen);
    }

    public function test_tandai_nama_tidak_valid_ditolak(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $this->post(route('tur.tandai', ['nama' => 'invalid']))->assertStatus(422);
        $this->post(route('tur.tandai', ['nama' => 'dashboard2']))->assertStatus(422);
    }

    public function test_reset_semua_mengembalikan_false(): void
    {
        $user = User::first();
        $this->actingAs($user);

        // Tandai semua
        $this->post(route('tur.tandai', ['nama' => 'dashboard']))->assertOk();
        $this->post(route('tur.tandai', ['nama' => 'rkas']))->assertOk();
        $this->post(route('tur.tandai', ['nama' => 'monitoring']))->assertOk();

        $user->refresh();
        $this->assertTrue((bool) $user->tour_dashboard_seen);

        // Reset
        $this->post(route('tur.reset'))->assertRedirect(route('dashboard.index'));
        // Atau JSON
        $this->postJson(route('tur.reset'))->assertOk()->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertFalse((bool) $user->tour_dashboard_seen);
        $this->assertFalse((bool) $user->tour_rkas_seen);
        $this->assertFalse((bool) $user->tour_monitoring_seen);

        // Render ulang harus false semua
        $resp = $this->get(route('dashboard.index'));
        $resp->assertSee('dashboard: false', false);
        $resp->assertSee('rkas: false', false);
        $resp->assertSee('monitoring: false', false);
    }

    public function test_guest_tidak_bisa_akses_tur(): void
    {
        $this->post(route('tur.tandai', ['nama' => 'dashboard']))->assertRedirect('/login');
        $this->post(route('tur.reset'))->assertRedirect('/login');
    }

    public function test_tandai_selesai_idempoten_dan_cepat(): void
    {
        $user = User::first();
        $this->actingAs($user);

        // Panggilan pertama — ukur waktu
        $start = microtime(true);
        $this->post(route('tur.tandai', ['nama' => 'dashboard']))->assertOk()->assertJson(['ok' => true]);
        $elapsed1 = microtime(true) - $start;

        $user->refresh();
        $this->assertTrue((bool) $user->tour_dashboard_seen);

        // Panggilan kedua idempoten — tetap 200, tidak error, tetap true
        $start2 = microtime(true);
        $this->post(route('tur.tandai', ['nama' => 'dashboard']))->assertOk()->assertJson(['ok' => true]);
        $elapsed2 = microtime(true) - $start2;

        $user->refresh();
        $this->assertTrue((bool) $user->tour_dashboard_seen);

        // Endpoint harus cepat (< 1 detik) — tidak ada operasi berat, penting untuk keepalive race
        $this->assertLessThan(1.0, $elapsed1, 'POST /tur/tandai selesai harus <1s (aktual: '.$elapsed1.'s)');
        $this->assertLessThan(1.0, $elapsed2, 'POST idempoten kedua harus <1s (aktual: '.$elapsed2.'s)');

        // Simulasi navigasi cepat setelah Selesai: GET dashboard lalu /rkas lalu balik dashboard — tur tidak muncul lagi
        $this->get(route('rkas.index'))->assertOk();
        $resp = $this->get(route('dashboard.index'));
        $resp->assertSee('dashboard: true', false);
    }
}
