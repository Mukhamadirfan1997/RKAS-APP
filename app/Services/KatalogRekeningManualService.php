<?php

namespace App\Services;

use App\Models\MasterKodeRekening;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Melengkapi kode rekening untuk entri manual sekolah di katalog barang.
 *
 * Latar belakang: 133 baris katalog berasal dari ketikan manual sekolah di
 * xlsm sumber (kolom "Kode Rekening" berisi "....."). Baris-baris itu BUKAN
 * sampah impor sehingga tidak boleh dihapus, tetapi juga tidak bisa dipakai
 * karena rekeningnya kosong.
 *
 * Sumber pemetaan: database/seeders/data/katalog-rekening-manual-2026-10.json
 * Setiap baris sudah ditetapkan rekening tujuannya + alasannya (kolom "basis"),
 * diturunkan dari bukti isi katalog resmi (81.062 baris) dan master rekening:
 * - 105 baris buku/ensiklopedia/literasi -> 5.1.02.01.01.0026 (Bahan Cetak),
 *   mengikuti 38 dari 39 kembaran resmi di katalog yang memakai rekening itu.
 * - 28 baris non-buku dipetakan per jenis barang (komputer, jaringan, audio,
 *   alat musik, mebel, olahraga, listrik, dll) mengikuti penempatan baris
 *   sejenis di katalog resmi.
 *
 * Sifat operasi:
 * - Hanya menyentuh baris yang kode_rekening-nya masih "....." (tidak menimpa
 *   koreksi manual pengguna maupun data yang sudah benar).
 * - Rekening tujuan wajib ada di master_kode_rekening; bila belum ada, baris
 *   dilewati dan dicatat (tidak membuat kode rekening "hantu").
 * - Idempotent: dijalankan berulang tidak mengubah apa pun lagi.
 */
class KatalogRekeningManualService
{
    /** Penanda kolom kode rekening yang belum diisi pada entri manual sekolah. */
    public const KODE_REKENING_KOSONG = '.....';

    public const FIXTURE = 'seeders/data/katalog-rekening-manual-2026-10.json';

    /**
     * @return array{diperbarui: int, dilewati: int, rekening_tidak_dikenal: array<int, string>}
     */
    public function jalankan(): array
    {
        $items = $this->items();

        if ($items === []) {
            return ['diperbarui' => 0, 'dilewati' => 0, 'rekening_tidak_dikenal' => []];
        }

        $rekeningTersedia = MasterKodeRekening::query()
            ->whereIn('kode', array_values(array_unique(array_column($items, 'kode_rekening'))))
            ->pluck('kode')
            ->all();

        // Instalasi baru: migrasi berjalan sebelum master rekening di-seed.
        // Tidak ada gunanya membandingkan 133 baris dengan master kosong.
        if ($rekeningTersedia === [] && MasterKodeRekening::query()->count() === 0) {
            Log::info('Master kode rekening belum tersedia; pelengkapan rekening entri manual dilewati.', [
                'baris' => count($items),
            ]);

            return ['diperbarui' => 0, 'dilewati' => count($items), 'rekening_tidak_dikenal' => []];
        }

        $diperbarui = 0;
        $dilewati = 0;
        $tidakDikenal = [];

        foreach ($items as $item) {
            $kodeRekening = (string) ($item['kode_rekening'] ?? '');
            $idBarang = (string) ($item['id_barang_arkas'] ?? '');

            if ($idBarang === '' || $kodeRekening === '') {
                $dilewati++;

                continue;
            }

            if (! in_array($kodeRekening, $rekeningTersedia, true)) {
                $tidakDikenal[$kodeRekening] = true;
                $dilewati++;

                continue;
            }

            $diperbarui += DB::table('kode_barang')
                ->where('id_barang_arkas', $idBarang)
                ->where('kode_rekening', self::KODE_REKENING_KOSONG)
                ->update([
                    'kode_rekening' => $kodeRekening,
                    'updated_at' => now(),
                ]);
        }

        if ($tidakDikenal !== []) {
            Log::warning('Rekening tujuan pemetaan entri manual belum ada di master, baris dilewati.', [
                'rekening' => array_keys($tidakDikenal),
            ]);
        }

        return [
            'diperbarui' => $diperbarui,
            'dilewati' => $dilewati,
            'rekening_tidak_dikenal' => array_keys($tidakDikenal),
        ];
    }

    /**
     * Seluruh baris pemetaan beserta alasannya (dipakai untuk audit/rollback).
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        $path = database_path(self::FIXTURE);

        if (! is_file($path)) {
            Log::warning('Fixture pemetaan rekening entri manual tidak ditemukan, dilewati.', ['path' => $path]);

            return [];
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data['items'] ?? [];
    }

    /**
     * Daftar pasangan (id_barang_arkas => kode_rekening) hasil pemetaan,
     * dipakai untuk membalik perubahan saat rollback migrasi.
     *
     * @return array<string, string>
     */
    public function petaRekening(): array
    {
        $peta = [];

        foreach ($this->items() as $item) {
            $peta[(string) $item['id_barang_arkas']] = (string) $item['kode_rekening'];
        }

        return $peta;
    }
}
