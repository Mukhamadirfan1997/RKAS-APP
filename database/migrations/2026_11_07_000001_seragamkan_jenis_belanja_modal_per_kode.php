<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan jenis belanja kelompok modal MURNI menurut kode rekening.
 *
 * Masalah yang diperbaiki (temuan 7 Nov 2026):
 *   - 5.2.02.05.* (Mebel, Mesin Ketik, Alat Kantor, Alat Pendingin, ...) berlabel
 *     "Belanja Modal Aset Tetap Lainnya" padahal kodenya 5.2.02 (Peralatan dan Mesin).
 *     Akibatnya 1 item Lemari Arsip Rp 17.500.000 masuk keranjang yang salah.
 *   - 5.2.05.* (buku, alat musik, software, lukisan, ...) berlabel "Belanja Modal Buku".
 *
 * Pemetaan baru:
 *   5.2.02* -> Belanja Modal Peralatan & Mesin
 *   5.2.05* -> Belanja Modal Aset Tetap Lainnya
 *
 * Kelompok lain (5.1.02.*, 5.2.04.*) TIDAK disentuh.
 *
 * Komponen JUKNIS tidak terpengaruh: komponen buku dikenali dari prefix 5.2.05
 * di config/juknis.php, bukan dari nama jenis belanja.
 */
return new class extends Migration
{
    private const ID_PERALATAN = 'Belanja Modal Peralatan & Mesin';

    private const ID_ASET_LAIN = 'Belanja Modal Aset Tetap Lainnya';

    public function up(): void
    {
        $idPeralatan = $this->jenisId(self::ID_PERALATAN);
        $idAsetLain = $this->jenisId(self::ID_ASET_LAIN);

        DB::table('master_kode_rekening')
            ->where('kode', 'like', '5.2.02%')
            ->update(['jenis_belanja_id' => $idPeralatan, 'kategori_belanja' => 'MODAL']);

        DB::table('master_kode_rekening')
            ->where('kode', 'like', '5.2.05%')
            ->update(['jenis_belanja_id' => $idAsetLain, 'kategori_belanja' => 'MODAL']);
    }

    public function down(): void
    {
        // Kembalikan ke pemetaan ARKAS/SmartRKAS sebelumnya.
        $idPeralatan = $this->jenisId(self::ID_PERALATAN);
        $idAsetLain = $this->jenisId(self::ID_ASET_LAIN);
        $idBuku = $this->jenisId('Belanja Modal Buku');

        DB::table('master_kode_rekening')
            ->where('kode', 'like', '5.2.05%')
            ->update(['jenis_belanja_id' => $idBuku]);

        DB::table('master_kode_rekening')
            ->where('kode', 'like', '5.2.02.05%')
            ->update(['jenis_belanja_id' => $idAsetLain]);

        DB::table('master_kode_rekening')
            ->where('kode', 'like', '5.2.02%')
            ->where('kode', 'not like', '5.2.02.05%')
            ->update(['jenis_belanja_id' => $idPeralatan]);
    }

    /**
     * Ambil id jenis belanja; buat bila belum ada (instalasi baru: migrasi
     * berjalan sebelum seeder mengisi tabel jenis_belanja).
     */
    private function jenisId(string $nama): int
    {
        $id = DB::table('jenis_belanja')->where('nama', $nama)->value('id');

        if ($id === null) {
            $id = DB::table('jenis_belanja')->insertGetId([
                'nama' => $nama,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $id;
    }
};
