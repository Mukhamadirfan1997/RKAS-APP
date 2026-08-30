<?php

namespace App\Http\Controllers;

use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Calculate total summary
        $allItems = RkasItem::where('tahun_anggaran_id', $tahunAnggaran->id)->with('alokasiBulan')->get();
        $totalSudahDianggarkan = $allItems->sum('jumlah');
        $sisaPagu = ($tahunAnggaran->pagu_total ?? 0) - $totalSudahDianggarkan;

        return view('rkas.index', compact(
            'sekolah',
            'tahunAnggaran',
            'selectedBulan',
            'items',
            'totalSudahDianggarkan',
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
            'jumlah' => (float) $item->jumlah,
            'alokasi' => $alokasiMap,
        ]);
    }

    /**
     * Store new RKAS item with 12-month allocation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'master_program_id' => 'required|exists:master_program,id',
            'master_kode_rekening_id' => 'required|exists:master_kode_rekening,id',
            'kode_barang_id' => 'nullable|exists:kode_barang,id',
            'uraian' => 'required|string|max:500',
            'keterangan_kustom' => 'nullable|string|max:255',
            'harga_satuan' => 'required|numeric|min:0',
            'alokasi' => 'required|array',
        ]);

        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

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
                'harga_satuan_arkas' => $hargaSatuan,
                'jumlah' => $totalJumlah,
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

        $validated = $request->validate([
            'master_program_id' => 'required|exists:master_program,id',
            'master_kode_rekening_id' => 'required|exists:master_kode_rekening,id',
            'kode_barang_id' => 'nullable|exists:kode_barang,id',
            'uraian' => 'required|string|max:500',
            'keterangan_kustom' => 'nullable|string|max:255',
            'harga_satuan' => 'required|numeric|min:0',
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

            $item->update([
                'master_program_id' => $validated['master_program_id'],
                'master_kode_rekening_id' => $validated['master_kode_rekening_id'],
                'kode_barang_id' => $validated['kode_barang_id'] ?? null,
                'uraian' => $validated['uraian'],
                'keterangan_kustom' => $validated['keterangan_kustom'] ?? null,
                'volume' => $totalVolume,
                'satuan' => $satuanUtama,
                'harga_satuan' => $hargaSatuan,
                'harga_satuan_arkas' => $hargaSatuan,
                'jumlah' => $totalJumlah,
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
    public function destroy($id)
    {
        $item = RkasItem::findOrFail($id);
        $item->delete();

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
}
