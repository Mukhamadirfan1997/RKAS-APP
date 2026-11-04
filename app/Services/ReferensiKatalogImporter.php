<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Impor katalog "Referensi Barang Jasa & Barang Modal" dari Dinas Pendidikan
 * ke tabel `kode_barang`.
 *
 * Prinsip (disepakati 2026-11-04):
 * - TIDAK PERNAH menghapus atau menimpa nama barang yang sudah ada.
 * - Baris baru hanya ditambahkan bila pasangan (nama ternormalisasi + kode
 *   rekening hasil pemetaan) belum ada di katalog.
 * - Baris yang sudah ada hanya diperbarui harganya (bila nilai baru > 0) dan
 *   dilengkapi kode rekening/kategori yang masih kosong.
 * - Kode rekening untuk barang tanpa rekening ditentukan oleh
 *   KatalogRekeningResolver memakai config/katalog_rekening_rules.php.
 * - Idempotent: aman dijalankan berulang tanpa menggandakan baris.
 *
 * Data sumber sudah dikonversi ke fixture JSON
 * database/seeders/data/referensi-katalog-jasa-modal-2026-11.json supaya
 * migrasi tetap berjalan pada aplikasi terpasang (file xlsx tidak dibundel).
 */
class ReferensiKatalogImporter
{
    /** Penanda baris hasil impor ini pada kolom `kode_barang.sumber`. */
    public const SUMBER = 'referensi_dinas';

    public const BERKAS = 'seeders/data/referensi-katalog-jasa-modal-2026-11.json';

    public function __construct(protected KatalogRekeningResolver $resolver) {}

    public function pathBerkas(?string $berkas = null): string
    {
        $berkas = $berkas ?? self::BERKAS;

        return str_starts_with($berkas, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:/', $berkas) === 1
            ? $berkas
            : database_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $berkas));
    }

    /**
     * @return array{
     *     total_baris: int, baru: int, harga_diperbarui: int,
     *     rekening_dilengkapi: int, duplikat_dilewati: int,
     *     per_dasar: array<string, int>, uji_coba: bool
     * }
     */
    public function jalankan(bool $ujiCoba = false, ?string $berkas = null): array
    {
        $path = $this->pathBerkas($berkas);
        $hasil = [
            'total_baris' => 0,
            'baru' => 0,
            'harga_diperbarui' => 0,
            'rekening_dilengkapi' => 0,
            'duplikat_dilewati' => 0,
            'per_dasar' => [],
            'uji_coba' => $ujiCoba,
        ];

        if (! is_file($path)) {
            Log::warning('Fixture katalog referensi dinas tidak ditemukan, impor dilewati.', ['path' => $path]);

            return $hasil;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['barang'])) {
            Log::warning('Fixture katalog referensi dinas kosong/tidak valid.', ['path' => $path]);

            return $hasil;
        }

        $peta = $this->muatPetaKatalog();
        $baru = [];
        $idTerlihat = [];

        // Tahap 1 — kumpulkan baris unik per pasangan (nama + kode rekening).
        // Fixture memuat banyak nama sama dengan harga berbeda; menyimpannya
        // sekali membuat hasil impor deterministik (dijalankan ulang tidak
        // mengubah harga bolak-balik). Bila harga berbeda, dipakai yang
        // tertinggi supaya acuan/SSH tidak merendah.
        $unik = [];

        foreach ($data['barang'] as $baris) {
            $nama = $this->rapikan((string) ($baris['nama'] ?? ''));
            if ($nama === '') {
                continue;
            }

            $id = trim((string) ($baris['id'] ?? ''));
            if ($id !== '' && isset($idTerlihat[$id])) {
                $hasil['duplikat_dilewati']++;

                continue;
            }
            $idTerlihat[$id] = true;
            $hasil['total_baris']++;

            $pemetaan = $this->resolver->resolve(
                $nama,
                $baris['rekening_asal'] ?? null,
                $baris['belanja'] ?? null,
                $baris['kategori'] ?? null,
                $baris['satuan'] ?? null,
            );

            [$harga, $min, $maks] = $this->harga($baris);

            $kunci = $this->kunciNama($nama).'|'.$pemetaan['rekening'];

            $kandidat = [
                'id' => $id,
                'nama' => $nama,
                'satuan' => trim((string) ($baris['satuan'] ?? '')),
                'harga' => $harga,
                'harga_min' => $min,
                'harga_max' => $maks,
                'belanja' => trim((string) ($baris['belanja'] ?? '')),
                'kategori' => trim((string) ($baris['kategori'] ?? '')),
                'kode_rekening' => $pemetaan['rekening'],
                'dasar' => $pemetaan['dasar'],
            ];

            if (isset($unik[$kunci])) {
                $hasil['duplikat_dilewati']++;
                if ($kandidat['harga'] > $unik[$kunci]['harga']) {
                    $unik[$kunci] = $kandidat;
                }

                continue;
            }

            $unik[$kunci] = $kandidat;
        }

        foreach ($unik as $dasar) {
            $hasil['per_dasar'][$dasar['dasar']] = ($hasil['per_dasar'][$dasar['dasar']] ?? 0) + 1;
        }

        // Tahap 2 — cocokkan dengan katalog yang ada, lalu perbarui/tambah.
        foreach ($unik as $kunci => $baris) {
            // Pencocokan HANYA lewat pasangan (nama ternormalisasi + kode
            // rekening). `id_barang_arkas` milik file dinas memakai skema
            // berbeda dari katalog sekolah (6.501 dari 6.538 ID yang sama
            // justru menunjuk barang berbeda), jadi tidak boleh dipakai.
            // Baris lama yang rekeningnya masih kosong/"....." boleh dicocokkan
            // lewat nama saja, selama namanya unik di katalog.
            $idLama = $peta['nama_rek'][$kunci]
                ?? $peta['nama_kosong'][$this->kunciNama($baris['nama'])]
                ?? null;

            if ($idLama !== null) {
                $this->perbarui(
                    $idLama,
                    $baris,
                    $baris['kode_rekening'],
                    $baris['harga'],
                    $baris['harga_min'],
                    $baris['harga_max'],
                    $hasil,
                    $ujiCoba,
                );

                continue;
            }

            // `id_barang_arkas` sengaja dikosongkan: kolom itu ber-UNIQUE index
            // dan hanya boleh diisi ID katalog resmi (xlsm) agar paket update
            // katalog tetap deterministik. ID barang file dinas disimpan di
            // kolom `kode` (tampil di cetakan PDF/Excel sebagai Kode Barang).
            $baru[] = [
                'kode' => mb_substr($baris['id'] !== '' ? $baris['id'] : 'REF-'.md5($kunci), 0, 100),
                'id_barang_arkas' => null,
                'nama' => mb_substr($baris['nama'], 0, 255),
                'satuan_default' => mb_substr($baris['satuan'], 0, 50) ?: null,
                'harga_acuan' => $baris['harga'],
                'harga_min' => $baris['harga_min'],
                'harga_max' => $baris['harga_max'],
                'kode_belanja' => mb_substr($baris['belanja'], 0, 20) ?: null,
                'kategori' => mb_substr($baris['kategori'], 0, 100) ?: null,
                'kode_rekening' => $baris['kode_rekening'],
                'sumber' => self::SUMBER,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $hasil['baru']++;

            if (! $ujiCoba && count($baru) >= 200) {
                DB::table('kode_barang')->insert($baru);
                $baru = [];
            }
        }

        if (! $ujiCoba && $baru !== []) {
            DB::table('kode_barang')->insert($baru);
        }

        return $hasil;
    }

    /**
     * Banyaknya baris yang sudah ditandai hasil impor ini (dipakai migrasi
     * untuk melewati impor pada instalasi yang sudah terisi).
     */
    public function jumlahBarisImpor(): int
    {
        return (int) DB::table('kode_barang')->where('sumber', self::SUMBER)->count();
    }

    /**
     * Peta baris katalog yang sudah ada:
     * - `nama_rek`: "NAMA|REKENING" => id (pencocokan utama)
     * - `nama_kosong`: "NAMA" => id untuk baris yang kode rekeningnya masih
     *   kosong/"....." (baris lama tanpa rekening boleh dilengkapi), bernilai
     *   null bila nama tersebut muncul lebih dari sekali (ambigu, jangan
     *   ditebak).
     *
     * @return array{nama_rek: array<string, int>, nama_kosong: array<string, int|null>}
     */
    protected function muatPetaKatalog(): array
    {
        $peta = ['nama_rek' => [], 'nama_kosong' => []];

        DB::table('kode_barang')
            ->select('id', 'nama', 'kode_rekening')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$peta) {
                foreach ($rows as $row) {
                    if ($row->nama === null || $row->nama === '') {
                        continue;
                    }
                    $nama = $this->kunciNama((string) $row->nama);
                    $rekening = trim((string) $row->kode_rekening);

                    $peta['nama_rek'][$nama.'|'.$rekening] ??= (int) $row->id;

                    if ($rekening === '' || str_starts_with($rekening, '.....')) {
                        if (array_key_exists($nama, $peta['nama_kosong'])) {
                            $peta['nama_kosong'][$nama] = null; // nama ganda -> ambigu
                        } else {
                            $peta['nama_kosong'][$nama] = (int) $row->id;
                        }
                    }
                }
            });

        return $peta;
    }

    /**
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $hasil
     */
    protected function perbarui(
        int $idLama,
        array $baris,
        string $rekening,
        float $harga,
        float $min,
        float $maks,
        array &$hasil,
        bool $ujiCoba = false,
    ): bool {
        $lama = DB::table('kode_barang')
            ->where('id', $idLama)
            ->first(['harga_acuan', 'harga_min', 'harga_max', 'kode_rekening', 'kategori', 'kode_belanja', 'satuan_default']);

        if ($lama === null) {
            return false;
        }

        $ubah = [];

        if ($harga > 0 && (float) $lama->harga_acuan !== $harga) {
            $ubah['harga_acuan'] = $harga;
        }
        if ($min > 0 && (float) $lama->harga_min !== $min) {
            $ubah['harga_min'] = $min;
        }
        if ($maks > 0 && (float) $lama->harga_max !== $maks) {
            $ubah['harga_max'] = $maks;
        }

        // Lengkapi data yang masih kosong saja — jangan timpa isian pengguna.
        $rekeningLama = trim((string) $lama->kode_rekening);
        if ($rekeningLama === '' || str_starts_with($rekeningLama, '.....')) {
            $ubah['kode_rekening'] = $rekening;
            $hasil['rekening_dilengkapi']++;
        }
        if (trim((string) $lama->kategori) === '' && trim((string) ($baris['kategori'] ?? '')) !== '') {
            $ubah['kategori'] = mb_substr(trim((string) $baris['kategori']), 0, 100);
        }
        if (trim((string) $lama->kode_belanja) === '' && trim((string) ($baris['belanja'] ?? '')) !== '') {
            $ubah['kode_belanja'] = mb_substr(trim((string) $baris['belanja']), 0, 20);
        }
        if (trim((string) $lama->satuan_default) === '' && trim((string) ($baris['satuan'] ?? '')) !== '') {
            $ubah['satuan_default'] = mb_substr(trim((string) $baris['satuan']), 0, 50);
        }

        if ($ubah === []) {
            return false;
        }

        if (isset($ubah['harga_acuan']) || isset($ubah['harga_min']) || isset($ubah['harga_max'])) {
            $hasil['harga_diperbarui']++;
        }

        if ($ujiCoba) {
            return true;
        }

        $ubah['updated_at'] = now();
        DB::table('kode_barang')->where('id', $idLama)->update($ubah);

        return true;
    }

    /**
     * Harga acuan: pakai Harga Barang; bila 0 pakai Harga Maksimal (kebiasaan
     * file dinas). Batas atas SSH diisi harga acuan bila kosong agar KONTROL
     * tetap bisa dinilai.
     *
     * @param  array<string, mixed>  $baris
     * @return array{0: float, 1: float, 2: float}
     */
    protected function harga(array $baris): array
    {
        $harga = (float) ($baris['harga'] ?? 0);
        $min = (float) ($baris['harga_min'] ?? 0);
        $maks = (float) ($baris['harga_max'] ?? 0);

        if ($harga <= 0) {
            $harga = $maks;
        }
        if ($maks <= 0) {
            $maks = $harga;
        }

        return [round($harga, 2), round($min, 2), round($maks, 2)];
    }

    protected function rapikan(string $nama): string
    {
        $nama = str_replace(["\xc2\xa0", "\r\n", "\r", "\n", "\t"], ' ', $nama);

        return trim((string) preg_replace('/\s+/', ' ', $nama));
    }

    protected function kunciNama(string $nama): string
    {
        return mb_strtoupper($this->rapikan($nama));
    }
}
