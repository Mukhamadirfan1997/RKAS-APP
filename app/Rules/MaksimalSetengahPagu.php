<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Pagu per tahap tidak boleh melebihi 50% dari Pagu Total 1 tahun.
 *
 * Dasar: dana BOSP disalurkan dua tahap (Tahap I Januari–Juni, Tahap II
 * Juli–Desember). Tiap tahap paling banyak separuh dari pagu satu tahun,
 * sehingga Tahap I + Tahap II tidak mungkin melampaui pagu.
 *
 * Kalau nilai `pagu_total` pada request tidak valid (bukan angka), aturan ini
 * tidak menilai — biar pesan error `pagu_total` yang muncul, tidak dobel.
 */
class MaksimalSetengahPagu implements ValidationRule
{
    /**
     * @param  float|null  $paguTotal  Null = pagu_total tidak valid, aturan dilewati.
     * @param  string  $tahap  Label tahap untuk pesan error ('I' / 'II').
     */
    public function __construct(
        private ?float $paguTotal,
        private string $tahap = 'I',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->paguTotal === null || ! is_numeric($value)) {
            return;
        }

        $batas = $this->paguTotal / 2;

        // Toleransi 0,5 rupiah agar pembulatan float tidak salah menolak.
        if ((float) $value > $batas + 0.5) {
            $fail(sprintf(
                'Pagu Tahap %s (Rp %s) tidak boleh melebihi 50%% dari Pagu Total 1 tahun (maksimal Rp %s).',
                $this->tahap,
                number_format((float) $value, 0, ',', '.'),
                number_format($batas, 0, ',', '.')
            ));
        }
    }
}
