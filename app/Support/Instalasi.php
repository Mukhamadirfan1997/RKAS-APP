<?php

namespace App\Support;

use App\Models\KodeBarang;
use App\Models\PengaturanSekolah;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Status instalasi aplikasi (desktop, 1 sekolah per komputer).
 *
 * Dipakai untuk memutuskan apakah aplikasi perlu menampilkan halaman
 * Registrasi / Pulihkan terlebih dahulu. Tidak ada akun default sama sekali:
 * akun operator hanya dibuat lewat halaman Registrasi (atau ikut terpulihkan
 * dari berkas backup).
 */
class Instalasi
{
    /**
     * Belum ada akun operator sama sekali → instalasi baru.
     *
     * Pengguna lama (sudah punya akun) tidak akan pernah melihat halaman
     * Registrasi/Pulihkan, karena hasilnya selalu false.
     */
    public static function belumTerdaftar(): bool
    {
        // Jaga-jaga saat migrasi belum jalan / tabel belum ada.
        if (! Schema::hasTable('users')) {
            return true;
        }

        return User::query()->doesntExist();
    }

    /**
     * Profil sekolah belum pernah diisi (pelengkap deteksi).
     */
    public static function profilKosong(): bool
    {
        if (! Schema::hasTable('pengaturan_sekolah')) {
            return true;
        }

        $profil = PengaturanSekolah::query()->first();

        return ! $profil || trim((string) $profil->nama_sekolah) === '';
    }

    /**
     * Halaman Registrasi/Pulihkan hanya boleh dibuka saat instalasi baru.
     */
    public static function bolehRegistrasi(): bool
    {
        return self::belumTerdaftar();
    }

    /**
     * Katalog kode barang belum terisi sama sekali.
     *
     * Kasus nyata yang dijaga: berkas `database-seed.sqlite` gagal disalin saat
     * pemasangan pertama. Aplikasi tetap terbuka dan tampak normal, tetapi
     * pencarian barang/rekening di lembar kerja tidak akan menemukan apa pun.
     * Banner peringatan membuat kondisi itu terlihat, bukan jadi teka-teki.
     */
    public static function katalogKosong(): bool
    {
        try {
            if (! Schema::hasTable('kode_barang')) {
                return true;
            }

            // `limit(1)->exists()` memakai index primary key: konstan, aman
            // dipanggil pada setiap halaman (tanpa count 91 ribu baris).
            return ! KodeBarang::query()->limit(1)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
