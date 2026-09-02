<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalisasi ejaan program/sub_program di master_program.
     *
     * Mapping eksplisit per nilai (bukan auto lower/trim) — sesuai konfirmasi
     * Langkah 1: 2026-09-03.
     * - program: 5 nilai casing tidak konsisten -> Title Case / istilah resmi SNP
     * - sub_program: 1 nilai "/" -> " dan "
     */
    public function up(): void
    {
        // --- program (Standar) : 5 mapping ---
        DB::table('master_program')
            ->where('program', 'Pengembangan pendidik dan tenaga kependidikan')
            ->update(['program' => 'Pengembangan Pendidik dan Tenaga Kependidikan']);

        DB::table('master_program')
            ->where('program', 'Pengembangan sarana dan prasarana sekolah')
            ->update(['program' => 'Pengembangan Sarana dan Prasarana Sekolah']);

        DB::table('master_program')
            ->where('program', 'Pengembangan standar pembiayaan')
            ->update(['program' => 'Pengembangan Standar Pembiayaan']);

        DB::table('master_program')
            ->where('program', 'Pengembangan standar pengelolaan')
            ->update(['program' => 'Pengembangan Standar Pengelolaan']);

        // Pilihan konfirmasi: "Pengembangan Standar Penilaian" (resmi SNP/ARKAS)
        DB::table('master_program')
            ->where('program', 'Pengembangan dan implementasi sistem penilaian')
            ->update(['program' => 'Pengembangan Standar Penilaian']);

        // --- sub_program (Program) : 1 mapping ---
        DB::table('master_program')
            ->where('sub_program', 'Pelaksanaan Kegiatan Asesmen/Evaluasi Pembelajaran')
            ->update(['sub_program' => 'Pelaksanaan Kegiatan Asesmen dan Evaluasi Pembelajaran']);
    }

    public function down(): void
    {
        DB::table('master_program')
            ->where('program', 'Pengembangan Pendidik dan Tenaga Kependidikan')
            ->update(['program' => 'Pengembangan pendidik dan tenaga kependidikan']);

        DB::table('master_program')
            ->where('program', 'Pengembangan Sarana dan Prasarana Sekolah')
            ->update(['program' => 'Pengembangan sarana dan prasarana sekolah']);

        DB::table('master_program')
            ->where('program', 'Pengembangan Standar Pembiayaan')
            ->update(['program' => 'Pengembangan standar pembiayaan']);

        DB::table('master_program')
            ->where('program', 'Pengembangan Standar Pengelolaan')
            ->update(['program' => 'Pengembangan standar pengelolaan']);

        DB::table('master_program')
            ->where('program', 'Pengembangan Standar Penilaian')
            ->update(['program' => 'Pengembangan dan implementasi sistem penilaian']);

        DB::table('master_program')
            ->where('sub_program', 'Pelaksanaan Kegiatan Asesmen dan Evaluasi Pembelajaran')
            ->whereIn('kode', ['03.04.01', '03.04.02'])
            ->update(['sub_program' => 'Pelaksanaan Kegiatan Asesmen/Evaluasi Pembelajaran']);
        // Catatan: down untuk sub_program hanya mengembalikan 2 baris 03.04.*,
        // karena 11 baris 08.04.* memang asalnya sudah " dan " — tidak diubah.
    }
};
