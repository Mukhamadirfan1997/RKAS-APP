<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AkunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_update_akun_success(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $resp = $this->post(route('pengaturan.update-akun'), [
            'name' => 'Operator Baru',
            'email' => 'baru@sekolah.id',
            'current_password' => 'password',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);
        $resp->assertRedirect(route('pengaturan.akun'));
        $resp->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals('Operator Baru', $user->name);
        $this->assertEquals('baru@sekolah.id', $user->email);
        $this->assertTrue(Hash::check('newpass123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'akun.update', 'auditable_id' => $user->id]);
    }

    public function test_update_akun_current_password_salah_ditolak(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $resp = $this->post(route('pengaturan.update-akun'), [
            'name' => 'X',
            'email' => 'x@sekolah.id',
            'current_password' => 'salah',
        ]);
        $resp->assertSessionHasErrors(['current_password']);
        $user->refresh();
        $this->assertNotEquals('X', $user->name);
    }

    public function test_update_akun_tanpa_ganti_password(): void
    {
        $user = User::first();
        $this->actingAs($user);
        $resp = $this->post(route('pengaturan.update-akun'), [
            'name' => 'Nama Baru Saja',
            'email' => 'nama@sekolah.id',
            'current_password' => 'password',
            'password' => '',
            'password_confirmation' => '',
        ]);
        $resp->assertRedirect();
        $user->refresh();
        $this->assertEquals('Nama Baru Saja', $user->name);
        $this->assertTrue(Hash::check('password', $user->password)); // tetap lama
    }
}
