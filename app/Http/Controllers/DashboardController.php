<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\JenisBelanja;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use App\Services\JuknisValidator;
use App\Services\RkaGelondonganService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function resolveTahun(Request $request): TahunAnggaran
    {
        if ($request->filled('tahun')) {
            $ta = TahunAnggaran::where('tahun', (int) $request->tahun)->first();
            if ($ta) {
                return $ta;
            }
        }

        $active = TahunAnggaran::where('is_active', true)->first();
        if ($active) {
            return $active;
        }

        $latest = TahunAnggaran::orderBy('tahun', 'desc')->first();
        if ($latest) {
            DB::transaction(function () use ($latest) {
                TahunAnggaran::where('id', '!=', $latest->id)->update(['is_active' => false]);
                $latest->update(['is_active' => true]);
            });
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'tahun.auto-fix',
                'auditable_type' => TahunAnggaran::class,
                'auditable_id' => $latest->id,
                'description' => "Auto-fix: tidak ada TA aktif, aktifkan TA {$latest->tahun} otomatis (tahun terbaru)",
                'old_values' => ['is_active' => false],
                'new_values' => ['tahun' => $latest->tahun, 'is_active' => true],
            ]);

            return $latest->fresh();
        }

        return TahunAnggaran::where('tahun', 2026)->first()
            ?? TahunAnggaran::first()
            ?? new TahunAnggaran(['tahun' => 2026]);
    }

    public function index(Request $request)
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
        $jenisBelanjas = JenisBelanja::orderBy('nama')->get();
        $proporsiJenis = [];
        foreach ($jenisBelanjas as $jb) {
            $proporsiJenis[$jb->nama] = (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
                ->whereHas('kodeRekening', fn ($q) => $q->where('jenis_belanja_id', $jb->id))
                ->sum('jumlah');
        }
        // Hapus yang 0 agar grafik tidak penuh kategori kosong, tapi tetap hitung Tanpa Klasifikasi bila ada
        $proporsiJenis = array_filter($proporsiJenis, fn ($v) => $v > 0);
        $tanpa = (float) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)
            ->where(function ($q) {
                $q->whereHas('kodeRekening', fn ($qq) => $qq->whereNull('jenis_belanja_id'))
                    ->orWhereDoesntHave('kodeRekening');
            })
            ->sum('jumlah');
        if ($tanpa > 0) {
            $proporsiJenis['Tanpa Klasifikasi'] = $tanpa;
        }

        // Total item & capaian
        $totalItem = RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->count();
        $daftarTahun = TahunAnggaran::orderBy('tahun', 'desc')->get();

        // Ringkasan RKA Gelondongan — reuse service yang sama dengan MonitoringJuknis
        $gelondongan = RkaGelondonganService::calculate($tahunAnggaran);

        // Kesiapan RKAS — 5 item lama + 1 kondisional untuk gelondongan
        $checks = [
            ['label' => 'Honor tidak melebihi batas', 'ok' => $summary['honor']['status'] === 'sesuai'],
            ['label' => 'Anggaran buku memenuhi minimum', 'ok' => $summary['buku']['status'] === 'sesuai'],
            ['label' => 'Sarpras tidak melebihi batas', 'ok' => $summary['sarpras']['status'] === 'sesuai'],
            ['label' => 'Alokasi Tahap I ≥ 50%', 'ok' => $summary['tahap1']['status'] === 'sesuai'],
            ['label' => 'Total anggaran tidak melebihi pagu', 'ok' => $summary['sudah_dianggarkan'] <= $summary['pagu_total']],
        ];
        $hasTarget = $tahunAnggaran->target_barjas !== null || $tahunAnggaran->target_modal_mesin !== null || $tahunAnggaran->target_modal_aset !== null;
        if ($hasTarget) {
            $semuaSesuai = ($gelondongan['status_per_row']['barang_jasa'] ?? 'belum_diisi') === 'sesuai'
                && ($gelondongan['status_per_row']['modal_mesin'] ?? 'belum_diisi') === 'sesuai'
                && ($gelondongan['status_per_row']['modal_aset_lainnya'] ?? 'belum_diisi') === 'sesuai';
            $checks[] = ['label' => '3 kategori RKA sesuai target Dinas', 'ok' => $semuaSesuai];
        }
        $okCount = collect($checks)->where('ok', true)->count();
        $score = count($checks) > 0 ? round($okCount / count($checks) * 100) : 0;

        return view('dashboard.index', compact(
            'sekolah',
            'tahunAnggaran',
            'daftarTahun',
            'summary',
            'bulanData',
            'proporsiJenis',
            'totalItem',
            'gelondongan',
            'checks',
            'okCount',
            'score',
            'hasTarget'
        ));
    }
}
