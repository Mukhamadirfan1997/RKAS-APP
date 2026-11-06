<?php

namespace Tests\Feature;

use Database\Seeders\DataUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_returns_a_successful_response(): void
    {
        // Perlu akun (tanpa akun, aplikasi mengarahkan ke halaman Registrasi).
        $this->seed(DataUjiSeeder::class);

        $this->get('/login')->assertStatus(200);
    }
}
