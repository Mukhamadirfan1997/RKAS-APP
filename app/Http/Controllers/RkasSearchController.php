<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use Illuminate\Http\Request;

class RkasSearchController extends Controller
{
    /**
     * Live search for Kegiatan (Program 8 SNP).
     */
    public function searchKegiatan(Request $request)
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

        $results = $query->limit(25)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'standar_snp' => $item->standar_snp,
                'text' => "[{$item->kode}] {$item->nama}",
                'subtext' => $item->standar_snp,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Live search for Kode Rekening Belanja.
     */
    public function searchRekening(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = MasterKodeRekening::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%");
            });
        }

        $results = $query->limit(25)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'kategori' => $item->kategori_belanja,
                'text' => "[{$item->kode}] {$item->nama}",
                'subtext' => "Kategori: {$item->kategori_belanja}",
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Live search for Katalog Kode Barang / Jasa.
     */
    public function searchBarang(Request $request)
    {
        $q = trim($request->input('q', ''));

        $query = KodeBarang::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%");
            });
        }

        $results = $query->limit(30)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'satuan' => $item->satuan_default ?? 'satuan',
                'harga' => (float) $item->harga_acuan,
                'text' => $item->nama,
                'subtext' => 'Satuan: '.($item->satuan_default ?? '-').' | Harga Acuan: Rp '.number_format($item->harga_acuan, 0, ',', '.'),
            ];
        });

        return response()->json(['results' => $results]);
    }
}
