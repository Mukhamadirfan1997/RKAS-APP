<?php

namespace App\Http\Controllers;

use App\Exports\RkasKertasKerjaExport;
use App\Exports\RkasKertasKerjaGroupedExport;
use App\Models\KodeBarang;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RkasController extends Controller
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
            ?? TahunAnggaran::where('tahun', 2026)->first()
            ?? TahunAnggaran::first()
            ?? new TahunAnggaran(['tahun' => 2026, 'pagu_total' => 0]);
    }

    /**
     * Display the RKAS 2026 Worksheet / Dashboard.
     */
    public function index(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah([
            'nama_sekolah' => 'SD NEGERI TOYANING 1',
        ]);

        $tahunAnggaran = $this->resolveTahun($request);

        $selectedBulan = (int) $request->input('bulan', 1); // 1 = Januari default, 0 = Semua

        $query = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut');

        if ($selectedBulan > 0) {
            $query->whereHas('alokasiBulan', function ($q) use ($selectedBulan) {
                $q->where('bulan', $selectedBulan)->where('volume', '>', 0);
            });
        }

        $items = $query->get();

        // Attach monthly volume and amount if specific month is selected
        if ($selectedBulan > 0) {
            $items->each(function ($item) use ($selectedBulan) {
                $bulanItem = $item->alokasiBulan->firstWhere('bulan', $selectedBulan);
                $item->volume_bulan = $bulanItem ? (float) $bulanItem->volume : 0;
                $item->jumlah_bulan = $bulanItem ? (float) $bulanItem->jumlah : 0;
            });
        }

        // Calculate total summary
        $allItems = RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->with('alokasiBulan')->get();
        $totalSudahDianggarkan = $allItems->sum('jumlah');
        $sisaPagu = ($tahunAnggaran->pagu_total ?? 0) - $totalSudahDianggarkan;
        $totalBulanTerpilih = $selectedBulan > 0
            ? $allItems->sum(fn ($i) => $i->alokasiBulan->where('bulan', $selectedBulan)->sum('jumlah'))
            : $totalSudahDianggarkan;

        // Group items per Kegiatan (ARKAS Kertas Kerja structure) - Urut otomatis berdasarkan kode SNP
        $kegiatanGroups = $items->groupBy('master_program_id')->map(function ($group) use ($selectedBulan) {
            $prog = $group->first()->program;
            $total1Tahun = $group->sum(fn ($i) => $i->jumlah_koreksi);
            $totalBulan = $selectedBulan > 0
                ? $group->sum(fn ($i) => $i->alokasiBulan->where('bulan', $selectedBulan)->sum('jumlah'))
                : $total1Tahun;

            return [
                'id' => $group->first()->master_program_id,
                'kode' => $prog->kode ?? '-',
                'nama' => $prog->nama ?? 'Kegiatan',
                'sub_program' => $prog->sub_program ?? '',
                'items' => $group,
                'jumlah_item' => $group->count(),
                'total_sudah' => $total1Tahun,
                'total_bulan' => $totalBulan,
                'bulan_aktif' => $group->flatMap(fn ($i) => $i->alokasiBulan->where('volume', '>', 0)->pluck('bulan'))->unique()->sort()->values(),
            ];
        })->sortBy('kode', SORT_NATURAL)->values();

        $daftarTahun = TahunAnggaran::orderBy('tahun', 'desc')->get();

        return view('rkas.index', compact(
            'sekolah',
            'tahunAnggaran',
            'daftarTahun',
            'selectedBulan',
            'items',
            'kegiatanGroups',
            'totalSudahDianggarkan',
            'totalBulanTerpilih',
            'sisaPagu'
        ));
    }

    /**
     * Get item detail in JSON format for the modal.
     */
    public function showJson($id)
    {
        $item = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])->findOrFail($id);

        $alokasiMap = [];
        for ($b = 1; $b <= 12; $b++) {
            $bulanItem = $item->alokasiBulan->firstWhere('bulan', $b);
            $alokasiMap[$b] = [
                'volume' => $bulanItem ? (float) $bulanItem->volume : 0,
                'satuan' => $bulanItem ? $bulanItem->satuan : $item->satuan,
                'jumlah' => $bulanItem ? (float) $bulanItem->jumlah : 0,
            ];
        }

        return response()->json([
            'id' => $item->id,
            'kegiatan_id' => $item->master_program_id,
            'kegiatan_text' => $item->program ? "[{$item->program->kode}] {$item->program->nama}" : '',
            'kegiatan_kode' => $item->program->kode ?? '',
            'rekening_id' => $item->master_kode_rekening_id,
            'rekening_text' => $item->kodeRekening ? "[{$item->kodeRekening->kode}] {$item->kodeRekening->nama}" : '',
            'rekening_kode' => $item->kodeRekening->kode ?? '',
            'kode_barang_id' => $item->kode_barang_id,
            'uraian' => $item->uraian,
            'keterangan_kustom' => $item->keterangan_kustom,
            'satuan' => $item->satuan,
            'harga_satuan' => (float) $item->harga_satuan,
            'harga_satuan_arkas' => (float) $item->harga_satuan_arkas,
            'harga_min' => (float) ($item->barang->harga_min ?? 0),
            'harga_max' => (float) ($item->barang->harga_max ?? 0),
            'koreksi' => (float) $item->koreksi,
            'jumlah' => (float) $item->jumlah,
            'alokasi' => $alokasiMap,
        ]);
    }

    /**
     * Helper: cek apakah RKAS masih editable (Draft/Pergeseran boleh, Disahkan terkunci).
     */
    private function assertRkasEditable(Request $request, ?TahunAnggaran $tahunAnggaran)
    {
        if ($tahunAnggaran && $tahunAnggaran->status_pengesahan === 'Disahkan') {
            $msg = 'RKAS TA ini sudah disahkan dan tidak bisa diedit. Buka kembali dari halaman Pengaturan jika perlu revisi.';
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }

            return redirect()->back()->withErrors(['error' => $msg]);
        }

        return null;
    }

    /**
     * Store new RKAS item with 12-month allocation.
     */
    public function store(Request $request)
    {
        $tahunAnggaran = $this->resolveTahun($request);

        if ($guard = $this->assertRkasEditable($request, $tahunAnggaran)) {
            return $guard;
        }

        $validated = $request->validate([
            'master_program_id' => 'required|exists:master_program,id',
            'master_kode_rekening_id' => 'required|exists:master_kode_rekening,id',
            'kode_barang_id' => 'nullable|exists:kode_barang,id',
            'uraian' => 'required|string|max:500',
            'keterangan_kustom' => 'nullable|string|max:255',
            'harga_satuan' => 'required|numeric|min:0',
            'koreksi' => 'nullable|numeric',
            'alokasi' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $totalVolume = 0;
            $totalJumlah = 0;
            $satuanUtama = 'satuan';

            foreach ($validated['alokasi'] as $bulan => $data) {
                $vol = isset($data['volume']) ? (float) $data['volume'] : 0;
                $sat = ! empty($data['satuan']) ? trim($data['satuan']) : $satuanUtama;
                if ($vol > 0) {
                    $totalVolume += $vol;
                    $satuanUtama = $sat;
                }
            }

            $hargaSatuan = (float) $validated['harga_satuan'];
            $totalJumlah = $totalVolume * $hargaSatuan;

            // harga_satuan_arkas diambil dari harga_acuan katalog, bukan input user (fix kontrol SELISIH)
            $hargaSatuanArkas = 0;
            if (! empty($validated['kode_barang_id'])) {
                $barang = KodeBarang::find($validated['kode_barang_id']);
                $hargaSatuanArkas = $barang ? (float) $barang->harga_acuan : 0;
            }

            $maxNoUrut = (int) RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->max('no_urut');

            $item = RkasItem::create([
                'tahun_anggaran_id' => $tahunAnggaran->id,
                'master_program_id' => $validated['master_program_id'],
                'master_kode_rekening_id' => $validated['master_kode_rekening_id'],
                'kode_barang_id' => $validated['kode_barang_id'] ?? null,
                'uraian' => $validated['uraian'],
                'keterangan_kustom' => $validated['keterangan_kustom'] ?? null,
                'volume' => $totalVolume,
                'satuan' => $satuanUtama,
                'harga_satuan' => $hargaSatuan,
                'harga_satuan_arkas' => $hargaSatuanArkas,
                'jumlah' => $totalJumlah,
                'koreksi' => $validated['koreksi'] ?? 0,
                'no_urut' => $maxNoUrut + 1,
            ]);

            foreach ($validated['alokasi'] as $bulan => $data) {
                $vol = isset($data['volume']) ? (float) $data['volume'] : 0;
                $sat = ! empty($data['satuan']) ? trim($data['satuan']) : $satuanUtama;
                $subtotal = $vol * $hargaSatuan;

                if ($vol > 0) {
                    RkasItemBulan::create([
                        'rkas_item_id' => $item->id,
                        'bulan' => (int) $bulan,
                        'volume' => $vol,
                        'satuan' => $sat,
                        'jumlah' => $subtotal,
                    ]);
                }
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Anggaran kegiatan berhasil disimpan.', 'id' => $item->id]);
            }

            return redirect()->back()->with('success', 'Anggaran kegiatan berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update existing RKAS item with 12-month allocation.
     */
    public function update(Request $request, $id)
    {
        $item = RkasItem::findOrFail($id);
        $tahunAnggaran = TahunAnggaran::find($item->tahun_anggaran_id) ?? TahunAnggaran::where('tahun', 2026)->first();

        if ($guard = $this->assertRkasEditable($request, $tahunAnggaran)) {
            return $guard;
        }

        $validated = $request->validate([
            'master_program_id' => 'required|exists:master_program,id',
            'master_kode_rekening_id' => 'required|exists:master_kode_rekening,id',
            'kode_barang_id' => 'nullable|exists:kode_barang,id',
            'uraian' => 'required|string|max:500',
            'keterangan_kustom' => 'nullable|string|max:255',
            'harga_satuan' => 'required|numeric|min:0',
            'koreksi' => 'nullable|numeric',
            'alokasi' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $totalVolume = 0;
            $satuanUtama = $item->satuan ?: 'satuan';

            foreach ($validated['alokasi'] as $bulan => $data) {
                $vol = isset($data['volume']) ? (float) $data['volume'] : 0;
                $sat = ! empty($data['satuan']) ? trim($data['satuan']) : $satuanUtama;
                if ($vol > 0) {
                    $totalVolume += $vol;
                    $satuanUtama = $sat;
                }
            }

            $hargaSatuan = (float) $validated['harga_satuan'];
            $totalJumlah = $totalVolume * $hargaSatuan;

            // harga_satuan_arkas diambil dari harga_acuan katalog, bukan input user
            $hargaSatuanArkas = 0;
            if (! empty($validated['kode_barang_id'])) {
                $barang = KodeBarang::find($validated['kode_barang_id']);
                $hargaSatuanArkas = $barang ? (float) $barang->harga_acuan : 0;
            }

            $item->update([
                'master_program_id' => $validated['master_program_id'],
                'master_kode_rekening_id' => $validated['master_kode_rekening_id'],
                'kode_barang_id' => $validated['kode_barang_id'] ?? null,
                'uraian' => $validated['uraian'],
                'keterangan_kustom' => $validated['keterangan_kustom'] ?? null,
                'volume' => $totalVolume,
                'satuan' => $satuanUtama,
                'harga_satuan' => $hargaSatuan,
                'harga_satuan_arkas' => $hargaSatuanArkas,
                'jumlah' => $totalJumlah,
                'koreksi' => $validated['koreksi'] ?? 0,
            ]);

            // Sync alokasi 12 bulan
            RkasItemBulan::where('rkas_item_id', $item->id)->delete();

            foreach ($validated['alokasi'] as $bulan => $data) {
                $vol = isset($data['volume']) ? (float) $data['volume'] : 0;
                $sat = ! empty($data['satuan']) ? trim($data['satuan']) : $satuanUtama;
                $subtotal = $vol * $hargaSatuan;

                if ($vol > 0) {
                    RkasItemBulan::create([
                        'rkas_item_id' => $item->id,
                        'bulan' => (int) $bulan,
                        'volume' => $vol,
                        'satuan' => $sat,
                        'jumlah' => $subtotal,
                    ]);
                }
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Perubahan anggaran berhasil disimpan.']);
            }

            return redirect()->back()->with('success', 'Perubahan anggaran berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete an RKAS item.
     */
    public function destroy(Request $request, $id)
    {
        $item = RkasItem::findOrFail($id);
        $tahunAnggaran = TahunAnggaran::find($item->tahun_anggaran_id) ?? TahunAnggaran::where('tahun', 2026)->first();

        if ($guard = $this->assertRkasEditable($request, $tahunAnggaran)) {
            return $guard;
        }

        $item->delete();

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Item anggaran berhasil dihapus.']);
        }

        return redirect()->back()->with('success', 'Item anggaran berhasil dihapus.');
    }

    /**
     * Build FLAT data for export — struktur ARKAS RINCIAN asli.
     * Hanya 3 level: Kegiatan → Rekening → Item (TANPA Standar/Program).
     * Tiap item dilengkapi Volume+Jumlah per bulan terpisah (24 kolom),
     * Validasi Bulanan (BENAR/SALAH), dan Kontrol (OK/SELISIH).
     */
    private function buildFlatForExport(TahunAnggaran $tahunAnggaran): array
    {
        $items = RkasItem::with(['program', 'kodeRekening.jenisBelanja', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        foreach ($items as $item) {
            $mapVol = [];
            $mapJml = [];
            for ($b = 1; $b <= 12; $b++) {
                $ab = $item->alokasiBulan->firstWhere('bulan', $b);
                $mapVol[$b] = $ab ? (float) $ab->volume : 0;
                $mapJml[$b] = $ab ? (float) $ab->jumlah : 0;
            }
            $item->bulanVol = $mapVol;
            $item->bulanJml = $mapJml;
            // Legacy alias untuk view lama yang masih pakai bulanMap (hanya Jumlah)
            $item->bulanMap = $mapJml;
            $item->tahap1Vol = array_sum(array_slice($mapVol, 0, 6, true));
            $item->tahap1Jml = array_sum(array_slice($mapJml, 0, 6, true));
            $item->tahap2Vol = array_sum(array_slice($mapVol, 6, 6, true));
            $item->tahap2Jml = array_sum(array_slice($mapJml, 6, 6, true));
            // Alias lama
            $item->tahap1 = $item->tahap1Jml;
            $item->tahap2 = $item->tahap2Jml;
            // Validasi Bulanan: BENAR jika Jumlah 1 tahun == Tahap I + Tahap II (tanpa koreksi)
            $sumBulanan = $item->tahap1Jml + $item->tahap2Jml;
            $item->validasiBulanan = abs((float) $item->jumlah - $sumBulanan) < 0.01 ? 'BENAR' : 'SALAH';
            // Kategori untuk Excel
            $item->kategori = $item->kodeRekening->jenisBelanja->nama ?? ($item->kodeRekening->kategori_belanja ?? '');
        }

        // Group flat: Kegiatan -> Rekening -> Item
        $flatGroups = $items->groupBy('master_program_id')->map(function ($group) {
            $prog = $group->first()->program;
            // Rekening dalam kegiatan, urut natural kode
            $byRek = $group->groupBy('master_kode_rekening_id');
            $rekenings = $byRek->map(function ($rekItems) {
                $rek = $rekItems->first()->kodeRekening;
                $perBulanVol = [];
                $perBulanJml = [];
                for ($b = 1; $b <= 12; $b++) {
                    $perBulanVol[$b] = $rekItems->sum(fn ($it) => $it->bulanVol[$b] ?? 0);
                    $perBulanJml[$b] = $rekItems->sum(fn ($it) => $it->bulanJml[$b] ?? 0);
                }

                return [
                    'id' => $rekItems->first()->master_kode_rekening_id,
                    'kode' => $rek->kode ?? '-',
                    'nama' => $rek->nama ?? 'Rekening',
                    'kategori' => $rek->jenisBelanja->nama ?? ($rek->kategori_belanja ?? ''),
                    'jenis' => $rek->jenisBelanja->nama ?? ($rek->kategori_belanja ?? ''),
                    'items' => $rekItems->sortBy('no_urut')->values(),
                    'jumlah_item' => $rekItems->count(),
                    'perBulanVol' => $perBulanVol,
                    'perBulanJml' => $perBulanJml,
                    'perBulan' => $perBulanJml, // alias lama (Jumlah)
                    'subTahap1Vol' => array_sum(array_slice($perBulanVol, 0, 6, true)),
                    'subTahap1' => array_sum(array_slice($perBulanJml, 0, 6, true)),
                    'subTahap1Jml' => array_sum(array_slice($perBulanJml, 0, 6, true)),
                    'subTahap2Vol' => array_sum(array_slice($perBulanVol, 6, 6, true)),
                    'subTahap2' => array_sum(array_slice($perBulanJml, 6, 6, true)),
                    'subTahap2Jml' => array_sum(array_slice($perBulanJml, 6, 6, true)),
                    'total_sudah' => $rekItems->sum(fn ($it) => (float) $it->jumlah_koreksi),
                    'total_kontrol' => $rekItems->sum(fn ($it) => (float) $it->jumlah),
                    'total_koreksi' => $rekItems->sum(fn ($it) => (float) $it->koreksi),
                ];
            })->sortBy('kode', SORT_NATURAL)->values();

            $perBulanVol = [];
            $perBulanJml = [];
            for ($b = 1; $b <= 12; $b++) {
                $perBulanVol[$b] = $group->sum(fn ($it) => $it->bulanVol[$b] ?? 0);
                $perBulanJml[$b] = $group->sum(fn ($it) => $it->bulanJml[$b] ?? 0);
            }

            return [
                'id' => $group->first()->master_program_id,
                'kode' => $prog->kode ?? '-',
                'nama' => $prog->nama ?? 'Kegiatan',
                'program' => $prog->program ?? '',
                'sub_program' => $prog->sub_program ?? '',
                'rekenings' => $rekenings,
                'items' => $group->sortBy('no_urut')->values(),
                'jumlah_item' => $group->count(),
                'jumlah_rekening' => $rekenings->count(),
                'perBulanVol' => $perBulanVol,
                'perBulanJml' => $perBulanJml,
                'perBulan' => $perBulanJml, // alias
                'subTahap1Vol' => array_sum(array_slice($perBulanVol, 0, 6, true)),
                'subTahap1' => array_sum(array_slice($perBulanJml, 0, 6, true)),
                'subTahap1Jml' => array_sum(array_slice($perBulanJml, 0, 6, true)),
                'subTahap2Vol' => array_sum(array_slice($perBulanVol, 6, 6, true)),
                'subTahap2' => array_sum(array_slice($perBulanJml, 6, 6, true)),
                'subTahap2Jml' => array_sum(array_slice($perBulanJml, 6, 6, true)),
                'total_sudah' => $group->sum(fn ($it) => (float) $it->jumlah_koreksi),
                'total_kontrol' => $group->sum(fn ($it) => (float) $it->jumlah),
                'total_koreksi' => $group->sum(fn ($it) => (float) $it->koreksi),
            ];
        })->sortBy('kode', SORT_NATURAL)->values();

        // Alias untuk view lama yang masih pakai $groups
        $groups = $flatGroups;

        // Grand totals — Volume & Jumlah per bulan terpisah
        $grandPerBulanVol = [];
        $grandPerBulanJml = [];
        for ($b = 1; $b <= 12; $b++) {
            $grandPerBulanVol[$b] = $items->sum(fn ($it) => $it->bulanVol[$b] ?? 0);
            $grandPerBulanJml[$b] = $items->sum(fn ($it) => $it->bulanJml[$b] ?? 0);
        }
        // Legacy alias (hanya Jumlah)
        $grandPerBulan = $grandPerBulanJml;
        $grandTahap1Vol = array_sum(array_slice($grandPerBulanVol, 0, 6, true));
        $grandTahap1 = array_sum(array_slice($grandPerBulanJml, 0, 6, true));
        $grandTahap1Jml = $grandTahap1;
        $grandTahap2Vol = array_sum(array_slice($grandPerBulanVol, 6, 6, true));
        $grandTahap2 = array_sum(array_slice($grandPerBulanJml, 6, 6, true));
        $grandTahap2Jml = $grandTahap2;
        $grandTotal = $items->sum(fn ($it) => (float) $it->jumlah_koreksi);
        $grandKoreksi = $items->sum(fn ($it) => (float) $it->koreksi);
        $grandKontrol = $items->sum(fn ($it) => (float) $it->jumlah);
        $grandJumlah = $grandKontrol;
        $sisaPagu = ($tahunAnggaran->pagu_total ?? 0) - $grandTotal;

        return compact('flatGroups', 'groups', 'items', 'grandPerBulanVol', 'grandPerBulanJml', 'grandPerBulan', 'grandTahap1Vol', 'grandTahap1', 'grandTahap1Jml', 'grandTahap2Vol', 'grandTahap2', 'grandTahap2Jml', 'grandTotal', 'grandKoreksi', 'grandKontrol', 'grandJumlah', 'sisaPagu');
    }

    /**
     * Build hierarchical 5-level data for export.
     * Struktur: Standar (master_program.program) → Program (master_program.sub_program)
     *         → Kegiatan (master_program kode/nama) → Rekening (master_kode_rekening)
     *         → Item (rkas_item + 12 bulan).
     * Tetap menghitung groups flat per kegiatan & grand totals untuk kompatibilitas.
     *
     * @deprecated Gunakan buildFlatForExport() — hierarki 5 level diganti flat 3 level ARKAS RINCIAN.
     */
    private function buildHierarchicalForExport(TahunAnggaran $tahunAnggaran): array
    {
        $items = RkasItem::with(['program', 'kodeRekening.jenisBelanja', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        foreach ($items as $item) {
            $map = [];
            for ($b = 1; $b <= 12; $b++) {
                $ab = $item->alokasiBulan->firstWhere('bulan', $b);
                $map[$b] = $ab ? (float) $ab->jumlah : 0;
            }
            $item->bulanMap = $map;
            $item->tahap1 = array_sum(array_slice($map, 0, 6, true));
            $item->tahap2 = array_sum(array_slice($map, 6, 6, true));
        }

        // Reuse grouped per kegiatan untuk kompatibilitas (dipakai test lama & fallback view)
        $groups = $items->groupBy('master_program_id')->map(function ($group) {
            $prog = $group->first()->program;
            $perBulan = [];
            for ($b = 1; $b <= 12; $b++) {
                $perBulan[$b] = $group->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
            }
            $subTahap1 = array_sum(array_slice($perBulan, 0, 6, true));
            $subTahap2 = array_sum(array_slice($perBulan, 6, 6, true));
            $totalKoreksi = $group->sum(fn ($it) => (float) $it->jumlah_koreksi);
            $totalKontrol = $group->sum(fn ($it) => (float) $it->jumlah);
            $totalKoreksiAdj = $group->sum(fn ($it) => (float) $it->koreksi);

            return [
                'id' => $group->first()->master_program_id,
                'kode' => $prog->kode ?? '-',
                'nama' => $prog->nama ?? 'Kegiatan',
                'program' => $prog->program ?? 'Tanpa Standar',
                'sub_program' => $prog->sub_program ?? '',
                'items' => $group->values(),
                'jumlah_item' => $group->count(),
                'total_sudah' => $totalKoreksi,
                'total_kontrol' => $totalKontrol,
                'total_koreksi' => $totalKoreksiAdj,
                'perBulan' => $perBulan,
                'subTahap1' => $subTahap1,
                'subTahap2' => $subTahap2,
            ];
        })->sortBy('kode', SORT_NATURAL)->values();

        // Grand totals
        $grandPerBulan = [];
        for ($b = 1; $b <= 12; $b++) {
            $grandPerBulan[$b] = $items->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
        }
        $grandTahap1 = array_sum(array_slice($grandPerBulan, 0, 6, true));
        $grandTahap2 = array_sum(array_slice($grandPerBulan, 6, 6, true));
        $grandTotal = $items->sum(fn ($it) => (float) $it->jumlah_koreksi);
        $grandKoreksi = $items->sum(fn ($it) => (float) $it->koreksi);
        $grandKontrol = $items->sum(fn ($it) => (float) $it->jumlah);
        $sisaPagu = ($tahunAnggaran->pagu_total ?? 0) - $grandTotal;

        // --- Hierarki 5 level ---
        // Level 1: Standar = master_program.program
        $byStandar = $items->groupBy(function ($it) {
            $v = trim((string) ($it->program->program ?? ''));

            return $v !== '' ? $v : 'Tanpa Standar';
        });

        $hierarchy = $byStandar->map(function ($standarItems, $standarNama) {
            // Level 2: Program = master_program.sub_program
            $bySub = $standarItems->groupBy(function ($it) {
                $v = trim((string) ($it->program->sub_program ?? ''));

                return $v !== '' ? $v : 'Tanpa Program';
            });

            $subs = $bySub->map(function ($subItems, $subNama) {
                // Level 3: Kegiatan = master_program
                $byKegiatan = $subItems->groupBy('master_program_id');

                $kegiatans = $byKegiatan->map(function ($kegItems) {
                    $prog = $kegItems->first()->program;
                    // Level 4: Rekening
                    $byRek = $kegItems->groupBy('master_kode_rekening_id');
                    $rekenings = $byRek->map(function ($rekItems) {
                        $rek = $rekItems->first()->kodeRekening;
                        $perBulan = [];
                        for ($b = 1; $b <= 12; $b++) {
                            $perBulan[$b] = $rekItems->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
                        }
                        $subTahap1 = array_sum(array_slice($perBulan, 0, 6, true));
                        $subTahap2 = array_sum(array_slice($perBulan, 6, 6, true));

                        return [
                            'id' => $rekItems->first()->master_kode_rekening_id,
                            'kode' => $rek->kode ?? '-',
                            'nama' => $rek->nama ?? 'Rekening',
                            'jenis' => $rek->jenisBelanja->nama ?? ($rek->kategori_belanja ?? ''),
                            'items' => $rekItems->values(),
                            'jumlah_item' => $rekItems->count(),
                            'total_sudah' => $rekItems->sum(fn ($it) => (float) $it->jumlah_koreksi),
                            'total_kontrol' => $rekItems->sum(fn ($it) => (float) $it->jumlah),
                            'total_koreksi' => $rekItems->sum(fn ($it) => (float) $it->koreksi),
                            'perBulan' => $perBulan,
                            'subTahap1' => $subTahap1,
                            'subTahap2' => $subTahap2,
                        ];
                    })->sortBy('kode', SORT_NATURAL)->values();

                    $perBulan = [];
                    for ($b = 1; $b <= 12; $b++) {
                        $perBulan[$b] = $kegItems->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
                    }
                    $subTahap1 = array_sum(array_slice($perBulan, 0, 6, true));
                    $subTahap2 = array_sum(array_slice($perBulan, 6, 6, true));

                    return [
                        'id' => $kegItems->first()->master_program_id,
                        'kode' => $prog->kode ?? '-',
                        'nama' => $prog->nama ?? 'Kegiatan',
                        'program' => $prog->program ?? '',
                        'sub_program' => $prog->sub_program ?? '',
                        'rekenings' => $rekenings,
                        'items' => $kegItems->values(),
                        'jumlah_item' => $kegItems->count(),
                        'jumlah_rekening' => $rekenings->count(),
                        'total_sudah' => $kegItems->sum(fn ($it) => (float) $it->jumlah_koreksi),
                        'total_kontrol' => $kegItems->sum(fn ($it) => (float) $it->jumlah),
                        'total_koreksi' => $kegItems->sum(fn ($it) => (float) $it->koreksi),
                        'perBulan' => $perBulan,
                        'subTahap1' => $subTahap1,
                        'subTahap2' => $subTahap2,
                    ];
                })->sortBy('kode', SORT_NATURAL)->values();

                $perBulan = [];
                for ($b = 1; $b <= 12; $b++) {
                    $perBulan[$b] = $subItems->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
                }
                $subTahap1 = array_sum(array_slice($perBulan, 0, 6, true));
                $subTahap2 = array_sum(array_slice($perBulan, 6, 6, true));

                return [
                    'sub' => $subNama,
                    'kegiatans' => $kegiatans,
                    'jumlah_kegiatan' => $kegiatans->count(),
                    'jumlah_item' => $subItems->count(),
                    'total_sudah' => $subItems->sum(fn ($it) => (float) $it->jumlah_koreksi),
                    'total_kontrol' => $subItems->sum(fn ($it) => (float) $it->jumlah),
                    'total_koreksi' => $subItems->sum(fn ($it) => (float) $it->koreksi),
                    'perBulan' => $perBulan,
                    'subTahap1' => $subTahap1,
                    'subTahap2' => $subTahap2,
                ];
            })->sortBy('sub')->values();

            $perBulan = [];
            for ($b = 1; $b <= 12; $b++) {
                $perBulan[$b] = $standarItems->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
            }
            $subTahap1 = array_sum(array_slice($perBulan, 0, 6, true));
            $subTahap2 = array_sum(array_slice($perBulan, 6, 6, true));

            return [
                'standar' => $standarNama,
                'subs' => $subs,
                'jumlah_program' => $subs->count(),
                'jumlah_kegiatan' => $standarItems->groupBy('master_program_id')->count(),
                'jumlah_item' => $standarItems->count(),
                'total_sudah' => $standarItems->sum(fn ($it) => (float) $it->jumlah_koreksi),
                'total_kontrol' => $standarItems->sum(fn ($it) => (float) $it->jumlah),
                'total_koreksi' => $standarItems->sum(fn ($it) => (float) $it->koreksi),
                'perBulan' => $perBulan,
                'subTahap1' => $subTahap1,
                'subTahap2' => $subTahap2,
            ];
        })->sortBy('standar')->values();

        return compact('hierarchy', 'groups', 'items', 'grandPerBulan', 'grandTahap1', 'grandTahap2', 'grandTotal', 'grandKoreksi', 'grandKontrol', 'sisaPagu');
    }

    /**
     * Build grouped data for export (reusable by index/pdfGrouped/exportGrouped).
     * Returns groups sorted natural + per-item 12-month breakdown.
     */
    private function buildGroupedForExport(TahunAnggaran $tahunAnggaran): array
    {
        $items = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        // Attach per-month map (1..12 => jumlah) to each item
        foreach ($items as $item) {
            $map = [];
            for ($b = 1; $b <= 12; $b++) {
                $ab = $item->alokasiBulan->firstWhere('bulan', $b);
                $map[$b] = $ab ? (float) $ab->jumlah : 0;
            }
            $item->bulanMap = $map; // dynamic attribute for export views
            $item->tahap1 = array_sum(array_slice($map, 0, 6, true));
            $item->tahap2 = array_sum(array_slice($map, 6, 6, true));
        }

        $groups = $items->groupBy('master_program_id')->map(function ($group) {
            $prog = $group->first()->program;
            // Per-month subtotal per kegiatan
            $perBulan = [];
            for ($b = 1; $b <= 12; $b++) {
                $perBulan[$b] = $group->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
            }
            $subTahap1 = array_sum(array_slice($perBulan, 0, 6, true));
            $subTahap2 = array_sum(array_slice($perBulan, 6, 6, true));
            $totalKoreksi = $group->sum(fn ($it) => (float) $it->jumlah_koreksi);
            $totalKontrol = $group->sum(fn ($it) => (float) $it->jumlah);
            $totalKoreksiAdj = $group->sum(fn ($it) => (float) $it->koreksi);

            return [
                'id' => $group->first()->master_program_id,
                'kode' => $prog->kode ?? '-',
                'nama' => $prog->nama ?? 'Kegiatan',
                'sub_program' => $prog->sub_program ?? '',
                'items' => $group->values(),
                'jumlah_item' => $group->count(),
                'total_sudah' => $totalKoreksi, // 1 tahun + koreksi
                'total_kontrol' => $totalKontrol,
                'total_koreksi' => $totalKoreksiAdj,
                'perBulan' => $perBulan,
                'subTahap1' => $subTahap1,
                'subTahap2' => $subTahap2,
            ];
        })->sortBy('kode', SORT_NATURAL)->values();

        // Grand totals
        $grandPerBulan = [];
        for ($b = 1; $b <= 12; $b++) {
            $grandPerBulan[$b] = $items->sum(fn ($it) => $it->bulanMap[$b] ?? 0);
        }
        $grandTahap1 = array_sum(array_slice($grandPerBulan, 0, 6, true));
        $grandTahap2 = array_sum(array_slice($grandPerBulan, 6, 6, true));
        $grandTotal = $items->sum(fn ($it) => (float) $it->jumlah_koreksi);
        $grandKoreksi = $items->sum(fn ($it) => (float) $it->koreksi);
        $grandKontrol = $items->sum(fn ($it) => (float) $it->jumlah);
        $sisaPagu = ($tahunAnggaran->pagu_total ?? 0) - $grandTotal;

        return compact('groups', 'items', 'grandPerBulan', 'grandTahap1', 'grandTahap2', 'grandTotal', 'grandKoreksi', 'grandKontrol', 'sisaPagu');
    }

    /**
     * Export the worksheet as PDF kertas kerja (A4) - flat legacy.
     */
    public function pdf(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);

        $items = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        $totalTahap1 = RkasItemBulan::whereHas('item', fn ($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))
            ->whereBetween('bulan', [1, 6])->sum('jumlah');
        $totalTahap2 = RkasItemBulan::whereHas('item', fn ($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))
            ->whereBetween('bulan', [7, 12])->sum('jumlah');

        $pdf = Pdf::loadView('rkas.pdf', compact(
            'sekolah',
            'tahunAnggaran',
            'items',
            'totalTahap1',
            'totalTahap2'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('kertas-kerja-rkas-'.($tahunAnggaran->tahun ?? 2026).'.pdf');
    }

    /**
     * Export grouped per kegiatan with 12-month breakdown - PDF (flat ARKAS RINCIAN, custom lebar).
     */
    public function pdfGrouped(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);
        $data = $this->buildFlatForExport($tahunAnggaran);

        $pdf = Pdf::loadView('rkas.pdf-grouped', array_merge(compact('sekolah', 'tahunAnggaran'), $data))
            ->setPaper('a3', 'landscape');

        return $pdf->download('kertas-kerja-rkas-grouped-'.($tahunAnggaran->tahun ?? 2026).'.pdf');
    }

    /**
     * Export the worksheet as Excel .xlsx (kertas kerja ARKAS + KOREKSI/KONTROL) - flat legacy.
     */
    public function export(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);

        $items = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        return Excel::download(
            new RkasKertasKerjaExport($sekolah, $tahunAnggaran, $items),
            'kertas-kerja-rkas-'.($tahunAnggaran->tahun ?? 2026).'.xlsx'
        );
    }

    /**
     * Export grouped per kegiatan with 12-month breakdown - Excel (flat ARKAS RINCIAN, 41 kolom).
     */
    public function exportGrouped(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);
        $data = $this->buildFlatForExport($tahunAnggaran);

        return Excel::download(
            new RkasKertasKerjaGroupedExport($sekolah, $tahunAnggaran, $data['groups'], $data),
            'kertas-kerja-rkas-grouped-'.($tahunAnggaran->tahun ?? 2026).'.xlsx'
        );
    }
}
