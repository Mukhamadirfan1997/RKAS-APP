<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan istilah penerimaan murid dengan aturan terbaru (Permendikdasmen
 * No. 3 Tahun 2025: SPMB — Sistem Penerimaan Murid Baru, menggantikan PPDB).
 *
 * Temuan 11 Nov 2026 (audit ulang master terhadap 5 file referensi Dinas
 * Pendidikan unduhan 31 Okt 2026):
 *   - Seluruh 162 kode kegiatan sudah lengkap (0 kode hilang / 0 kode berlebih).
 *   - 279 kode rekening sudah identik dengan file referensi (0 beda nama).
 *   - 91.157 kode barang = 81.062 katalog resmi + 10.095 referensi dinas.
 *   - Satu-satunya sisa istilah lama: kegiatan 03.01.01 masih tertulis
 *     "Pelaksanaan Pendaftaran Peserta Didik Baru (PPDB)" dan sub program
 *     "Penerimaan Peserta Didik Baru" (6 kegiatan 03.01.*).
 *
 * Catatan: file referensi Dinas menulis singkatan "PMB", sedangkan istilah
 * resmi Permendikdasmen adalah "SPMB" (Sistem Penerimaan Murid Baru) —
 * dipilih SPMB agar sesuai nomenklatur resmi yang dipakai ARKAS terbaru.
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    private const KODE_SPMB = '03.01.01';

    private const NAMA_SPMB = 'Pelaksanaan Pendaftaran Murid Baru (SPMB)';

    private const SUB_LAMA = 'Penerimaan Peserta Didik Baru';

    private const SUB_BARU = 'Penerimaan Murid Baru';

    public function up(): void
    {
        // 1. Nama kegiatan 03.01.01 -> istilah resmi SPMB.
        DB::table('master_program')
            ->where('kode', self::KODE_SPMB)
            ->update([
                'nama' => self::NAMA_SPMB,
                'sub_program' => self::SUB_BARU,
                'updated_at' => now(),
            ]);

        // 2. Sub program "Penerimaan Peserta Didik Baru" (03.01.01 - 03.01.06).
        DB::table('master_program')
            ->where('sub_program', self::SUB_LAMA)
            ->update(['sub_program' => self::SUB_BARU, 'updated_at' => now()]);

        // 3. Sisa singkatan PPDB pada nama kegiatan lain (bila ada).
        DB::table('master_program')
            ->where('nama', 'like', '%PPDB%')
            ->update([
                'nama' => DB::raw("replace(nama, '(PPDB)', '(SPMB)')"),
                'updated_at' => now(),
            ]);
        DB::table('master_program')
            ->where('nama', 'like', '%PPDB%')
            ->update([
                'nama' => DB::raw("replace(nama, 'PPDB', 'SPMB')"),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('master_program')
            ->where('kode', self::KODE_SPMB)
            ->update([
                'nama' => 'Pelaksanaan Pendaftaran Peserta Didik Baru (PPDB)',
                'sub_program' => self::SUB_LAMA,
                'updated_at' => now(),
            ]);

        DB::table('master_program')
            ->where('sub_program', self::SUB_BARU)
            ->update(['sub_program' => self::SUB_LAMA, 'updated_at' => now()]);
    }
};
