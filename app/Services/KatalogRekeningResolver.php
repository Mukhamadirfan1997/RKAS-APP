<?php

namespace App\Services;

use App\Models\MasterKodeRekening;

/**
 * Menentukan kode rekening yang relevan untuk barang katalog yang tidak
 * memiliki kode rekening resmi (atau memiliki kode yang tidak ada di master).
 *
 * Urutan penentuan (berhenti pada langkah pertama yang menghasilkan kode yang
 * benar-benar ada di master_kode_rekening):
 *
 * 1. Kode rekening asal dari file dinas, bila sudah ada di master.
 * 2. Aturan kata kunci berurutan pada config/katalog_rekening_rules.php.
 * 3. Barang bersatuan buku (eksemplar) -> aturan "buku".
 * 4. Peta kategori (kolom Kategori file Modal).
 * 5. Cadangan per kelompok kode rekening asal (prefix 4 segmen).
 * 6. Cadangan per kode belanja (RBM/BHP/BBJ/...).
 * 7. Cadangan global.
 */
class KatalogRekeningResolver
{
    /** @var array<string, mixed> */
    private array $aturan;

    /** @var array<int, string>|null */
    private ?array $kodeMaster = null;

    /** @var array<string, bool> */
    private array $adaDiMaster = [];

    /**
     * @param  array<string, mixed>|null  $aturan
     */
    public function __construct(?array $aturan = null)
    {
        $this->aturan = $aturan ?? config('katalog_rekening_rules', []);
    }

    /**
     * @return array{rekening: string, dasar: string, aturan: string}
     */
    public function resolve(
        string $nama,
        ?string $kodeAsal = null,
        ?string $kodeBelanja = null,
        ?string $kategori = null,
        ?string $satuan = null,
    ): array {
        $namaNormal = $this->normalisasi($nama);
        $kodeAsal = $this->bersihkanKode($kodeAsal);

        // 1. Kode rekening asal sudah ada di master.
        if ($kodeAsal !== null && $this->kodeValid($kodeAsal)) {
            return $this->hasil($kodeAsal, 'kode-asal', 'Kode rekening pada file referensi sudah ada di master');
        }

        // 2. Aturan kata kunci berurutan — dahulukan bagian nama pokok
        // (sebelum pemisah "-"), baru seluruh teks termasuk deskripsi, supaya
        // kata di deskripsi ("...membantu pemeliharaan...") tidak menyerobot.
        $kepala = $this->kepala($namaNormal);
        if ($kepala !== $namaNormal && $hasil = $this->jalankanAturan($kepala)) {
            return $hasil;
        }
        if ($hasil = $this->jalankanAturan($namaNormal, true)) {
            return $hasil;
        }

        // 3. Barang bersatuan buku.
        $satuanNormal = $this->normalisasi((string) $satuan);
        $satuanBuku = array_map(fn ($s) => $this->normalisasi((string) $s), $this->aturan['satuan_buku'] ?? []);
        if ($satuanNormal !== '' && in_array($satuanNormal, $satuanBuku, true)) {
            $idAturan = $this->aturan['aturan_satuan_buku'] ?? 'buku';
            foreach ($this->aturan['aturan'] ?? [] as $aturan) {
                if (($aturan['id'] ?? null) === $idAturan && $hasil = $this->nilaiAturan($namaNormal, $aturan, 'satuan buku')) {
                    return $hasil;
                }
            }
        }

        // 4. Peta kategori.
        $kategoriNormal = $this->normalisasi((string) $kategori);
        if ($kategoriNormal !== '' && ! empty($this->aturan['kategori'][$kategoriNormal])) {
            $kode = $this->bersihkanKode($this->aturan['kategori'][$kategoriNormal]);
            if ($kode !== null && $this->kodeValid($kode)) {
                return $this->hasil($kode, 'kategori', 'Kategori "'.$kategori.'"');
            }
        }

        // 5. Cadangan per kelompok kode rekening asal.
        if ($kodeAsal !== null) {
            $kode = $this->cariKeluarga($kodeAsal);
            if ($kode !== null) {
                return $this->hasil($kode, 'keluarga', 'Kelompok kode rekening asal '.$this->prefixKeluarga($kodeAsal));
            }
        }

        // 6. Cadangan per kode belanja.
        $belanja = $this->normalisasi((string) $kodeBelanja);
        if ($belanja !== '' && ! empty($this->aturan['cadangan_belanja'][$belanja])) {
            $kode = $this->bersihkanKode($this->aturan['cadangan_belanja'][$belanja]);
            if ($kode !== null && $this->kodeValid($kode)) {
                return $this->hasil($kode, 'cadangan-belanja', 'Kode belanja '.$belanja);
            }
        }

        // 7. Cadangan global.
        $kode = $this->bersihkanKode($this->aturan['cadangan'] ?? '5.1.02.01.01.0012');

        return $this->hasil($kode ?? '5.1.02.01.01.0012', 'cadangan', 'Cadangan global');
    }

    /**
     * Seluruh kode rekening yang dipakai aturan (untuk uji validitas).
     *
     * @return array<int, string>
     */
    public function kodeYangDipakaiAturan(): array
    {
        $kode = [];
        foreach ($this->aturan['aturan'] ?? [] as $aturan) {
            if (! empty($aturan['default'])) {
                $kode[] = $aturan['default'];
            }
            foreach ($aturan['kunci'] ?? [] as $nilai) {
                $kode[] = $nilai;
            }
        }
        foreach ($this->aturan['kategori'] ?? [] as $nilai) {
            $kode[] = $nilai;
        }
        foreach ($this->aturan['keluarga'] ?? [] as $nilai) {
            $kode[] = $nilai;
        }
        foreach ($this->aturan['cadangan_belanja'] ?? [] as $nilai) {
            $kode[] = $nilai;
        }
        if (! empty($this->aturan['cadangan'])) {
            $kode[] = $this->aturan['cadangan'];
        }

        return array_values(array_unique(array_map('strval', $kode)));
    }

    /**
     * @return array{rekening: string, dasar: string, aturan: string}|null
     */
    private function jalankanAturan(string $nama, bool $lewatiRentanDeskripsi = false): ?array
    {
        $rentan = $lewatiRentanDeskripsi
            ? array_flip($this->aturan['aturan_kepala_saja'] ?? [])
            : [];

        foreach ($this->aturan['aturan'] ?? [] as $aturan) {
            if (isset($rentan[(string) ($aturan['id'] ?? '')])) {
                continue;
            }
            if (! $this->syaratTerpenuhi($nama, $aturan)) {
                continue;
            }

            if ($hasil = $this->nilaiAturan($nama, $aturan, 'kata kunci')) {
                return $hasil;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $aturan
     */
    private function syaratTerpenuhi(string $nama, array $aturan): bool
    {
        $wajib = $aturan['wajib'] ?? [];
        if ($wajib === []) {
            return true;
        }

        foreach ($wajib as $kunci) {
            if ($this->cocok($nama, $kunci)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $aturan
     * @return array{rekening: string, dasar: string, aturan: string}|null
     */
    private function nilaiAturan(string $nama, array $aturan, string $dasar): ?array
    {
        foreach ($aturan['kunci'] ?? [] as $kunci => $kode) {
            if ($this->cocok($nama, (string) $kunci) && $this->kodeValid((string) $kode)) {
                return $this->hasil((string) $kode, $dasar, ($aturan['ket'] ?? $aturan['id']).' — kata kunci "'.$kunci.'"');
            }
        }

        $default = $aturan['default'] ?? null;
        if ($default !== null && $this->kodeValid((string) $default)) {
            return $this->hasil((string) $default, $dasar, $aturan['ket'] ?? (string) ($aturan['id'] ?? 'aturan'));
        }

        return null;
    }

    private function cariKeluarga(string $kodeAsal): ?string
    {
        $prefix = $this->prefixKeluarga($kodeAsal);
        $keluarga = $this->aturan['keluarga'] ?? [];

        // Prefix terpanjang menang (5.2.02.05.x lebih spesifik dari 5.2.02.x).
        $kandidat = [];
        foreach ($keluarga as $awalan => $kode) {
            if (str_starts_with($kodeAsal, (string) $awalan)) {
                $kandidat[(string) $awalan] = (string) $kode;
            }
        }
        if ($kandidat === []) {
            return null;
        }
        uksort($kandidat, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($kandidat as $kode) {
            if ($this->kodeValid($kode)) {
                return $kode;
            }
        }

        return null;
    }

    private function prefixKeluarga(string $kodeAsal): string
    {
        $bagian = explode('.', $kodeAsal);
        if (count($bagian) < 4) {
            return $kodeAsal;
        }

        return implode('.', array_slice($bagian, 0, 4));
    }

    private function cocok(string $nama, string $kunci): bool
    {
        $kunci = $this->normalisasi($kunci);
        if ($kunci === '') {
            return false;
        }

        $pola = preg_quote($kunci, '/');

        // Kata kunci panjang (>= 9 huruf): cukup awal kata yang utuh, akhiran
        // bahasa Indonesia (…AN, …NYA, …KAN) tetap diterima sehingga
        // "PERENCANA" juga menangkap "PERENCANAAN".
        if (strlen($kunci) >= 9) {
            return (bool) preg_match('/(?<![A-Z0-9])'.$pola.'/', $nama);
        }

        // Kata pendek wajib cocok sebagai kata utuh supaya "LEM" tidak
        // menangkap "LEMBAGA" dan "TARI" tidak menangkap "PERTANIAN".
        return (bool) preg_match('/(?<![A-Z0-9])'.$pola.'(?![A-Z0-9])/', $nama);
    }

    /**
     * Bagian nama pokok sebelum pemisah "-" (nama barang vs deskripsi).
     */
    private function kepala(string $nama): string
    {
        $pos = strpos($nama, '-');
        if ($pos === false || $pos < 3) {
            return $nama;
        }

        return trim(substr($nama, 0, $pos));
    }

    private function normalisasi(string $teks): string
    {
        $teks = str_replace(["\xc2\xa0", "\r", "\n", "\t"], ' ', $teks);
        $teks = mb_strtoupper(trim($teks));

        return trim((string) preg_replace('/\s+/', ' ', $teks));
    }

    private function bersihkanKode(?string $kode): ?string
    {
        if ($kode === null) {
            return null;
        }
        $kode = trim(str_replace('.....', '', $kode));

        return $kode === '' ? null : $kode;
    }

    private function kodeValid(string $kode): bool
    {
        $this->muatMaster();

        return isset($this->adaDiMaster[$kode]);
    }

    private function muatMaster(): void
    {
        if ($this->kodeMaster !== null) {
            return;
        }

        $this->kodeMaster = MasterKodeRekening::query()->pluck('kode')->all();
        $this->adaDiMaster = array_fill_keys($this->kodeMaster, true);
    }

    /**
     * @return array{rekening: string, dasar: string, aturan: string}
     */
    private function hasil(string $kode, string $dasar, string $aturan): array
    {
        return ['rekening' => $kode, 'dasar' => $dasar, 'aturan' => $aturan];
    }
}
