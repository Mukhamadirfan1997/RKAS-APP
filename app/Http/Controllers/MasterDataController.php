<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MasterDataController extends Controller
{
    // ===================== PROGRAM =====================

    public function program(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = MasterProgram::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhere('standar_snp', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('kode')->get();

        return view('master.program', compact('items', 'q'));
    }

    public function storeProgram(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_program,kode',
            'nama' => 'required|string|max:255',
            'standar_snp' => 'nullable|string|max:150',
        ]);

        MasterProgram::create($validated);

        return redirect()->route('master.program')->with('success', 'Program/kegiatan berhasil ditambahkan.');
    }

    public function updateProgram(Request $request, $id)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_program,kode,'.$id,
            'nama' => 'required|string|max:255',
            'standar_snp' => 'nullable|string|max:150',
        ]);

        MasterProgram::findOrFail($id)->update($validated);

        return redirect()->route('master.program')->with('success', 'Program/kegiatan berhasil diperbarui.');
    }

    public function destroyProgram($id)
    {
        MasterProgram::findOrFail($id)->delete();

        return redirect()->route('master.program')->with('success', 'Program/kegiatan berhasil dihapus.');
    }

    // ===================== REKENING =====================

    public function rekening(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = MasterKodeRekening::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('kode')->get();

        return view('master.rekening', compact('items', 'q'));
    }

    public function storeRekening(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_kode_rekening,kode',
            'nama' => 'required|string|max:255',
            'kategori_belanja' => 'required|in:BARJAS,MODAL,HONOR',
        ]);

        MasterKodeRekening::create($validated);

        return redirect()->route('master.rekening')->with('success', 'Kode rekening berhasil ditambahkan.');
    }

    public function updateRekening(Request $request, $id)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_kode_rekening,kode,'.$id,
            'nama' => 'required|string|max:255',
            'kategori_belanja' => 'required|in:BARJAS,MODAL,HONOR',
        ]);

        MasterKodeRekening::findOrFail($id)->update($validated);

        return redirect()->route('master.rekening')->with('success', 'Kode rekening berhasil diperbarui.');
    }

    public function destroyRekening($id)
    {
        MasterKodeRekening::findOrFail($id)->delete();

        return redirect()->route('master.rekening')->with('success', 'Kode rekening berhasil dihapus.');
    }

    // ===================== KODE BARANG =====================

    public function barang(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = KodeBarang::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('kode')->paginate(50)->withQueryString();

        return view('master.barang', compact('items', 'q'));
    }

    public function storeBarang(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'nullable|string|max:100',
            'nama' => 'required|string|max:255',
            'satuan_default' => 'nullable|string|max:50',
            'harga_acuan' => 'nullable|numeric|min:0',
        ]);

        $validated['harga_acuan'] = $validated['harga_acuan'] ?? 0;

        KodeBarang::create($validated);

        return redirect()->route('master.barang')->with('success', 'Kode barang berhasil ditambahkan.');
    }

    public function updateBarang(Request $request, $id)
    {
        $validated = $request->validate([
            'kode' => 'nullable|string|max:100',
            'nama' => 'required|string|max:255',
            'satuan_default' => 'nullable|string|max:50',
            'harga_acuan' => 'nullable|numeric|min:0',
        ]);

        $validated['harga_acuan'] = $validated['harga_acuan'] ?? 0;

        KodeBarang::findOrFail($id)->update($validated);

        return redirect()->route('master.barang')->with('success', 'Kode barang berhasil diperbarui.');
    }

    public function destroyBarang($id)
    {
        KodeBarang::findOrFail($id)->delete();

        return redirect()->route('master.barang')->with('success', 'Kode barang berhasil dihapus.');
    }

    // ===================== IMPORT EXCEL =====================

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx,csv',
            'target' => 'required|in:program,rekening,barang',
        ]);

        try {
            $rows = Excel::toArray(new \StdClass, $request->file('file'));
            $data = $rows[0] ?? [];

            if (empty($data) || count($data) < 2) {
                return back()->withErrors(['error' => 'File kosong atau tidak memiliki data baris.']);
            }

            $imported = 0;
            $skipped = 0;
            $header = array_map('strtolower', $data[0]);

            DB::beginTransaction();

            foreach (array_slice($data, 1) as $row) {
                $row = array_pad($row, count($header), null);
                $rowMap = array_combine($header, $row);

                if (empty(array_filter($rowMap))) {
                    continue;
                }

                switch ($request->target) {
                    case 'program':
                        if (empty($rowMap['kode'] ?? null) || empty($rowMap['nama'] ?? null)) {
                            $skipped++;

                            continue 2;
                        }
                        $created = MasterProgram::firstOrCreate(
                            ['kode' => trim($rowMap['kode'])],
                            [
                                'nama' => trim($rowMap['nama']),
                                'standar_snp' => $rowMap['standar_snp'] ?? $rowMap['standarsnp'] ?? null,
                            ]
                        );
                        break;

                    case 'rekening':
                        if (empty($rowMap['kode'] ?? null) || empty($rowMap['nama'] ?? null)) {
                            $skipped++;

                            continue 2;
                        }
                        $kategori = strtoupper((string) ($rowMap['kategori'] ?? $rowMap['kategori_belanja'] ?? 'BARJAS'));
                        if (! in_array($kategori, ['BARJAS', 'MODAL', 'HONOR'])) {
                            $kategori = 'BARJAS';
                        }
                        $created = MasterKodeRekening::firstOrCreate(
                            ['kode' => trim($rowMap['kode'])],
                            [
                                'nama' => trim($rowMap['nama']),
                                'kategori_belanja' => $kategori,
                            ]
                        );
                        break;

                    case 'barang':
                        if (empty($rowMap['nama'] ?? null)) {
                            $skipped++;

                            continue 2;
                        }
                        $created = KodeBarang::firstOrCreate(
                            ['nama' => trim($rowMap['nama'])],
                            [
                                'kode' => $rowMap['kode'] ?? null,
                                'satuan_default' => $rowMap['satuan'] ?? $rowMap['satuan_default'] ?? null,
                                'harga_acuan' => (float) ($rowMap['harga'] ?? $rowMap['harga_acuan'] ?? 0),
                            ]
                        );
                        break;
                }

                $imported++;
            }

            DB::commit();

            return redirect()->route('master.'.$request->target)->with(
                'success',
                "Import selesai: {$imported} baris diimpor, {$skipped} baris dilewati."
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Import gagal: '.$e->getMessage()]);
        }
    }
}
