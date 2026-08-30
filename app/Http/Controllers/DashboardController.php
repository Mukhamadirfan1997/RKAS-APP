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
    public function index()
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        $validator = new JuknisValidator($tahunAnggaran);
        $summary = $validator->summary();

        // Grafik proporsi per bulan
        $bulanData = [];
        for ($b = 1; $b <= 12; $b++) {
            $bulanData[$b] = (float) RkasItemBulan::whereHas('item', fn ($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))
                ->where('bulan', $b)->sum('jumlah');
        }

        // Proporsi jenis belanja (BARJAS / MODAL / HONOR)
        $jenisBelanja = MasterKodeRekening::all()->pluck('kategori_belanja')->unique();
        $proporsiJenis = [];
        foreach ($jenisBelanja as $jenis) {
            $proporsiJenis[$jenis] = (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
                ->whereHas('kodeRekening', fn ($q) => $q->where('kategori_belanja', $jenis))
                ->sum('jumlah');
        }

        // Total item & capaian
        $totalItem = RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->count();

        return view('dashboard.index', compact(
            'sekolah',
            'tahunAnggaran',
            'summary',
            'bulanData',
            'proporsiJenis',
            'totalItem'
        ));
    }
}
