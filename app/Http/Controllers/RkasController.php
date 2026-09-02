<?php

namespace App\Http\Controllers;

use App\Exports\RkasKertasKerjaExport;
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
    /**
     * Display the RKAS 2026 Worksheet / Dashboard.
     */
    public function index(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah([
            'nama_sekolah' => 'SD NEGERI TOYANING 1',
        ]);

        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

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
            ? $allItems->sum(fn($i) => $i->alokasiBulan->where('bulan', $selectedBulan)->sum('jumlah'))
            : $totalSudahDianggarkan;

        // Group items per Kegiatan (ARKAS Kertas Kerja structure) - Urut otomatis berdasarkan kode SNP
        $kegiatanGroups = $items->groupBy('master_program_id')->map(function ($group) use ($selectedBulan) {
            $prog = $group->first()->program;
            $total1Tahun = $group->sum(fn($i) => $i->jumlah_koreksi);
            $totalBulan = $selectedBulan > 0
                ? $group->sum(fn($i) => $i->alokasiBulan->where('bulan', $selectedBulan)->sum('jumlah'))
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
                'bulan_aktif' => $group->flatMap(fn($i) => $i->alokasiBulan->where('volume', '>', 0)->pluck('bulan'))->unique()->sort()->values(),
            ];
        })->sortBy('kode', SORT_NATURAL)->values();

        return view('rkas.index', compact(
            'sekolah',
            'tahunAnggaran',
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
            'rekening_id' => $item->master_kode_rekening_id,
            'rekening_text' => $item->kodeRekening ? "[{$item->kodeRekening->kode}] {$item->kodeRekening->nama}" : '',
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
        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

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
     * Export the worksheet as PDF kertas kerja (A4).
     */
    public function pdf()
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        $items = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        $totalTahap1 = RkasItemBulan::whereHas('item', fn($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))
            ->whereBetween('bulan', [1, 6])->sum('jumlah');
        $totalTahap2 = RkasItemBulan::whereHas('item', fn($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))
            ->whereBetween('bulan', [7, 12])->sum('jumlah');

        $pdf = Pdf::loadView('rkas.pdf', compact(
            'sekolah',
            'tahunAnggaran',
            'items',
            'totalTahap1',
            'totalTahap2'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('kertas-kerja-rkas-' . ($tahunAnggaran->tahun ?? 2026) . '.pdf');
    }

    /**
     * Export the worksheet as Excel .xlsx (kertas kerja ARKAS + KOREKSI/KONTROL).
     */
    public function export()
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        $items = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->orderBy('no_urut')
            ->get();

        return Excel::download(
            new RkasKertasKerjaExport($sekolah, $tahunAnggaran, $items),
            'kertas-kerja-rkas-' . ($tahunAnggaran->tahun ?? 2026) . '.xlsx'
        );
    }
}
