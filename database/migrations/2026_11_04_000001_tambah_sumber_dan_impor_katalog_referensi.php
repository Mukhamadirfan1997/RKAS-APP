<?php

use App\Services\ReferensiKatalogImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom penanda `kode_barang.sumber` lalu mengimpor katalog
 * "Referensi Barang Jasa & Barang Modal" dari Dinas Pendidikan.
 *
 * Impor bersifat MENAMBAH SAJA (tidak menghapus/menimpa nama barang yang ada)
 * dan idempotent — aman dijalankan berulang.
 *
 * Instalasi baru memakai `resources/database-seed.sqlite` yang sudah memuat
 * baris impor (bertanda sumber = referensi_dinas), sehingga pemeriksaan
 * jumlah di bawah membuat migrasi ini selesai seketika.
 */
return new class extends Migration
{
    /** Perkiraan jumlah baris hasil impor (untuk deteksi instalasi terisi). */
    private const AMBANG_SUDAH_IMPOR = 5000;

    public function up(): void
    {
        if (! Schema::hasColumn('kode_barang', 'sumber')) {
            Schema::table('kode_barang', function (Blueprint $table) {
                $table->string('sumber', 30)->nullable();
            });
        }

        // Jangan jalankan impor berat di lingkungan test (:memory:).
        if (app()->runningUnitTests()) {
            return;
        }

        $importer = app(ReferensiKatalogImporter::class);

        if ($importer->jumlahBarisImpor() >= self::AMBANG_SUDAH_IMPOR) {
            Log::info('Impor katalog referensi dinas dilewati: katalog sudah memuat baris referensi.');

            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $hasil = $importer->jalankan();

        Log::info('Impor katalog referensi dinas (Barang Jasa & Modal) selesai.', $hasil);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kode_barang', 'sumber')) {
            return;
        }

        DB::table('kode_barang')->where('sumber', ReferensiKatalogImporter::SUMBER)->delete();

        Schema::table('kode_barang', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
