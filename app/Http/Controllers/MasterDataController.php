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
        $snp = trim($request->input('snp', ''));

        $query = MasterProgram::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhere('program', 'like', "%{$q}%")
                    ->orWhere('sub_program', 'like', "%{$q}%");
            });
        }
        if ($snp !== '') {
            $query->where('program', $snp);
        }

        $items = $query->orderBy('kode')->paginate(20)->withQueryString();
        $snpList = MasterProgram::select('program')->distinct()->whereNotNull('program')->where('program', '!=', '')->orderBy('program')->pluck('program');

        return view('master.program', compact('items', 'q', 'snp', 'snpList'));
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
        $jenis = $request->input('jenis', '');

        $query = MasterKodeRekening::query()->with('jenisBelanja');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhereHas('jenisBelanja', fn ($jb) => $jb->where('nama', 'like', "%{$q}%"));
            });
        }
        if ($jenis !== '') {
            if ($jenis === '__null') {
                $query->whereNull('jenis_belanja_id');
            } else {
                $query->where('jenis_belanja_id', $jenis);
            }
        }

        $items = $query->orderBy('kode')->paginate(30)->withQueryString();

        $jenisBelanjas = JenisBelanja::orderBy('nama')->get();

        return view('master.rekening', compact('items', 'q', 'jenis', 'jenisBelanjas'));
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
        $kategori = trim($request->input('kategori', ''));

        $query = KodeBarang::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhere('kode_rekening', 'like', "%{$q}%");
            });
        }
        if ($kategori !== '') {
            $query->where('kategori', $kategori);
        }

        $items = $query->orderBy('kode')->paginate(50)->withQueryString();
        $kategoriList = KodeBarang::select('kategori')->distinct()->whereNotNull('kategori')->where('kategori', '!=', '')->orderBy('kategori')->pluck('kategori');

        return view('master.barang', compact('items', 'q', 'kategori', 'kategoriList'));
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
            $errors = [];
            // Normalisasi header: BOM, trim, spasi->underscore, lowercase
            $header = array_map(function ($h) {
                $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
                $h = strtolower(trim((string) $h));
                $h = preg_replace('/\s+/', '_', $h);
                $h = preg_replace('/[^a-z0-9_]/', '', $h);
                return $h;
            }, $data[0]);

            // Helper ambil nilai via alias
            $get = function (array $map, array $keys, $default = null) {
                foreach ($keys as $k) {
                    if (isset($map[$k]) && trim((string) $map[$k]) !== '') {
                        return $map[$k];
                    }
                }
                return $default;
            };

            DB::beginTransaction();

            $rowNum = 1; // header = 1
            foreach (array_slice($data, 1) as $row) {
                $rowNum++;
                $row = array_pad($row, count($header), null);
                $rowMap = array_combine($header, $row);

                if (empty(array_filter($rowMap, fn ($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                switch ($request->target) {
                    case 'program':
                        $kode = trim((string) ($get($rowMap, ['kode_kegiatan', 'kode', 'kode_program'], '')));
                        $nama = trim((string) ($get($rowMap, ['uraian', 'nama', 'nama_kegiatan', 'nama_program'], '')));
                        if ($kode === '' || $nama === '') {
                            $skipped++;
                            $errors[] = "Baris {$rowNum}: kode/nama kosong — dilewati";

                            continue 2;
                        }
                        $created = MasterProgram::firstOrCreate(
                            ['kode' => rtrim($kode, '.')],
                            [
                                'nama' => $nama,
                                'program' => $get($rowMap, ['program', 'standar_snp', 'standarsnp', 'standar', 'snp'], null),
                                'sub_program' => $get($rowMap, ['sub_program', 'subprogram', 'sub'], null),
                            ]
                        );
                        break;

                    case 'rekening':
                        $kode = trim((string) ($get($rowMap, ['kode_barang', 'kode', 'kode_rekening', 'kode_rek'], '')));
                        $nama = trim((string) ($get($rowMap, ['rincian_objek', 'nama', 'uraian', 'nama_rekening'], '')));
                        if ($kode === '' || $nama === '') {
                            $skipped++;
                            $errors[] = "Baris {$rowNum}: kode/nama rekening kosong — dilewati";

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
                        $nama = trim((string) ($get($rowMap, ['nama', 'nama_barang', 'uraian', 'nama_barang_jasa'], '')));
                        if ($nama === '') {
                            $skipped++;
                            $errors[] = "Baris {$rowNum}: nama barang kosong — dilewati";

                            continue 2;
                        }
                        // Ambil semua varian ARKAS full
                        $kode = trim((string) ($get($rowMap, ['kode', 'kode_barang', 'kode_arkas'], '')));
                        $idBarang = trim((string) ($get($rowMap, ['id_barang_arkas', 'id_barang', 'id_arkas', 'kode_arkas'], '')));
                        $kodeRek = trim((string) ($get($rowMap, ['kode_rekening', 'kode_rek', 'rekening'], '')));
                        $satuan = trim((string) ($get($rowMap, ['satuan', 'satuan_default', 'satuan_barang'], '')));
                        $hargaAcuanRaw = $get($rowMap, ['harga', 'harga_acuan', 'harga_acuan_arkas', 'harga_satuan'], 0);
                        $hargaMinRaw = $get($rowMap, ['harga_min', 'hargamin', 'batas_bawah', 'harga_minimal'], 0);
                        $hargaMaxRaw = $get($rowMap, ['harga_max', 'hargamax', 'batas_atas', 'harga_maksimal'], 0);
                        // Validasi numeric
                        $hargaAcuanStr = trim((string) $hargaAcuanRaw);
                        $hargaMinStr = trim((string) $hargaMinRaw);
                        $hargaMaxStr = trim((string) $hargaMaxRaw);
                        if ($hargaAcuanStr !== '' && ! is_numeric(str_replace([',', '.'], '', $hargaAcuanStr)) && ! is_numeric($hargaAcuanStr)) {
                            // izinkan angka excel numeric, tapi jika string non-numeric beri warning
                            if (! is_numeric($hargaAcuanRaw)) {
                                $errors[] = "Baris {$rowNum}: harga_acuan bukan angka ({$hargaAcuanRaw}) — diisi 0";
                            }
                        }
                        $hargaAcuan = is_numeric($hargaAcuanRaw) ? (float) $hargaAcuanRaw : (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $hargaAcuanStr));
                        $hargaMin = is_numeric($hargaMinRaw) ? (float) $hargaMinRaw : (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $hargaMinStr));
                        $hargaMax = is_numeric($hargaMaxRaw) ? (float) $hargaMaxRaw : (float) str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $hargaMaxStr));
                        $kodeBelanja = trim((string) ($get($rowMap, ['kode_belanja', 'kodebelanja'], '')));
                        $kategori = trim((string) ($get($rowMap, ['kategori', 'kategori_barang'], '')));

                        // Unik: prioritas id_barang_arkas > kode > nama
                        if ($idBarang !== '') {
                            $created = KodeBarang::firstOrCreate(
                                ['id_barang_arkas' => $idBarang],
                                [
                                    'kode' => $kode !== '' ? $kode : $idBarang,
                                    'nama' => $nama,
                                    'kode_rekening' => $kodeRek ?: null,
                                    'satuan_default' => $satuan ?: null,
                                    'harga_acuan' => $hargaAcuan,
                                    'harga_min' => $hargaMin,
                                    'harga_max' => $hargaMax,
                                    'kode_belanja' => $kodeBelanja ?: null,
                                    'kategori' => $kategori ?: null,
                                ]
                            );
                        } elseif ($kode !== '') {
                            $created = KodeBarang::firstOrCreate(
                                ['kode' => $kode],
                                [
                                    'id_barang_arkas' => $idBarang ?: $kode,
                                    'nama' => $nama,
                                    'kode_rekening' => $kodeRek ?: null,
                                    'satuan_default' => $satuan ?: null,
                                    'harga_acuan' => $hargaAcuan,
                                    'harga_min' => $hargaMin,
                                    'harga_max' => $hargaMax,
                                    'kode_belanja' => $kodeBelanja ?: null,
                                    'kategori' => $kategori ?: null,
                                ]
                            );
                        } else {
                            $created = KodeBarang::firstOrCreate(
                                ['nama' => $nama],
                                [
                                    'kode' => null,
                                    'id_barang_arkas' => null,
                                    'kode_rekening' => $kodeRek ?: null,
                                    'satuan_default' => $satuan ?: null,
                                    'harga_acuan' => $hargaAcuan,
                                    'harga_min' => $hargaMin,
                                    'harga_max' => $hargaMax,
                                    'kode_belanja' => $kodeBelanja ?: null,
                                    'kategori' => $kategori ?: null,
                                ]
                            );
                        }
                        break;
                }

                $imported++;
            }

            DB::commit();

            $msg = "Import selesai: {$imported} baris diimpor, {$skipped} baris dilewati.";
            if (! empty($errors)) {
                $msg .= ' Rincian: '.implode('; ', array_slice($errors, 0, 5)).(count($errors) > 5 ? ' ... (+'.(count($errors)-5).' lagi)' : '');
            }

            return redirect()->route('master.'.$request->target)->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Import gagal: '.$e->getMessage()]);
        }
    }
}
