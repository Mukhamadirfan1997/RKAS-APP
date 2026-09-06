<?php

namespace App\Http\Controllers;

use App\Exports\RkasKertasKerjaExport;
use App\Exports\RkasKertasKerjaGroupedExport;
use App\Models\AuditLog;
use App\Models\KodeBarang;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ?? new TahunAnggaran(['tahun' => 2026, 'pagu_total' => 0]);
    }

    /**
     * Helper terpusat untuk penamaan file ekspor — dipakai SEMUA method export.
     * Pola: {Tahun}-RKAS-{Varian}-{StatusSingkat}-{NamaSekolahSlug}.{ext}
     * Contoh: 2026-RKAS-Ringkas-Draft-SDN-Toyaning-1.pdf
     *         2026-RKAS-TahapI-Draft-SDN-Toyaning-1.pdf
     *         2026-RKAS-Januari-Draft-SDN-Toyaning-1.pdf
     *         2026-RKAS-Kerja-Draft-SDN-Toyaning-1.xlsx
     */
    private function namaFileEkspor(string $varian, TahunAnggaran $tahunAnggaran, ?PengaturanSekolah $sekolah, string $ext = 'pdf'): string
    {
        $tahun = $tahunAnggaran->tahun ?? 2026;
        $statusRaw = trim((string) ($tahunAnggaran->status_pengesahan ?? 'Draft'));
        $status = $statusRaw !== '' ? $statusRaw : 'Draft';
        // Normalisasi status ke bentuk singkat kapital awal (jaga kalau DB lowercase)
        // Pakai apa adanya jika sudah Draft/Disahkan/Pergeseran, fallback kapitalisasi sederhana
        if (! in_array($status, ['Draft', 'Disahkan', 'Pergeseran'], true)) {
            $status = ucfirst(strtolower($status));
            if ($status === '') $status = 'Draft';
        }
        $namaSekolah = trim((string) ($sekolah->nama_sekolah ?? ''));
        if ($namaSekolah === '') {
            $slug = 'Sekolah';
        } else {
            $slug = preg_replace('/\s+/', '-', $namaSekolah);
            $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
            if ($slug === '') $slug = 'Sekolah';
        }
        $varian = trim($varian) !== '' ? trim($varian) : 'Dokumen';
        $ext = ltrim(strtolower($ext), '.');
        if ($ext === '') $ext = 'pdf';

        return "{$tahun}-RKAS-{$varian}-{$status}-{$slug}.{$ext}";
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

        // Ambil semua item terfilter untuk sorting natural + totals, lalu paginate manual agar urut program→rekening→no_urut benar lintas halaman
        $filteredAll = $query->get();

        // Attach monthly volume and amount if specific month is selected (untuk semua terfilter, sebelum paginate agar badge & sort benar)
        if ($selectedBulan > 0) {
            $filteredAll->each(function ($item) use ($selectedBulan) {
                $bulanItem = $item->alokasiBulan->firstWhere('bulan', $selectedBulan);
                $item->volume_bulan = $bulanItem ? (float) $bulanItem->volume : 0;
                $item->jumlah_bulan = $bulanItem ? (float) $bulanItem->jumlah : 0;
            });
        }

        // Calculate total summary (grand — tidak terpengaruh pagination, dipakai footer & badge Tahap)
        $allItems = RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->with('alokasiBulan')->get();
        $totalSudahDianggarkan = $allItems->sum('jumlah');
        $totalSudahKoreksi = $allItems->sum(fn ($i) => (float) $i->jumlah_koreksi);
        $sisaPagu = ($tahunAnggaran->pagu_total ?? 0) - $totalSudahDianggarkan;
        $totalBulanTerpilih = $selectedBulan > 0
            ? $allItems->sum(fn ($i) => $i->alokasiBulan->where('bulan', $selectedBulan)->sum('jumlah'))
            : $totalSudahDianggarkan;
        // Grand untuk badge Tahap I/II di lembar kerja (selalu 1 tahun penuh, tidak filter bulan/pagination)
        $grandTahap1Jumlah = $allItems->sum(fn ($i) => $i->alokasiBulan->whereBetween('bulan', [1, 6])->sum('jumlah'));
        $grandTahap2Jumlah = $allItems->sum(fn ($i) => $i->alokasiBulan->whereBetween('bulan', [7, 12])->sum('jumlah'));

        // Group items per Kegiatan — dari filteredAll (bukan paginated) agar header kegiatan konsisten
        $kegiatanGroups = $filteredAll->groupBy('master_program_id')->map(function ($group) use ($selectedBulan) {
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

        // Sorting natural program.kode → rekening.kode → no_urut SEBELUM paginate (jamin urut lintas halaman)
        $sorted = $filteredAll->sort(function ($a, $b) {
            $ka = $a->program->kode ?? '';
            $kb = $b->program->kode ?? '';
            $c = strnatcasecmp($ka, $kb);
            if ($c !== 0) return $c;
            $ra = $a->kodeRekening->kode ?? '';
            $rb = $b->kodeRekening->kode ?? '';
            $c2 = strnatcasecmp($ra, $rb);
            if ($c2 !== 0) return $c2;
            return ($a->no_urut ?? 0) <=> ($b->no_urut ?? 0);
        })->values();

        // Paginate manual 50/halaman — export (buildFlatForExport) tetap query full tanpa pagination
        $perPage = 50;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $sorted->forPage($currentPage, $perPage)->values();
        $items = new LengthAwarePaginator(
            $currentItems,
            $sorted->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $daftarTahun = TahunAnggaran::orderBy('tahun', 'desc')->get();

        return view('rkas.index', compact(
            'sekolah',
            'tahunAnggaran',
            'daftarTahun',
            'selectedBulan',
            'items',
            'kegiatanGroups',
            'totalSudahDianggarkan',
            'totalSudahKoreksi',
            'totalBulanTerpilih',
            'sisaPagu',
            'grandTahap1Jumlah',
            'grandTahap2Jumlah'
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
            'satuan' => 'required_without:satuan_barang|string|max:50',
            'satuan_barang' => 'required_without:satuan|nullable|string|max:50',
            'alokasi' => 'required|array',
            'alokasi.*.volume' => 'nullable|numeric|min:0',
            'alokasi.*.satuan' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $totalVolume = 0;
            $totalJumlah = 0;
            // Kolom DB `rkas_item.satuan` — terima `satuan` (utama) atau alias `satuan_barang`
            $satuanUtama = trim((string) ($validated['satuan'] ?? $validated['satuan_barang'] ?? ''));
            if ($satuanUtama === '') {
                $satuanUtama = 'satuan';
            }

            foreach ($validated['alokasi'] as $bulan => $data) {
                $vol = isset($data['volume']) ? (float) $data['volume'] : 0;
                if ($vol > 0) {
                    $totalVolume += $vol;
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
            'satuan' => 'required_without:satuan_barang|string|max:50',
            'satuan_barang' => 'required_without:satuan|nullable|string|max:50',
            'alokasi' => 'required|array',
            'alokasi.*.volume' => 'nullable|numeric|min:0',
            'alokasi.*.satuan' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $totalVolume = 0;
            // Kolom DB `rkas_item.satuan` — terima `satuan` (utama) atau alias `satuan_barang`
            $satuanUtama = trim((string) ($validated['satuan'] ?? $validated['satuan_barang'] ?? $item->satuan ?? 'satuan'));
            if ($satuanUtama === '') {
                $satuanUtama = $item->satuan ?: 'satuan';
            }

            foreach ($validated['alokasi'] as $bulan => $data) {
                $vol = isset($data['volume']) ? (float) $data['volume'] : 0;
                if ($vol > 0) {
                    $totalVolume += $vol;
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
     *
     * @param string $mode  'tahunan'|'per_tahap'|'per_bulan' — menentukan filter & agregasi
     * @param int|null $bulan 1..12 hanya untuk mode per_bulan
     * @param int|null $tahap 1|2 hanya untuk mode per_tahap
     */
    private function buildFlatForExport(TahunAnggaran $tahunAnggaran, string $mode = 'tahunan', ?int $bulan = null, ?int $tahap = null): array
    {
        // Normalisasi mode; reuse filter whereHas alokasiBulan dari index() untuk per_bulan/per_tahap
        $mode = in_array($mode, ['tahunan', 'per_tahap', 'per_bulan'], true) ? $mode : 'tahunan';
        if ($mode === 'per_bulan') {
            $bulan = $bulan !== null ? (int) $bulan : (int) date('n');
            $bulan = max(1, min(12, $bulan));
        }
        if ($mode === 'per_tahap') {
            $tahap = $tahap !== null ? (int) $tahap : 1;
            $tahap = in_array($tahap, [1, 2], true) ? $tahap : 1;
        }
        $tahapBulans = null;
        $tahapNama = null;
        $tahapLabel = null;
        if ($mode === 'per_tahap' && $tahap !== null) {
            $tahapBulans = $tahap === 1 ? [1, 2, 3, 4, 5, 6] : [7, 8, 9, 10, 11, 12];
            $tahapNama = $tahap === 1 ? 'Tahap I' : 'Tahap II';
            $tahapLabel = $tahap === 1 ? 'Tahap I (Januari - Juni)' : 'Tahap II (Juli - Desember)';
        }

        $query = RkasItem::with(['program', 'kodeRekening.jenisBelanja', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut');

        // Reuse logika filter index(): hanya item dengan alokasi >0 di bulan tersebut / rentang 6 bulan
        if ($mode === 'per_bulan' && $bulan !== null) {
            $query->whereHas('alokasiBulan', function ($q) use ($bulan) {
                $q->where('bulan', $bulan)->where('volume', '>', 0);
            });
        } elseif ($mode === 'per_tahap' && $tahapBulans !== null) {
            $query->whereHas('alokasiBulan', function ($q) use ($tahapBulans) {
                $q->whereIn('bulan', $tahapBulans)->where('volume', '>', 0);
            });
        }

        $items = $query->get();

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
            // Alias per-bulan spesifik (volume/jumlah hanya bulan terpilih) — dipakai pdf-per-bulan
            if ($mode === 'per_bulan' && $bulan !== null) {
                $item->volumeBulan = $mapVol[$bulan] ?? 0;
                $item->jumlahBulan = $mapJml[$bulan] ?? 0;
                $item->satuanBulan = $item->alokasiBulan->firstWhere('bulan', $bulan)?->satuan ?? $item->satuan;
            }
            // Alias per-tahap spesifik (volume/jumlah hanya 6 bulan tahap) — dipakai pdf-per-tahap
            if ($mode === 'per_tahap' && $tahapBulans !== null) {
                $volTahap = 0; $jmlTahap = 0;
                foreach ($tahapBulans as $tb) { $volTahap += $mapVol[$tb] ?? 0; $jmlTahap += $mapJml[$tb] ?? 0; }
                $item->volumeTahap = $volTahap;
                $item->jumlahTahap = $jmlTahap;
            }
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

        // Group flat: Kegiatan -> Rekening -> Item (memakai filter mode yang sudah diterapkan di query)
        $flatGroups = $items->groupBy('master_program_id')->map(function ($group) use ($mode, $bulan, $tahap, $tahapBulans) {
            $prog = $group->first()->program;
            // Rekening dalam kegiatan, urut natural kode
            $byRek = $group->groupBy('master_kode_rekening_id');
            $rekenings = $byRek->map(function ($rekItems) use ($mode, $bulan, $tahap, $tahapBulans) {
                $rek = $rekItems->first()->kodeRekening;
                $perBulanVol = [];
                $perBulanJml = [];
                for ($b = 1; $b <= 12; $b++) {
                    $perBulanVol[$b] = $rekItems->sum(fn ($it) => $it->bulanVol[$b] ?? 0);
                    $perBulanJml[$b] = $rekItems->sum(fn ($it) => $it->bulanJml[$b] ?? 0);
                }
                $subBulanVol = ($mode === 'per_bulan' && $bulan !== null) ? ($perBulanVol[$bulan] ?? 0) : 0;
                $subBulanJml = ($mode === 'per_bulan' && $bulan !== null) ? ($perBulanJml[$bulan] ?? 0) : 0;
                $subTahapJml = 0; $subTahapVol = 0;
                if ($mode === 'per_tahap' && $tahapBulans !== null) {
                    foreach ($tahapBulans as $tb) { $subTahapVol += $perBulanVol[$tb] ?? 0; $subTahapJml += $perBulanJml[$tb] ?? 0; }
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
                    'subBulanVol' => $subBulanVol,
                    'subBulanJml' => $subBulanJml,
                    'subTahapVol' => $subTahapVol,
                    'subTahapJml' => $subTahapJml,
                ];
            })->sortBy('kode', SORT_NATURAL)->values();

            $perBulanVol = [];
            $perBulanJml = [];
            for ($b = 1; $b <= 12; $b++) {
                $perBulanVol[$b] = $group->sum(fn ($it) => $it->bulanVol[$b] ?? 0);
                $perBulanJml[$b] = $group->sum(fn ($it) => $it->bulanJml[$b] ?? 0);
            }
            $subBulanVol = ($mode === 'per_bulan' && $bulan !== null) ? ($perBulanVol[$bulan] ?? 0) : 0;
            $subBulanJml = ($mode === 'per_bulan' && $bulan !== null) ? ($perBulanJml[$bulan] ?? 0) : 0;
            $subTahapVol = 0; $subTahapJml = 0;
            if ($mode === 'per_tahap' && $tahapBulans !== null) {
                foreach ($tahapBulans as $tb) { $subTahapVol += $perBulanVol[$tb] ?? 0; $subTahapJml += $perBulanJml[$tb] ?? 0; }
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
                'subBulanVol' => $subBulanVol,
                'subBulanJml' => $subBulanJml,
                'subTahapVol' => $subTahapVol,
                'subTahapJml' => $subTahapJml,
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
        // Per-bulan agregasi khusus (hanya relevan untuk mode per_bulan)
        $grandBulanVol = ($mode === 'per_bulan' && $bulan !== null) ? ($grandPerBulanVol[$bulan] ?? 0) : 0;
        $grandBulanJml = ($mode === 'per_bulan' && $bulan !== null) ? ($grandPerBulanJml[$bulan] ?? 0) : 0;
        $grandBulanTotal = $grandBulanJml;
        $persenBulan = ($tahunAnggaran->pagu_total ?? 0) > 0 ? round($grandBulanTotal / (float) $tahunAnggaran->pagu_total * 100, 1) : 0;
        $bulanNamaList = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $bulanNama = ($mode === 'per_bulan' && $bulan !== null) ? ($bulanNamaList[$bulan] ?? '') : '';
        // Per-tahap agregasi khusus (hanya relevan untuk mode per_tahap)
        $grandTahapVol = 0; $grandTahapJml = 0; $grandTahapTotal = 0; $persenTahap = 0; $paguTahap = 0;
        if ($mode === 'per_tahap' && $tahapBulans !== null) {
            foreach ($tahapBulans as $tb) { $grandTahapVol += $grandPerBulanVol[$tb] ?? 0; $grandTahapJml += $grandPerBulanJml[$tb] ?? 0; }
            $grandTahapTotal = $grandTahapJml;
            $paguTahap = $tahap === 1 ? (float) ($tahunAnggaran->pagu_tahap1 ?? 0) : (float) ($tahunAnggaran->pagu_tahap2 ?? 0);
            $persenTahap = $paguTahap > 0 ? round($grandTahapTotal / $paguTahap * 100, 1) : 0;
        }

        return compact('flatGroups', 'groups', 'items', 'grandPerBulanVol', 'grandPerBulanJml', 'grandPerBulan', 'grandTahap1Vol', 'grandTahap1', 'grandTahap1Jml', 'grandTahap2Vol', 'grandTahap2', 'grandTahap2Jml', 'grandTotal', 'grandKoreksi', 'grandKontrol', 'grandJumlah', 'sisaPagu', 'mode', 'bulan', 'bulanNama', 'grandBulanVol', 'grandBulanJml', 'grandBulanTotal', 'persenBulan', 'tahap', 'tahapBulans', 'tahapNama', 'tahapLabel', 'grandTahapVol', 'grandTahapJml', 'grandTahapTotal', 'persenTahap', 'paguTahap');
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

        return $pdf->download($this->namaFileEkspor('Ringkas', $tahunAnggaran, $sekolah, 'pdf'));
    }

    /**
     * Export grouped per kegiatan with 12-month breakdown - PDF (flat ARKAS RINCIAN, custom lebar).
     */
    public function pdfGrouped(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);
        $data = $this->buildFlatForExport($tahunAnggaran, 'tahunan');

        $pdf = Pdf::loadView('rkas.pdf-grouped', array_merge(compact('sekolah', 'tahunAnggaran'), $data))
            ->setPaper('a3', 'landscape');

        return $pdf->download($this->namaFileEkspor('Ringkas', $tahunAnggaran, $sekolah, 'pdf'));
    }

    /**
     * Export per-bulan — flat Kegiatan→Rekening→Item hanya untuk satu bulan.
     * GET /rkas/pdf-per-bulan?bulan=1..12 (default bulan berjalan)
     */
    public function pdfPerBulan(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);
        $bulan = $request->query('bulan', (int) date('n'));
        $bulan = (int) $bulan;
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('n');
        }
        // Validasi eksplisit agar query string salah tidak 500
        $request->validate(['bulan' => 'nullable|integer|min:1|max:12']);

        $data = $this->buildFlatForExport($tahunAnggaran, 'per_bulan', $bulan);

        $pdf = Pdf::loadView('rkas.pdf-per-bulan', array_merge(compact('sekolah', 'tahunAnggaran'), $data))
            ->setPaper('a3', 'landscape');

        $bulanNama = $data['bulanNama'] ?? 'Bulan';
        $varianBulan = $bulanNama !== '' ? $bulanNama : 'Bulan';

        return $pdf->download($this->namaFileEkspor($varianBulan, $tahunAnggaran, $sekolah, 'pdf'));
    }

    /**
     * Export per-tahap — satu file per tahap (6 bulan breakdown), filter rentang 6 bulan.
     * GET /rkas/pdf-per-tahap?tahap=1|2 (1=Jan-Jun, 2=Jul-Des)
     */
    public function pdfPerTahap(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request);
        $tahap = (int) $request->query('tahap', 1);
        if (! in_array($tahap, [1, 2], true)) {
            $tahap = 1;
        }

        $data = $this->buildFlatForExport($tahunAnggaran, 'per_tahap', null, $tahap);

        $pdf = Pdf::loadView('rkas.pdf-per-tahap', array_merge(compact('sekolah', 'tahunAnggaran'), $data))
            ->setPaper('a3', 'landscape');

        $varianTahap = $tahap === 1 ? 'TahapI' : 'TahapII';

        return $pdf->download($this->namaFileEkspor($varianTahap, $tahunAnggaran, $sekolah, 'pdf'));
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
            $this->namaFileEkspor('Kerja', $tahunAnggaran, $sekolah, 'xlsx')
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
            $this->namaFileEkspor('Kerja', $tahunAnggaran, $sekolah, 'xlsx')
        );
    }
}
