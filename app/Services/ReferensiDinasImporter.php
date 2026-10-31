<?php

namespace App\Services;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menerapkan hasil pemeriksaan "Referensi ARKAS dari Dinas Pendidikan"
 * (unduhan 2026-10-31) ke data master aplikasi.
 *
 * Sumber data: database/seeders/data/referensi-dinas-2026-10.json
 * File JSON ini hasil ekstraksi 5 file referensi resmi (lihat meta.sumber):
 * - 23 kode kegiatan SD yang belum ada di master_program
 * - 3 rekening Belanja Modal (5.2.02.01.03.0003-0005) yang belum ada
 * - 1.052 harga acuan barang yang namanya cocok persis dengan katalog
 *
 * Catatan penting: nomor "ID Barang" pada file dinas TIDAK sama skema dengan
 * xlsm sumber katalog (6.501 dari 6.538 ID yang sama punya nama berbeda).
 * Karena itu harga dicocokkan lewat (kode_rekening + nama ternormalisasi),
 * bukan lewat ID.
 *
 * Importer ini TIDAK menghapus data katalog. Baris berkode rekening "....."
 * (133 baris) terbukti merupakan entri manual sekolah di xlsm sumber
 * (buku literasi, ensiklopedia, laptop, proyektor, alat musik, dll) — bukan
 * sampah impor — sehingga wajib dipertahankan.
 *
 * Semua langkah idempotent: aman dijalankan berulang tanpa menggandakan data.
 */
class ReferensiDinasImporter
{
    /** Kode rekening belum terisi pada entri manual sekolah (jangan dihapus). */
    public const KODE_REKENING_BELUM_DIISI = '.....';

    public function __construct(protected JenisBelanjaResolver $resolver) {}

    /**
     * @return array{kegiatan_baru: int, rekening_baru: int, harga_diperbarui: int}
     */
    public function jalankan(): array
    {
        $data = $this->muatFixture();

        return [
            'kegiatan_baru' => $this->tambahKegiatan($data['kegiatan'] ?? []),
            'rekening_baru' => $this->tambahRekening($data['rekening'] ?? []),
            'harga_diperbarui' => $this->terapkanHarga($data['harga'] ?? []),
        ];
    }

    /**
     * Daftar kode kegiatan yang ditambahkan (dipakai untuk rollback migrasi).
     *
     * @return array<int, string>
     */
    public function kodeKegiatan(): array
    {
        return array_column($this->muatFixture()['kegiatan'] ?? [], 'kode');
    }

    /**
     * Daftar kode rekening yang ditambahkan (dipakai untuk rollback migrasi).
     *
     * @return array<int, string>
     */
    public function kodeRekening(): array
    {
        return array_column($this->muatFixture()['rekening'] ?? [], 'kode');
    }

    /**
     * @return array<string, mixed>
     */
    protected function muatFixture(): array
    {
        $path = database_path('seeders/data/referensi-dinas-2026-10.json');

        if (! is_file($path)) {
            Log::warning('Fixture referensi dinas tidak ditemukan, impor dilewati.', ['path' => $path]);

            return [];
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, array<string, mixed>>  $list
     */
    protected function tambahKegiatan(array $list): int
    {
        $baru = 0;

        foreach ($list as $kegiatan) {
            $program = MasterProgram::firstOrCreate(
                ['kode' => $kegiatan['kode']],
                [
                    'nama' => $kegiatan['nama'],
                    'program' => $kegiatan['program'],
                    'sub_program' => $kegiatan['sub_program'],
                    'level' => $kegiatan['level'] ?? 4,
                ]
            );

            if ($program->wasRecentlyCreated) {
                $baru++;
            }
        }

        return $baru;
    }

    /**
     * @param  array<int, array<string, mixed>>  $list
     */
    protected function tambahRekening(array $list): int
    {
        $baru = 0;

        foreach ($list as $rekening) {
            $kode = (string) $rekening['kode'];
            $namaJenis = $this->resolver->namaRekening($kode);

            // Pastikan jenis belanja tersedia lebih dulu: pada instalasi baru
            // migrasi ini berjalan sebelum SmartRkasMasterSeeder mengisi 9 jenis.
            $jenis = JenisBelanja::firstOrCreate(['nama' => $namaJenis]);

            $rek = MasterKodeRekening::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $rekening['nama'],
                    'jenis_belanja_id' => $jenis->id,
                    'kategori_belanja' => $this->resolver->legacyKategori($namaJenis),
                ]
            );

            if ($rek->wasRecentlyCreated) {
                $baru++;
            }
        }

        return $baru;
    }

    /**
     * @param  array<int, array<string, mixed>>  $list
     */
    protected function terapkanHarga(array $list): int
    {
        $diperbarui = 0;

        foreach (array_chunk($list, 200) as $chunk) {
            foreach ($chunk as $harga) {
                $diperbarui += DB::table('kode_barang')
                    ->where('id_barang_arkas', $harga['id_barang_arkas'])
                    ->update([
                        'harga_acuan' => $harga['harga_acuan'],
                        'harga_min' => $harga['harga_min'],
                        'harga_max' => $harga['harga_max'],
                        'updated_at' => now(),
                    ]);
            }
        }

        return $diperbarui;
    }
}
