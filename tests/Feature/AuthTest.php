<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_success_redirects_and_logs_audit(): void
    {
        $resp = $this->post(route('login.attempt'), [
            'email' => 'admin@sekolah.id',
            'password' => 'password',
        ]);

        $resp->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'auditable_type' => User::class,
        ]);
        $log = AuditLog::where('action', 'login')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals(User::where('email', 'admin@sekolah.id')->first()->id, $log->user_id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $before = AuditLog::where('action', 'login')->count();

        $resp = $this->post(route('login.attempt'), [
            'email' => 'admin@sekolah.id',
            'password' => 'salah-password',
        ]);

        $resp->assertRedirect(); // back()
        $resp->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('tidak cocok', strtolower(collect($resp->getSession()->get('errors')->get('email'))->first() ?? ''));
        $this->assertGuest();

        // Audit tidak bertambah untuk percobaan gagal
        $this->assertEquals($before, AuditLog::where('action', 'login')->count());
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $before = AuditLog::where('action', 'login')->count();

        $resp = $this->post(route('login.attempt'), [
            'email' => 'tidakada@sekolah.id',
            'password' => 'password',
        ]);

        $resp->assertSessionHasErrors(['email']);
        $this->assertGuest();
        $this->assertEquals($before, AuditLog::where('action', 'login')->count());
    }

    public function test_authenticated_user_visiting_login_is_redirected(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $resp = $this->get(route('login'));
        $resp->assertRedirect('/');
    }

    public function test_logout_clears_session_and_protects_routes(): void
    {
        $user = User::first();
        $this->actingAs($user);

        // Pastikan sebelumnya bisa akses rkas
        $this->get(route('rkas.index'))->assertOk();

        $resp = $this->post(route('logout'));
        $resp->assertRedirect(route('login'));
        $this->assertGuest();

        // Setelah logout, akses route auth harus redirect ke login
        $this->get(route('rkas.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
        $this->get(route('backup.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        // Tanpa login sama sekali
        $this->get(route('rkas.index'))->assertRedirect(route('login'));
        $this->get('/pengaturan')->assertRedirect(route('login'));
        $this->post(route('rkas.store'), [])->assertRedirect(route('login'));
    }
}
