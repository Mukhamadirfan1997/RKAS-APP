<?php

use App\Services\KatalogRekeningManualService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Lengkapi kode rekening 133 entri manual sekolah di katalog barang.
     *
     * Baris-baris tersebut berasal dari ketikan manual sekolah di xlsm sumber
     * (kolom Kode Rekening berisi "....."), bukan sampah impor, sehingga
     * dipertahankan dan hanya rekeningnya yang dilengkapi.
     *
     * Pemetaan + alasan per baris: database/seeders/data/katalog-rekening-manual-2026-10.json
     * Idempotent: hanya baris yang masih "....." yang diperbarui.
     */
    public function up(): void
    {
        $hasil = app(KatalogRekeningManualService::class)->jalankan();

        Log::info('Rekening entri manual katalog dilengkapi', $hasil);
    }

    public function down(): void
    {
        // Kembalikan ke "....." hanya bila nilainya masih sama dengan hasil
        // pemetaan ini (perubahan manual pengguna tidak dibalik).
        foreach (app(KatalogRekeningManualService::class)->petaRekening() as $idBarang => $kodeRekening) {
            DB::table('kode_barang')
                ->where('id_barang_arkas', $idBarang)
                ->where('kode_rekening', $kodeRekening)
                ->update([
                    'kode_rekening' => KatalogRekeningManualService::KODE_REKENING_KOSONG,
                    'updated_at' => now(),
                ]);
        }
    }
};
