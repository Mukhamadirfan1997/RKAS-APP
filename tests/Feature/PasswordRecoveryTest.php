<?php

namespace Tests\Feature;

use App\Models\PengaturanSekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_lupa_password_page_shows(): void
    {
        $this->get(route('auth.forgot'))->assertOk();
    }

    public function test_verifikasi_npsn_email_benar_redirect_ke_reset(): void
    {
        $sekolah = PengaturanSekolah::first();
        $user = User::first();
        $resp = $this->post(route('auth.forgot.attempt'), [
            'npsn' => $sekolah->npsn,
            'email' => $user->email,
        ]);
        $resp->assertRedirect(route('auth.reset'));
        $this->assertTrue(session()->has('pw_reset_npsn_verified'));
    }

    public function test_verifikasi_gagal_jika_npsn_salah(): void
    {
        $user = User::first();
        $resp = $this->post(route('auth.forgot.attempt'), [
            'npsn' => '00000000',
            'email' => $user->email,
        ]);
        $resp->assertSessionHasErrors(['npsn']);
        $this->assertFalse(session()->has('pw_reset_npsn_verified'));
    }

    public function test_reset_tanpa_verifikasi_ditolak(): void
    {
        $this->get(route('auth.reset'))->assertRedirect(route('auth.forgot'));
        $this->post(route('auth.reset.attempt'), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('auth.forgot'));
    }

    public function test_reset_berhasil_dan_bisa_login_dengan_password_baru(): void
    {
        $sekolah = PengaturanSekolah::first();
        $user = User::first();
        $this->post(route('auth.forgot.attempt'), [
            'npsn' => $sekolah->npsn,
            'email' => $user->email,
        ]);

        $resp = $this->post(route('auth.reset.attempt'), [
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ]);
        $resp->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();

        // Logout dan login dengan password baru
        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'newpass1234',
        ])->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();

        // Password lama tidak bisa
        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email']);
    }
}
