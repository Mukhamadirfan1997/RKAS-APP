<?php

namespace App\Services;

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

    public function __construct(?TahunAnggaran $tahun = null)
    {
        $tahun = $tahun ?? TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        $this->paguTotal = (float) ($tahun->pagu_total ?? 0);
        $this->items = RkasItem::with(['program', 'kodeRekening', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahun->id)
            ->get()
            ->all();

        foreach ($this->items as $item) {
            $kategori = $item->kodeRekening->kategori_belanja ?? '';
            $programKode = $item->program->kode ?? '';
            $uraian = strtolower($item->uraian ?? '');

            $jumlah = (float) $item->jumlah;

            $isHonor = in_array($programKode, config('juknis.honor.kode_program'))
                || in_array($kategori, config('juknis.honor.kategori_rekening'));

            $isBuku = in_array($programKode, config('juknis.buku.kode_program'))
                || ($kategori === 'MODAL' && str_contains($uraian, 'buku'));

            $isSarpras = in_array($programKode, config('juknis.sarpras.kode_program'));

            if ($isHonor) {
                $this->honorTotal += $jumlah;
            }

            if ($isBuku) {
                $this->bukuTotal += $jumlah;
            }

            if ($isSarpras) {
                $this->sarprasTotal += $jumlah;
            }
        }

        $this->tahap1Total = (float) RkasItemBulan::whereHas('item', function ($q) use ($tahun) {
            $q->where('tahun_anggaran_id', $tahun->id);
        })->whereBetween('bulan', [1, 6])->sum('jumlah');
    }

    public function pctOfPagu(float $value): float
    {
        return $this->paguTotal > 0 ? round($value / $this->paguTotal * 100, 2) : 0;
    }

    public function honor(): array
    {
        $batas = (float) config('juknis.honor.batas_persen');
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
        $batas = (float) config('juknis.tahap1.batas_persen');
        $pct = $this->pctOfPagu($this->tahap1Total);

        return [
            'total' => $this->tahap1Total,
            'persen' => $pct,
            'batas_persen' => $batas,
            'batas_nominal' => $this->paguTotal * $batas / 100,
            'status' => $pct >= $batas ? 'sesuai' : 'kurang',
            'sisa' => ($this->paguTotal * $batas / 100) - $this->tahap1Total,
        ];
    }

    public function summary(): array
    {
        $total = $this->honorTotal + $this->bukuTotal + $this->sarprasTotal;

        return [
            'pagu_total' => $this->paguTotal,
            'sudah_dianggarkan' => (float) collect($this->items)->sum('jumlah'),
            'honor' => $this->honor(),
            'buku' => $this->buku(),
            'sarpras' => $this->sarpras(),
            'tahap1' => $this->tahap1(),
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
}
