<?php

namespace App\Http\Controllers;

use App\Models\TahunAnggaran;
use App\Services\KesiapanRkasService;
use Illuminate\Http\Request;

/**
 * Halaman "Kesiapan RKAS" — daftar periksa sebelum pengesahan.
 *
 * Setiap temuan bisa diklik langsung ke halaman perbaikannya (pagu, monitoring
 * JUKNIS, atau baris item tertentu di lembar kerja) sehingga operator tidak
 * menebak-nebak apa yang kurang.
 */
class KesiapanController extends Controller
{
    private function resolveTahun(Request $request): TahunAnggaran
    {
        if ($request->filled('tahun')) {
            $ta = TahunAnggaran::where('tahun', (int) $request->tahun)->first();
            if ($ta) {
                return $ta;
            }
        }

        return TahunAnggaran::where('is_active', true)->first()
            ?? TahunAnggaran::orderBy('tahun', 'desc')->first()
            ?? new TahunAnggaran(['tahun' => 2026, 'pagu_total' => 0, 'pagu_tahap1' => 0, 'pagu_tahap2' => 0]);
    }

    public function index(Request $request)
    {
        $tahunAnggaran = $this->resolveTahun($request);
        $kesiapan = (new KesiapanRkasService($tahunAnggaran))->evaluasi();
        $daftarTahun = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $statusRkas = $tahunAnggaran->status_pengesahan ?? 'Draft';

        return view('kesiapan.index', compact('tahunAnggaran', 'kesiapan', 'daftarTahun', 'statusRkas'));
    }
}
