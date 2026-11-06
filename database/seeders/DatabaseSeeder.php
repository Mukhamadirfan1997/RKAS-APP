<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder PRODUKSI — hanya mengisi data referensi yang dibutuhkan aplikasi
 * agar bisa langsung dipakai (kode kegiatan, kode rekening, jenis belanja,
 * pemetaan JUKNIS, dan katalog barang).
 *
 * TIDAK mengisi data milik sekolah:
 * - tidak ada akun default (akun dibuat lewat halaman Registrasi),
 * - tidak ada profil sekolah,
 * - tidak ada tahun anggaran / pagu,
 * - tidak ada item RKAS contoh.
 *
 * Seeder ini didefinisikan sebagai "master-data-only" dan dipakai sebagai
 * basis `resources/database-seed.sqlite` untuk instalasi baru.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Jenis belanja resmi ARKAS (9) + program/kegiatan (162) +
        //    kode rekening (279) dari sumber nyata SmartRKAS.
        $this->call(SmartRkasMasterSeeder::class);

        // 2. Kategori kepatuhan JUKNIS (honor/buku/sarpras/tahap I).
        $this->call(JuknisSeeder::class);
    }
}
