<?php

use App\Services\ReferensiDinasImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Terapkan referensi ARKAS dari Dinas Pendidikan (unduhan 2026-10-31):
     * - 23 kode kegiatan SD baru ke master_program
     * - 3 rekening Belanja Modal (5.2.02.01.03.0003-0005)
     * - 1.052 harga acuan katalog yang namanya cocok persis
     *
     * Catatan: entri manual sekolah berkode rekening "....." (133 baris)
     * TIDAK dihapus — lihat ReferensiDinasImporter.
     *
     * Semua langkah idempotent (lihat App\Services\ReferensiDinasImporter).
     */
    public function up(): void
    {
        $hasil = app(ReferensiDinasImporter::class)->jalankan();

        Log::info('Referensi dinas 2026 diterapkan', $hasil);
    }

    public function down(): void
    {
        $importer = app(ReferensiDinasImporter::class);

        // Hapus hanya kegiatan yang belum dipakai item RKAS (hindari memutus data user).
        foreach ($importer->kodeKegiatan() as $kode) {
            $program = DB::table('master_program')->where('kode', $kode)->first();

            if ($program && ! DB::table('rkas_item')->where('master_program_id', $program->id)->exists()) {
                DB::table('master_program')->where('id', $program->id)->delete();
            }
        }

        foreach ($importer->kodeRekening() as $kode) {
            $rekening = DB::table('master_kode_rekening')->where('kode', $kode)->first();

            if ($rekening && ! DB::table('rkas_item')->where('master_kode_rekening_id', $rekening->id)->exists()) {
                DB::table('master_kode_rekening')->where('id', $rekening->id)->delete();
            }
        }

        // Harga acuan sengaja tidak dibalik: nilainya data referensi resmi
        // (SSH dinas), bukan perubahan struktural.
    }
};
