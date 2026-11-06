<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RkasSearchController extends Controller
{
    /**
     * Batas hasil per permintaan (dipakai juga oleh infinite scroll di modal).
     */
    private const BATAS_MAKS = 100;

    /**
     * Live search for Kegiatan (Program 8 SNP).
     */
    public function searchKegiatan(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $query = MasterProgram::query();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhere('program', 'like', "%{$q}%")
                    ->orWhere('sub_program', 'like', "%{$q}%");
            });
        }
        // Urutan harus deterministik agar paginasi (scroll) tidak menggandakan
        // atau melewatkan baris saat ada nilai yang sama.
        $query->orderBy('kode')->orderBy('id');

        [$limit, $offset] = $this->limitOffset($request, 25);

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($limit + 1)->get();

        $hasil = $rows->take($limit)->map(function ($item) {
            return [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'program' => $item->program,
                'sub_program' => $item->sub_program,
                'text' => "[{$item->kode}] {$item->nama}",
                'subtext' => trim(($item->sub_program ?? $item->program ?? '')),
            ];
        })->values();

        return response()->json($this->bingkai($hasil, $rows, $limit, $offset, $total));
    }

    /**
     * Live search for Kode Rekening Belanja.
     */
    public function searchRekening(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $query = MasterKodeRekening::query()->with('jenisBelanja');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode', 'like', "%{$q}%")
                    ->orWhere('nama', 'like', "%{$q}%")
                    ->orWhereHas('jenisBelanja', fn ($jb) => $jb->where('nama', 'like', "%{$q}%"));
            });
        }
        // Urutan harus deterministik agar paginasi (scroll) tidak menggandakan
        // atau melewatkan baris saat ada nilai yang sama.
        $query->orderBy('kode')->orderBy('id');

        [$limit, $offset] = $this->limitOffset($request, 25);

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($limit + 1)->get();

        $hasil = $rows->take($limit)->map(function ($item) {
            return [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'kategori' => $item->jenisBelanja->nama ?? $item->kategori_belanja,
                'text' => "[{$item->kode}] {$item->nama}",
                'subtext' => 'Jenis Belanja: '.($item->jenisBelanja->nama ?? '-'),
            ];
        })->values();

        return response()->json($this->bingkai($hasil, $rows, $limit, $offset, $total));
    }

    /**
     * Live search for Katalog Kode Barang / Jasa.
     * Opsional: filter by kode_rekening (exact) agar barang sesuai rekening terpilih.
     *
     * Kata kunci dibuat longgar (30 Okt 2026):
     * - boleh beberapa kata, urutan bebas: "hvs a4 kertas" = "kertas hvs a4";
     * - tiap kata dicocokkan ke nama, kode barang, dan kode rekening;
     * - tidak harus di awal kata ("snowman" menemukan "Spidol Whiteboard Snowman");
     * - hasil yang diawali kata kunci ditampilkan lebih dulu.
     *
     * Mendukung offset untuk infinite scroll. Total sengaja tidak dihitung
     * (tabel 91 ribu baris): gunakan `has_more` untuk memuat halaman berikutnya.
     */
    public function searchBarang(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $kodeRekening = trim((string) $request->input('kode_rekening', ''));

        $query = KodeBarang::query();
        if ($kodeRekening !== '') {
            $query->where('kode_rekening', $kodeRekening);
        }

        // Pecah jadi kata; buang wildcard agar '%'/'_' tidak jadi pola aneh.
        $kata = collect(preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($t) => trim(str_replace(['%', '_'], '', $t)))
            ->filter(fn ($t) => $t !== '')
            ->take(6)
            ->values()
            ->all();

        if ($kata !== []) {
            $query->where(function ($sub) use ($kata) {
                foreach ($kata as $tok) {
                    // AND antar kata, OR antar kolom: semua kata harus muncul.
                    $sub->where(function ($w) use ($tok) {
                        $w->where('nama', 'like', "%{$tok}%")
                            ->orWhere('kode', 'like', "%{$tok}%")
                            ->orWhere('kode_rekening', 'like', "%{$tok}%");
                    });
                }
            });

            // Relevansi: yang berawalan kata pertama tampil paling atas.
            $utama = $kata[0];
            $query->orderByRaw(
                'CASE WHEN nama LIKE ? THEN 0 WHEN nama LIKE ? THEN 1 ELSE 2 END',
                [$utama.'%', '% '.$utama.'%']
            );
        }
        $query->orderBy('nama')->orderBy('id');

        [$limit, $offset] = $this->limitOffset($request, 30);

        $rows = $query->offset($offset)->limit($limit + 1)->get();

        $hasil = $rows->take($limit)->map(function ($item) {
            $sshInfo = '';
            if ((float) $item->harga_min > 0 || (float) $item->harga_max > 0) {
                $sshInfo = ' | SSH: Rp '.number_format((float) $item->harga_min, 0, ',', '.').' - Rp '.number_format((float) $item->harga_max, 0, ',', '.');
            }

            return [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'satuan' => $item->satuan_default ?? 'satuan',
                'harga' => (float) $item->harga_acuan,
                'harga_min' => (float) ($item->harga_min ?? 0),
                'harga_max' => (float) ($item->harga_max ?? 0),
                'text' => $item->nama,
                'subtext' => 'Satuan: '.($item->satuan_default ?? '-').' | Acuan: Rp '.number_format((float) $item->harga_acuan, 0, ',', '.').$sshInfo,
            ];
        })->values();

        return response()->json($this->bingkai($hasil, $rows, $limit, $offset, null));
    }

    /**
     * @return array{0:int,1:int} [limit, offset]
     */
    private function limitOffset(Request $request, int $bawaan): array
    {
        $limit = (int) $request->input('limit', $bawaan);
        $limit = max(1, min($limit, self::BATAS_MAKS));

        $offset = max(0, (int) $request->input('offset', 0));

        return [$limit, $offset];
    }

    /**
     * Bentuk respons standar (kompatibel dengan pemakaian lama: `results`).
     *
     * @param  Collection  $hasil
     * @param  Collection  $rows  baris mentah (limit + 1)
     */
    private function bingkai($hasil, $rows, int $limit, int $offset, ?int $total): array
    {
        $hasMore = $rows->count() > $limit;

        return [
            'results' => $hasil,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $hasMore,
        ];
    }
}
