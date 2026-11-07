<?php

namespace App\Services;

use App\Models\JenisBelanja;

/**
 * Satu-satunya aturan pemetaan kode rekening -> jenis belanja.
 *
 * Sumber kebenaran adalah PREFIX KODE REKENING (hierarki resmi BAS/ARKAS),
 * bukan urutan array dan bukan tebakan per rekening:
 *
 *   5.2.02           -> Belanja Modal Peralatan & Mesin
 *                       (termasuk 5.2.02.05 Alat Kantor & Rumah Tangga/Mebel)
 *   5.2.05           -> Belanja Modal Aset Tetap Lainnya
 *                       (termasuk buku/perpustakaan 5.2.05.01 dan alat musik/software)
 *
 * Catatan penting: aturan JUKNIS (config/juknis.php) TIDAK memakai nama jenis
 * belanja untuk komponen buku — komponen buku dikenali dari prefix 5.2.05
 * sehingga perubahan label jenis tidak mengubah persentase kepatuhan.
 *
 * Prefix TERPANJANG menang, jadi urutan penulisan di array tidak menentukan.
 */
class JenisBelanjaResolver
{
    public const RULES = [
        // Belanja operasional (BARJAS)
        '5.1.02.01.01.0026' => 'Belanja Cetak',
        '5.1.02.01' => 'Belanja Barang Persediaan',
        '5.1.02.02' => 'Belanja Jasa',
        '5.1.02.03' => 'Belanja Jasa Pemeliharaan',
        '5.1.02.04' => 'Belanja Perjalanan Dinas',

        // Belanja modal — dipisah murni menurut kode rekening
        '5.2.02' => 'Belanja Modal Peralatan & Mesin',
        '5.2.05' => 'Belanja Modal Aset Tetap Lainnya',

        // Cadangan
        '5.2' => 'Belanja Modal Peralatan & Mesin',
        '5.1' => 'Belanja Lainnya',
    ];

    /** @var array<int, string>|null */
    private static ?array $prefixTerurut = null;

    public function namaRekening(string $kode): string
    {
        foreach ($this->prefixTerurut() as $prefix) {
            if (str_starts_with($kode, $prefix)) {
                return self::RULES[$prefix];
            }
        }

        return 'Belanja Lainnya';
    }

    public function jenisId(string $kode): ?int
    {
        return JenisBelanja::where('nama', $this->namaRekening($kode))->value('id');
    }

    public function legacyKategori(?string $jenis): string
    {
        return match ($jenis) {
            'Belanja Modal Buku', 'Belanja Modal Peralatan & Mesin', 'Belanja Modal Aset Tetap Lainnya' => 'MODAL',
            'Belanja Jasa', 'Belanja Jasa Pemeliharaan', 'Belanja Perjalanan Dinas', 'Belanja Lainnya' => 'BARJAS',
            default => 'BARJAS',
        };
    }

    /**
     * Prefix diurutkan dari yang terpanjang agar 5.2.02 / 5.2.05 tidak
     * tertelan aturan cadangan 5.2.
     *
     * @return array<int, string>
     */
    private function prefixTerurut(): array
    {
        if (self::$prefixTerurut === null) {
            $prefixes = array_keys(self::RULES);
            usort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));
            self::$prefixTerurut = $prefixes;
        }

        return self::$prefixTerurut;
    }
}
