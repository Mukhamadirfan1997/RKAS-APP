<?php

namespace App\Http\Controllers;

use App\Models\JenisBelanja;
use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Services\JenisBelanjaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MasterDataController extends Controller
{
    protected JenisBelanjaResolver $resolver;

    public function __construct()
    {
        $this->resolver = app(JenisBelanjaResolver::class);
    }
    // ===================== PROGRAM =====================

    public function program(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = MasterProgram::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhere('program', 'like', "%{$q}%")
                    ->orWhere('sub_program', 'like', "%{$q}%");
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
            'program' => 'nullable|string|max:150',
            'sub_program' => 'nullable|string|max:150',
        ]);

        MasterProgram::create($validated);

        return redirect()->route('master.program')->with('success', 'Program/kegiatan berhasil ditambahkan.');
    }

    public function updateProgram(Request $request, $id)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_program,kode,'.$id,
            'nama' => 'required|string|max:255',
            'program' => 'nullable|string|max:150',
            'sub_program' => 'nullable|string|max:150',
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

        $query = MasterKodeRekening::query()->with('jenisBelanja');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhereHas('jenisBelanja', fn ($jb) => $jb->where('nama', 'like', "%{$q}%"));
            });
        }

        $items = $query->orderBy('kode')->get();

        $jenisBelanjas = JenisBelanja::orderBy('nama')->get();

        return view('master.rekening', compact('items', 'q', 'jenisBelanjas'));
    }

    public function storeRekening(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_kode_rekening,kode',
            'nama' => 'required|string|max:255',
            'jenis_belanja_id' => 'nullable|exists:jenis_belanja,id',
            'kategori_belanja' => 'nullable|in:BARJAS,MODAL,HONOR',
        ]);

        MasterKodeRekening::create($validated);

        return redirect()->route('master.rekening')->with('success', 'Kode rekening berhasil ditambahkan.');
    }

    public function updateRekening(Request $request, $id)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:master_kode_rekening,kode,'.$id,
            'nama' => 'required|string|max:255',
            'jenis_belanja_id' => 'nullable|exists:jenis_belanja,id',
            'kategori_belanja' => 'nullable|in:BARJAS,MODAL,HONOR',
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
                        $kode = trim((string) ($rowMap['kode_kegiatan'] ?? $rowMap['kode'] ?? ''));
                        $nama = trim((string) ($rowMap['uraian'] ?? $rowMap['nama'] ?? ''));
                        if ($kode === '' || $nama === '') {
                            $skipped++;

                            continue 2;
                        }
                        $created = MasterProgram::firstOrCreate(
                            ['kode' => rtrim($kode, '.')],
                            [
                                'nama' => $nama,
                                'program' => $rowMap['program'] ?? $rowMap['standar_snp'] ?? $rowMap['standarsnp'] ?? null,
                                'sub_program' => $rowMap['sub_program'] ?? null,
                            ]
                        );
                        break;

                    case 'rekening':
                        $kode = trim((string) ($rowMap['kode_barang'] ?? $rowMap['kode'] ?? ''));
                        $nama = trim((string) ($rowMap['rincian_objek'] ?? $rowMap['nama'] ?? ''));
                        if ($kode === '' || $nama === '') {
                            $skipped++;

                            continue 2;
                        }
                        $kode = rtrim($kode, '.');
                        $namaJenis = $this->resolver->namaRekening($kode);
                        $created = MasterKodeRekening::firstOrCreate(
                            ['kode' => $kode],
                            [
                                'nama' => $nama,
                                'jenis_belanja_id' => $this->resolver->jenisId($kode),
                                'kategori_belanja' => $this->resolver->legacyKategori($namaJenis),
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
