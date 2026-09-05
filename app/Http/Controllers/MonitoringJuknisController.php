<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\KategoriJuknis;
use App\Models\KodeRekeningKategoriJuknis;
use App\Models\MasterKodeRekening;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Services\JuknisValidator;
use App\Services\RkaGelondonganService;
use Illuminate\Http\Request;

class MonitoringJuknisController extends Controller
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
            \Illuminate\Support\Facades\DB::transaction(function () use ($latest) {
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
        $tahunAnggaran = $this->resolveTahun($request);
        $validator = new JuknisValidator($tahunAnggaran);
        $summary = $validator->summary();

        // Kategori JUKNIS konfigurasi pengguna
        $kategoriList = KategoriJuknis::with('rekenings')->get();

        // Semua rekening untuk checklist centang (group by Jenis Belanja)
        $allRekenings = MasterKodeRekening::with('jenisBelanja')->orderBy('kode')->get();
        $rekeningByJenis = $allRekenings->groupBy(fn ($r) => $r->jenisBelanja->nama ?? 'Tanpa Klasifikasi');

        // Daftar item yang belum termapping ke kategori JUKNIS
        $mappedRekeningIds = KodeRekeningKategoriJuknis::pluck('master_kode_rekening_id');
        $unmapped = RkasItem::with(['program', 'kodeRekening'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->where(function ($q) use ($mappedRekeningIds) {
                $q->whereNull('master_kode_rekening_id')
                    ->orWhereNotIn('master_kode_rekening_id', $mappedRekeningIds);
            })
            ->get();

        $filter = $request->input('filter', 'all');
        $daftarTahun = TahunAnggaran::orderBy('tahun', 'desc')->get();

        // Cek RKA Gelondongan — 3 kategori Dinas (reuse service)
        $gelondongan = RkaGelondonganService::calculate($tahunAnggaran);
        $gelondonganLabels = config('rka_gelondongan.labels', [
            'barang_jasa' => 'Belanja Barang dan Jasa',
            'modal_mesin' => 'Modal Mesin',
            'modal_aset_lainnya' => 'Modal Aset Tetap Lainnya (termasuk modal buku)',
            'jumlah' => 'Jumlah',
        ]);

        return view('monitoring.index', compact(
            'tahunAnggaran',
            'daftarTahun',
            'summary',
            'kategoriList',
            'allRekenings',
            'rekeningByJenis',
            'unmapped',
            'filter',
            'gelondongan',
            'gelondonganLabels'
        ));
    }

    public function mapping(Request $request)
    {
        $request->validate([
            'kategori_juknis_id' => 'required|exists:kategori_juknis,id',
            'kode_rekening' => 'array',
            'kode_rekening.*' => 'exists:master_kode_rekening,id',
        ]);

        $kategori = KategoriJuknis::findOrFail($request->kategori_juknis_id);
        $kategori->rekenings()->sync($request->kode_rekening ?? []);

        return redirect()->route('monitoring.juknis')->with('success', 'Pemetaan rekening ke kategori JUKNIS berhasil disimpan.');
    }
}
