<?php

namespace App\Services;

use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;

class JuknisValidator
{
    protected float $paguTotal = 0;

    protected array $items = [];

    protected float $honorTotal = 0;

    protected float $bukuTotal = 0;

    protected float $sarprasTotal = 0;

    protected float $tahap1Total = 0;

    protected float $tahap2Total = 0;

    protected string $statusSekolah = 'negeri';

    public function __construct(?TahunAnggaran $tahun = null)
    {
        $tahun = $tahun ?? TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        $this->statusSekolah = strtolower(PengaturanSekolah::value('status_sekolah') ?? 'negeri');
        $this->statusSekolah = in_array($this->statusSekolah, ['negeri', 'swasta'], true) ? $this->statusSekolah : 'negeri';

        $this->paguTotal = (float) ($tahun->pagu_total ?? 0);
        $this->items = RkasItem::with(['program', 'kodeRekening', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahun->id)
            ->get()
            ->all();

        foreach ($this->items as $item) {
            $jenisBelanja = $item->kodeRekening->jenisBelanja->nama ?? '';
            $kodeRekening = $item->kodeRekening->kode ?? '';
            $programKode = $item->program->kode ?? '';
            $uraian = strtolower($item->uraian ?? '');

            $jumlah = (float) $item->jumlah;

            // Kombinasi presisi ARKAS: program AND (jenis belanja / prefix rekening / keyword)
            $kategori = $this->klasifikasi($programKode, $jenisBelanja, $kodeRekening, $uraian);

            if ($kategori === 'honor') {
                $this->honorTotal += $jumlah;
            } elseif ($kategori === 'buku') {
                $this->bukuTotal += $jumlah;
            } elseif ($kategori === 'sarpras') {
                $this->sarprasTotal += $jumlah;
            }
        }

        $this->tahap1Total = (float) RkasItemBulan::whereHas('item', function ($q) use ($tahun) {
            $q->where('tahun_anggaran_id', $tahun->id);
        })->whereBetween('bulan', [1, 6])->sum('jumlah');

        $this->tahap2Total = (float) RkasItemBulan::whereHas('item', function ($q) use ($tahun) {
            $q->where('tahun_anggaran_id', $tahun->id);
        })->whereBetween('bulan', [7, 12])->sum('jumlah');
    }

    public function pctOfPagu(float $value): float
    {
        return $this->paguTotal > 0 ? round($value / $this->paguTotal * 100, 2) : 0;
    }

    public function statusSekolah(): string
    {
        return $this->statusSekolah;
    }

    public function honorBatasPersen(): float
    {
        // Negeri maksimal 20%, swasta maksimal 40% (Permendikdasmen No. 8/2026).
        if ($this->statusSekolah === 'swasta') {
            return (float) (config('juknis.honor.batas_persen_swasta') ?? config('juknis.honor.batas_persen'));
        }

        return (float) config('juknis.honor.batas_persen');
    }

    public function honor(): array
    {
        $batas = $this->honorBatasPersen();
        $pct = $this->pctOfPagu($this->honorTotal);

        return [
            'total' => $this->honorTotal,
            'persen' => $pct,
            'batas_persen' => $batas,
            'batas_nominal' => $this->paguTotal * $batas / 100,
            'status' => $this->statusFor($this->honorTotal, $this->paguTotal * $batas / 100, 'maksimal'),
            'sisa' => ($this->paguTotal * $batas / 100) - $this->honorTotal,
        ];
    }

    public function buku(): array
    {
        $batas = (float) config('juknis.buku.batas_persen');
        $pct = $this->pctOfPagu($this->bukuTotal);

        return [
            'total' => $this->bukuTotal,
            'persen' => $pct,
            'batas_persen' => $batas,
            'batas_nominal' => $this->paguTotal * $batas / 100,
            'status' => $this->statusFor($this->bukuTotal, $this->paguTotal * $batas / 100, 'minimal'),
            'sisa' => ($this->paguTotal * $batas / 100) - $this->bukuTotal,
        ];
    }

    public function sarpras(): array
    {
        $batas = (float) config('juknis.sarpras.batas_persen');
        $pct = $this->pctOfPagu($this->sarprasTotal);

        return [
            'total' => $this->sarprasTotal,
            'persen' => $pct,
            'batas_persen' => $batas,
            'batas_nominal' => $this->paguTotal * $batas / 100,
            'status' => $this->statusFor($this->sarprasTotal, $this->paguTotal * $batas / 100, 'maksimal'),
            'sisa' => ($this->paguTotal * $batas / 100) - $this->sarprasTotal,
        ];
    }

    public function tahap1(): array
    {
        $min = (float) config('juknis.tahap1.batas_persen');
        $maks = (float) (config('juknis.tahap1.batas_maks_persen') ?? 50);
        $pct = $this->pctOfPagu($this->tahap1Total);
        $batasMin = $this->paguTotal * $min / 100;
        $batasMaks = $this->paguTotal * $maks / 100;

        // Dua sisi: JUKNIS menuntut minimal 50%, penyaluran hanya menyediakan
        // 50% kas di Tahap I — jadi rencana Tahap I tidak boleh lebih dari 50%.
        $status = 'sesuai';
        if ($this->tahap1Total < $batasMin - 0.5) {
            $status = 'kurang';
        }
        if ($batasMaks > 0 && $this->tahap1Total > $batasMaks + 0.5) {
            $status = 'melebihi';
        }

        return [
            'total' => $this->tahap1Total,
            'persen' => $pct,
            'batas_persen' => $min,
            'batas_nominal' => $batasMin,
            'batas_maks_persen' => $maks,
            'batas_maks_nominal' => $batasMaks,
            'status' => $status,
            'sisa' => $batasMin - $this->tahap1Total,
            'kurang' => max(0, $batasMin - $this->tahap1Total),
            'kelebihan' => max(0, $this->tahap1Total - $batasMaks),
        ];
    }

    /**
     * Alokasi Tahap II (Juli–Desember). Hanya punya batas atas: jatah penyaluran
     * Tahap II maksimal 50% dari pagu 1 tahun.
     */
    public function tahap2(): array
    {
        $maks = (float) (config('juknis.tahap2.batas_maks_persen') ?? 50);
        $pct = $this->pctOfPagu($this->tahap2Total);
        $batasMaks = $this->paguTotal * $maks / 100;
        $melebihi = $batasMaks > 0 && $this->tahap2Total > $batasMaks + 0.5;

        return [
            'total' => $this->tahap2Total,
            'persen' => $pct,
            'batas_maks_persen' => $maks,
            'batas_maks_nominal' => $batasMaks,
            'status' => $melebihi ? 'melebihi' : 'sesuai',
            'sisa' => $batasMaks - $this->tahap2Total,
            'kelebihan' => max(0, $this->tahap2Total - $batasMaks),
        ];
    }

    public function summary(): array
    {
        $total = $this->honorTotal + $this->bukuTotal + $this->sarprasTotal;

        return [
            'pagu_total' => $this->paguTotal,
            'sudah_dianggarkan' => (float) collect($this->items)->sum('jumlah'),
            'status_sekolah' => $this->statusSekolah,
            'honor' => $this->honor(),
            'buku' => $this->buku(),
            'sarpras' => $this->sarpras(),
            'tahap1' => $this->tahap1(),
            'tahap2' => $this->tahap2(),
            'total_kategori' => $total,
        ];
    }

    protected function statusFor(float $value, float $batas, string $arah): string
    {
        if ($arah === 'maksimal') {
            return $value <= $batas ? 'sesuai' : 'melebihi';
        }

        return $value >= $batas ? 'sesuai' : 'kurang';
    }

    /**
     * Klasifikasi satu item ke komponen JUKNIS.
     *
     * Presisi ARKAS: kombinasi AND antara kode program (kegiatan) dengan
     * (jenis belanja ATAU prefix kode rekening ATAU kata kunci uraian).
     * Honor hanya diakui pada kegiatan 07.12.*; honor di kegiatan lain
     * (mis. ekstrakurikuler) tidak masuk persentase honor.
     * Prioritas eksklusif: honor > buku > sarpras -> null (tanpa komponen).
     *
     * @return string|null 'honor' | 'buku' | 'sarpras' | null
     */
    protected function klasifikasi(string $programKode, string $jenisBelanja, string $kodeRekening, string $uraian): ?string
    {
        if ($this->kombinasi('honor', $programKode, $jenisBelanja, $kodeRekening, $uraian)) {
            return 'honor';
        }

        if ($this->kombinasi('buku', $programKode, $jenisBelanja, $kodeRekening, $uraian)) {
            return 'buku';
        }

        if ($this->kombinasi('sarpras', $programKode, $jenisBelanja, $kodeRekening, $uraian)) {
            return 'sarpras';
        }

        return null;
    }

    /**
     * Cek kombinasi AND program + (jenis belanja | prefix rekening | keyword).
     */
    protected function kombinasi(string $key, string $programKode, string $jenisBelanja, string $kodeRekening, string $uraian): bool
    {
        $cfg = config("juknis.$key");

        if (! in_array($programKode, $cfg['kode_program'] ?? [], true)) {
            return false;
        }

        $jenisCocok = in_array($jenisBelanja, $cfg['jenis_belanja'] ?? [], true);

        $prefix = $cfg['rekening'] ?? [];
        $rekeningCocok = false;
        foreach ($prefix as $p) {
            if ($p !== '' && str_starts_with($kodeRekening, $p)) {
                $rekeningCocok = true;
                break;
            }
        }

        $keywordCocok = $this->uraianHits($uraian, $cfg['keywords'] ?? []);

        return $jenisCocok || $rekeningCocok || $keywordCocok;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    protected function uraianHits(string $uraian, array $keywords): bool
    {
        foreach ($keywords as $k) {
            if (str_contains($uraian, $k)) {
                return true;
            }
        }

        return false;
    }
}
