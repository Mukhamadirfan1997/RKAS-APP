<?php

namespace App\Services;

use App\Models\JenisBelanja;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use Illuminate\Support\Facades\DB;

class RkaGelondonganService
{
    /**
     * Hitung 3 kategori Dinas untuk tahun anggaran tertentu.
     * Reuse pola whereHas(kodeRekening.jenisBelanja) seperti DashboardController.
     * Menggunakan jumlah_koreksi (jumlah + koreksi), bukan jumlah mentah.
     *
     * @return array{barang_jasa: float, modal_mesin: float, modal_aset_lainnya: float, jumlah: float, pagu_total: float, selisih: float, is_sesuai: bool, detail: array, rows: array, targets: array, realisasis: array, selisihs: array}
     */
    public static function calculate(TahunAnggaran $tahunAnggaran): array
    {
        $mapping = config('rka_gelondongan.mapping', [
            'barang_jasa' => ['Belanja Barang', 'Belanja Barang Persediaan', 'Belanja Cetak', 'Belanja Jasa', 'Belanja Jasa Pemeliharaan', 'Belanja Perjalanan Dinas'],
            'modal_mesin' => ['Belanja Modal Peralatan & Mesin'],
            'modal_aset_lainnya' => ['Belanja Modal Aset Tetap Lainnya', 'Belanja Modal Buku'],
        ]);
        $labels = config('rka_gelondongan.labels', [
            'barang_jasa' => 'Belanja Barang dan Jasa',
            'modal_mesin' => 'Modal Mesin',
            'modal_aset_lainnya' => 'Modal Aset Tetap Lainnya (termasuk modal buku)',
            'jumlah' => 'Jumlah',
        ]);

        // Helper: sum jumlah_koreksi untuk daftar nama jenis_belanja — pakai DB raw SUM(jumlah + koreksi) agar presisi & efisien
        $sumForNames = function (array $names) use ($tahunAnggaran): float {
            if (empty($names)) {
                return 0.0;
            }
            // Resolve jenis_belanja.id via nama verbatim — jika jenis tidak ada, id tidak ditemukan -> sum 0
            $ids = JenisBelanja::whereIn('nama', $names)->pluck('id');
            if ($ids->isEmpty()) {
                return 0.0;
            }
            return (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
                ->whereHas('kodeRekening', function ($q) use ($ids) {
                    $q->whereIn('jenis_belanja_id', $ids);
                })
                ->selectRaw('COALESCE(SUM(jumlah + koreksi), 0) as agg')
                ->value('agg');
        };

        $barangJasa = $sumForNames($mapping['barang_jasa'] ?? []);
        $modalMesin = $sumForNames($mapping['modal_mesin'] ?? []);
        $modalAset = $sumForNames($mapping['modal_aset_lainnya'] ?? []);
        $jumlah = round($barangJasa + $modalMesin + $modalAset, 2);

        $paguTotal = (float) ($tahunAnggaran->pagu_total ?? 0);
        $selisih = round($jumlah - $paguTotal, 2);
        $isSesuai = abs($selisih) < 0.5; // toleransi 50 sen

        // Target dari tahun_anggaran (nullable)
        $targetBarjas = $tahunAnggaran->target_barjas !== null ? (float) $tahunAnggaran->target_barjas : null;
        $targetMesin = $tahunAnggaran->target_modal_mesin !== null ? (float) $tahunAnggaran->target_modal_mesin : null;
        $targetAset = $tahunAnggaran->target_modal_aset !== null ? (float) $tahunAnggaran->target_modal_aset : null;
        $targets = [
            'barang_jasa' => $targetBarjas,
            'modal_mesin' => $targetMesin,
            'modal_aset_lainnya' => $targetAset,
        ];
        $realisasis = [
            'barang_jasa' => $barangJasa,
            'modal_mesin' => $modalMesin,
            'modal_aset_lainnya' => $modalAset,
        ];
        // Selisih per kategori = Target - Realisasi (toleransi ±1000)
        $selisihs = [];
        $statusPerRow = [];
        foreach (['barang_jasa', 'modal_mesin', 'modal_aset_lainnya'] as $k) {
            $t = $targets[$k];
            $r = $realisasis[$k];
            if ($t === null) {
                $selisihs[$k] = null;
                $statusPerRow[$k] = 'belum_diisi';
            } else {
                $s = round($t - $r, 2);
                $selisihs[$k] = $s;
                if (abs($s) < 1000.5) {
                    $statusPerRow[$k] = 'sesuai';
                } elseif ($r < $t) {
                    $statusPerRow[$k] = 'kurang';
                } else {
                    $statusPerRow[$k] = 'lebih';
                }
            }
        }
        // Jumlah target (sum target yang tidak null) vs pagu
        $jumlahTarget = null;
        if ($targetBarjas !== null || $targetMesin !== null || $targetAset !== null) {
            $jumlahTarget = round((float) ($targetBarjas ?? 0) + (float) ($targetMesin ?? 0) + (float) ($targetAset ?? 0), 2);
        }
        $selisihTargetPagu = $jumlahTarget !== null ? round($jumlahTarget - $paguTotal, 2) : null;

        // Detail per jenis_belanja untuk debug (optional)
        $detail = [];
        foreach ($mapping as $kategori => $names) {
            $detail[$kategori] = [
                'names' => $names,
                'total' => $kategori === 'barang_jasa' ? $barangJasa : ($kategori === 'modal_mesin' ? $modalMesin : $modalAset),
            ];
        }

        // Rows untuk tabel: Kategori | Target | Realisasi | Selisih
        $rows = [];
        foreach (['barang_jasa', 'modal_mesin', 'modal_aset_lainnya'] as $k) {
            $rows[] = [
                'key' => $k,
                'label' => $labels[$k] ?? $k,
                'target' => $targets[$k],
                'realisasi' => $realisasis[$k],
                'selisih' => $selisihs[$k],
                'status' => $statusPerRow[$k],
            ];
        }

        return [
            'barang_jasa' => $barangJasa,
            'modal_mesin' => $modalMesin,
            'modal_aset_lainnya' => $modalAset,
            'jumlah' => $jumlah,
            'pagu_total' => $paguTotal,
            'selisih' => $selisih,
            'is_sesuai' => $isSesuai,
            'detail' => $detail,
            'targets' => $targets,
            'realisasis' => $realisasis,
            'selisihs' => $selisihs,
            'rows' => $rows,
            'jumlah_target' => $jumlahTarget,
            'selisih_target_pagu' => $selisihTargetPagu,
            'status_per_row' => $statusPerRow,
        ];
    }

    /**
     * Grand total jumlah_koreksi semua item (untuk validasi jumlah == grand).
     */
    public static function grandKoreksi(TahunAnggaran $tahunAnggaran): float
    {
        return (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
            ->selectRaw('COALESCE(SUM(jumlah + koreksi), 0) as agg')
            ->value('agg');
    }
}
