<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga bundel desktop.
 *
 * Regresi nyata v1.6.0: `composer.json` dikeluarkan dari `bundle.resources`
 * saat bundel dipangkas. Akibatnya SETIAP perintah artisan di aplikasi
 * terpasang gagal (`Application::getNamespace()` membaca composer.json),
 * termasuk migrasi otomatis saat aplikasi dijalankan — log
 * `storage/logs/migrate.log` berisi "GAGAL (exit code: 1)". Data pengguna
 * jadi tertinggal di skema lama tanpa ada tanda apa pun di layar.
 *
 * Test ini mengunci daftar berkas yang WAJIB ikut bundel agar pemangkasan
 * berikutnya tidak memutus migrasi otomatis lagi.
 */
class BundelDesktopTest extends TestCase
{
    private function konfigurasi(): array
    {
        $path = base_path('src-tauri/tauri.conf.json');
        $this->assertFileExists($path, 'tauri.conf.json tidak ditemukan.');

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_composer_json_ikut_dibundel_agar_artisan_bisa_jalan(): void
    {
        $resources = $this->konfigurasi()['bundle']['resources'] ?? [];

        $this->assertArrayHasKey(
            '../composer.json',
            $resources,
            'composer.json wajib ada di bundle.resources: tanpa berkas ini semua perintah artisan (termasuk migrate otomatis) gagal di aplikasi terpasang.'
        );
        $this->assertSame('./composer.json', $resources['../composer.json']);
    }

    public function test_berkas_penting_runtime_ikut_dibundel(): void
    {
        $resources = $this->konfigurasi()['bundle']['resources'] ?? [];

        $wajib = [
            '../app', '../bootstrap', '../config',
            '../database/migrations', '../database/seeders/data',
            '../public', '../resources/views',
            '../resources/database-seed.sqlite',
            '../routes', '../vendor', '../artisan', '../composer.json',
        ];

        foreach ($wajib as $kunci) {
            $this->assertArrayHasKey($kunci, $resources, "Berkas/folder runtime '{$kunci}' hilang dari bundle.resources.");
        }
    }

    public function test_semua_sumber_bundel_benar_benar_ada(): void
    {
        $resources = $this->konfigurasi()['bundle']['resources'] ?? [];
        $akar = base_path('src-tauri');

        foreach (array_keys($resources) as $relatif) {
            $this->assertFileExists(
                $akar.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relatif),
                "Sumber bundel '{$relatif}' tidak ada di disk sehingga build akan gagal."
            );
        }
    }

    public function test_composer_json_memuat_pemetaan_namespace_app(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        // Laravel mencari namespace aplikasi lewat pemetaan psr-4 yang menunjuk
        // ke folder app/. Bila hilang, getNamespace() melempar RuntimeException.
        $this->assertArrayHasKey('App\\', $psr4);
        $this->assertSame('app/', $psr4['App\\']);
    }
}
