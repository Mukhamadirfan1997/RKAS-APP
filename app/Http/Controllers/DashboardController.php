<?php

namespace App\Http\Controllers;

use App\Models\MasterKodeRekening;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use App\Services\JuknisValidator;

class DashboardController extends Controller
{
    private function resolveTahun(\Illuminate\Http\Request $request): TahunAnggaran
    {
        if ($request->filled('tahun')) {
            $ta = TahunAnggaran::where('tahun', (int) $request->tahun)->first();
            if ($ta) return $ta;
        }
        return TahunAnggaran::where('is_active', true)->first()
            ?? TahunAnggaran::where('tahun', 2026)->first()
            ?? TahunAnggaran::first()
            ?? new TahunAnggaran(['tahun'=>2026]);
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);

        $validator = new JuknisValidator($tahunAnggaran);
        $summary = $validator->summary();

        // Grafik proporsi per bulan
        $bulanData = [];
        for ($b = 1; $b <= 12; $b++) {
            $bulanData[$b] = (float) RkasItemBulan::whereHas('item', fn ($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))
                ->where('bulan', $b)->sum('jumlah');
        }

        // Proporsi 9 Jenis Belanja resmi ARKAS + Tanpa Klasifikasi
        $jenisBelanjas = \App\Models\JenisBelanja::orderBy('nama')->get();
        $proporsiJenis = [];
        foreach ($jenisBelanjas as $jb) {
            $proporsiJenis[$jb->nama] = (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
                ->whereHas('kodeRekening', fn ($q) => $q->where('jenis_belanja_id', $jb->id))
                ->sum('jumlah');
        }
        // Hapus yang 0 agar grafik tidak penuh kategori kosong, tapi tetap hitung Tanpa Klasifikasi bila ada
        $proporsiJenis = array_filter($proporsiJenis, fn($v) => $v > 0);
        $tanpa = (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
            ->where(function ($q) {
                $q->whereHas('kodeRekening', fn ($qq) => $qq->whereNull('jenis_belanja_id'))
                    ->orWhereDoesntHave('kodeRekening');
            })
            ->sum('jumlah');
        if ($tanpa > 0) $proporsiJenis['Tanpa Klasifikasi'] = $tanpa;

        // Total item & capaian
        $totalItem = RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->count();
        $daftarTahun = TahunAnggaran::orderBy('tahun','desc')->get();

        return view('dashboard.index', compact(
            'sekolah',
            'tahunAnggaran',
            'daftarTahun',
            'summary',
            'bulanData',
            'proporsiJenis',
            'totalItem'
        ));
    }
}
