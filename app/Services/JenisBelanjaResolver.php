<?php

namespace App\Services;

use App\Models\JenisBelanja;

class JenisBelanjaResolver
{
    public const RULES = [
        '5.1.02.01' => 'Belanja Barang Persediaan',
        '5.1.02.02' => 'Belanja Jasa',
        '5.1.02.03' => 'Belanja Jasa Pemeliharaan',
        '5.1.02.04' => 'Belanja Perjalanan Dinas',
        '5.2.02.10' => 'Belanja Modal Peralatan & Mesin',
        '5.2.05' => 'Belanja Modal Buku',
        '5.2.02.05' => 'Belanja Modal Aset Tetap Lainnya',
        '5.2' => 'Belanja Modal Peralatan & Mesin',
        '5.1' => 'Belanja Lainnya',
    ];

    public function namaRekening(string $kode): string
    {
        foreach (self::RULES as $prefix => $namaJenis) {
            if (str_starts_with($kode, $prefix)) {
                return $namaJenis;
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
}
