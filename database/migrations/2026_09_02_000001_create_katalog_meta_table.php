<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('katalog_meta', function (Blueprint $table) {
            $table->id();
            $table->string('versi', 50); // e.g. 2027.01, 2026.09
            $table->dateTime('tanggal_update');
            $table->unsignedInteger('jumlah_barang')->default(0);
            $table->string('checksum', 128)->nullable(); // sha256 of CSV
            $table->string('source_file', 255)->nullable();
            $table->timestamps();
        });

        // Seed initial meta dari katalog saat ini (agar halaman tidak kosong sebelum update pertama)
        // Akan diisi via seeder/command, tapi buat baris default jika belum ada
        // Unique index untuk kode_barang.id_barang_arkas agar upsert deterministik
        // (Arkas seeder pakai id_barang_arkas sebagai key unik)
        Schema::table('kode_barang', function (Blueprint $table) {
            // Jika belum ada unique, tambahkan (sqlite: create unique index)
            // Cek dulu apakah index sudah ada — untuk fresh install, ini baru
            $table->unique('id_barang_arkas', 'kode_barang_id_barang_arkas_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kode_barang', function (Blueprint $table) {
            $table->dropUnique('kode_barang_id_barang_arkas_unique');
        });
        Schema::dropIfExists('katalog_meta');
    }
};
