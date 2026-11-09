<?php

namespace App\Services;

use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use Illuminate\Support\Str;

/**
 * Penilai Kesiapan RKAS — satu sumber kebenaran untuk:
 *  - skor kesiapan di Dashboard,
 *  - daftar masalah yang bisa diklik (halaman Kesiapan RKAS),
 *  - gerbang sebelum pengesahan (PengaturanController::sahkan()).
 *
 * Semua pemeriksaan bersifat "bisa diperbaiki operator": setiap kegagalan
 * membawa tautan langsung ke halaman/item yang perlu dibenahi. Nominal selalu
 * dihitung ulang dari database (bukan dari request) agar tidak bisa diakali.
 */
class KesiapanRkasService
{
    /** Maksimum baris masalah yang ditampilkan per pemeriksaan. */
    public const BATAS_MASALAH = 25;

    protected TahunAnggaran $tahun;

    protected float $paguTotal = 0;

    public function __construct(?TahunAnggaran $tahun = null)
    {
        $this->tahun = $tahun
            ?? TahunAnggaran::where('is_active', true)->first()
            ?? TahunAnggaran::orderBy('tahun', 'desc')->first()
            ?? new TahunAnggaran(['tahun' => 2026, 'pagu_total' => 0]);

        $this->paguTotal = (float) ($this->tahun->pagu_total ?? 0);
    }

    public function tahun(): TahunAnggaran
    {
        return $this->tahun;
    }

    /**
     * @return array{
     *     tahun:int, pagu_total:float, sudah_dianggarkan:float, sisa:float,
     *     checks:array<int, array<string, mixed>>, ok_count:int, total:int,
     *     skor:int, siap:bool, gagal:array<int, string>, masalah:array<int, array<string, mixed>>
     * }
     */
    public function evaluasi(): array
    {
        $items = RkasItem::with(['program', 'kodeRekening', 'barang', 'alokasiBulan'])
            ->where('tahun_anggaran_id', $this->tahun->id)
            ->orderBy('no_urut')
            ->get();

        $validator = new JuknisValidator($this->tahun);
        $summary = $validator->summary();

        $sudah = (float) $summary['sudah_dianggarkan'];
        $sisa = $this->paguTotal - $sudah;

        $checks = [];

        // Sebagian syarat tidak bermakna bila datanya belum ada. Syarat semacam
        // itu ditandai "belum bisa dinilai" dan TIDAK ikut dihitung pada skor —
        // supaya instalasi baru (pagu Rp 0, belum ada item) tidak menampilkan
        // skor tinggi yang menyesatkan.
        $dinilaiPagu = $this->paguTotal > 0;
        $dinilaiItem = $items->isNotEmpty();
        $alasanPagu = 'isi Pagu Total 1 tahun dulu';
        $alasanItem = 'belum ada uraian anggaran di lembar kerja';
        // Syarat JUKNIS berbicara tentang persentase terhadap pagu DAN isi
        // anggaran — keduanya harus ada agar penilaian bermakna.
        $dinilaiJuknis = $dinilaiPagu && $dinilaiItem;
        $alasanJuknis = $dinilaiPagu ? $alasanItem : $alasanPagu;

        // 1. Pagu dasar harus terisi — tanpa pagu, semua persentase JUKNIS
        //    tidak bermakna dan RKAS tidak mungkin dinilai.
        $checks[] = $this->cek(
            'pagu_terisi',
            'Pagu Total 1 tahun sudah diisi',
            $this->paguTotal > 0,
            $this->paguTotal > 0 ? $this->rupiah($this->paguTotal) : 'Pagu Total masih Rp 0',
            route('pengaturan.pagu', ['tahun' => $this->tahun->tahun]),
            $this->paguTotal > 0 ? [] : [[
                'judul' => 'Pagu Total 1 tahun belum diisi',
                'detail' => 'Isi Pagu Total, lalu bagi ke Tahap I dan Tahap II (masing-masing maksimal 50%).',
                'tautan' => route('pengaturan.pagu', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Isi pagu',
            ]]
        );

        // 2. Pagu per tahap maksimal 50% dari pagu 1 tahun (BOSP 2 tahap).
        $tahap1 = (float) ($this->tahun->pagu_tahap1 ?? 0);
        $tahap2 = (float) ($this->tahun->pagu_tahap2 ?? 0);
        $batasTahap = $this->paguTotal / 2;
        $masalahTahap = [];
        foreach ([['I', $tahap1], ['II', $tahap2]] as [$label, $nominal]) {
            if ($this->paguTotal > 0 && $nominal > $batasTahap + 0.5) {
                $masalahTahap[] = [
                    'judul' => "Pagu Tahap {$label} melebihi 50%",
                    'detail' => $this->rupiah($nominal).' dari pagu '.$this->rupiah($this->paguTotal).' (maksimal '.$this->rupiah($batasTahap).')',
                    'tautan' => route('pengaturan.pagu', ['tahun' => $this->tahun->tahun]),
                    'aksi' => 'Perbaiki pagu',
                ];
            }
        }
        $checks[] = $this->cek(
            'pagu_tahap_batas',
            'Pagu Tahap I & II masing-masing maksimal 50%',
            $masalahTahap === [],
            'Tahap I '.$this->rupiah($tahap1).' · Tahap II '.$this->rupiah($tahap2),
            route('pengaturan.pagu', ['tahun' => $this->tahun->tahun]),
            $masalahTahap,
            $dinilaiPagu,
            $alasanPagu
        );

        // 3. Pagu Tahap I + Tahap II harus tepat sama dengan pagu 1 tahun.
        $selisihTahap = round($tahap1 + $tahap2 - $this->paguTotal, 2);
        $checks[] = $this->cek(
            'pagu_tahap_total',
            'Pagu Tahap I + Tahap II = Pagu Total',
            abs($selisihTahap) < 0.5,
            'Jumlah tahap '.$this->rupiah($tahap1 + $tahap2).' vs pagu '.$this->rupiah($this->paguTotal)
                .(abs($selisihTahap) < 0.5 ? '' : ' (selisih '.$this->rupiah(abs($selisihTahap)).')'),
            route('pengaturan.pagu', ['tahun' => $this->tahun->tahun]),
            abs($selisihTahap) < 0.5 ? [] : [[
                'judul' => 'Jumlah dua tahap tidak sama dengan pagu 1 tahun',
                'detail' => 'Tahap I '.$this->rupiah($tahap1).' + Tahap II '.$this->rupiah($tahap2)
                    .' = '.$this->rupiah($tahap1 + $tahap2).', seharusnya '.$this->rupiah($this->paguTotal).'.',
                'tautan' => route('pengaturan.pagu', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Perbaiki pagu',
            ]],
            $dinilaiPagu,
            $alasanPagu
        );

        // 4. Seluruh pagu harus teranggarkan tepat (sisa Rp 0, tidak kurang/lebih).
        $masalahSisa = [];
        if ($this->paguTotal > 0 && abs($sisa) >= 0.5) {
            $masalahSisa[] = [
                'judul' => $sisa > 0 ? 'Masih ada pagu yang belum dianggarkan' : 'Anggaran melebihi pagu',
                'detail' => 'Sudah dianggarkan '.$this->rupiah($sudah).' dari '.$this->rupiah($this->paguTotal)
                    .' — '.($sisa > 0 ? 'kurang '.$this->rupiah($sisa) : 'lebih '.$this->rupiah(abs($sisa))).'.',
                'tautan' => route('rkas.index', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Buka lembar kerja',
            ];
        }
        $checks[] = $this->cek(
            'sisa_pagu',
            'Seluruh pagu teranggarkan (sisa Rp 0)',
            $this->paguTotal > 0 && abs($sisa) < 0.5,
            $this->paguTotal > 0 ? 'Sisa '.$this->rupiah($sisa) : 'Pagu Total masih Rp 0',
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            $masalahSisa,
            $dinilaiPagu,
            $alasanPagu
        );

        // 5–8. Kepatuhan JUKNIS (Permendikdasmen No. 8/2026).
        $honor = $summary['honor'];
        $checks[] = $this->cek(
            'juknis_honor',
            'Honor tidak melebihi batas ('.$this->angka($honor['batas_persen']).'%)',
            $honor['status'] === 'sesuai',
            $this->rupiah($honor['total']).' ('.$this->angka($honor['persen']).'%) · batas '.$this->rupiah($honor['batas_nominal']),
            route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
            $honor['status'] === 'sesuai' ? [] : [[
                'judul' => 'Honor melebihi batas '.$this->angka($honor['batas_persen']).'%',
                'detail' => $this->rupiah($honor['total']).' ('.$this->angka($honor['persen']).'%) — kurangi '
                    .$this->rupiah($honor['total'] - $honor['batas_nominal']).' agar sesuai.',
                'tautan' => route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Lihat monitoring',
            ]],
            $dinilaiJuknis,
            $alasanJuknis
        );

        $buku = $summary['buku'];
        $checks[] = $this->cek(
            'juknis_buku',
            'Anggaran buku memenuhi minimum ('.$this->angka($buku['batas_persen']).'%)',
            $buku['status'] === 'sesuai',
            $this->rupiah($buku['total']).' ('.$this->angka($buku['persen']).'%) · minimal '.$this->rupiah($buku['batas_nominal']),
            route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
            $buku['status'] === 'sesuai' ? [] : [[
                'judul' => 'Anggaran buku kurang dari '.$this->angka($buku['batas_persen']).'%',
                'detail' => 'Baru '.$this->rupiah($buku['total']).' ('.$this->angka($buku['persen']).'%), minimal '
                    .$this->rupiah($buku['batas_nominal']).' — tambah '.$this->rupiah(max(0, $buku['sisa'])).'.',
                'tautan' => route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Lihat monitoring',
            ]],
            $dinilaiJuknis,
            $alasanJuknis
        );

        $sarpras = $summary['sarpras'];
        $checks[] = $this->cek(
            'juknis_sarpras',
            'Sarpras (pemeliharaan) tidak melebihi batas ('.$this->angka($sarpras['batas_persen']).'%)',
            $sarpras['status'] === 'sesuai',
            $this->rupiah($sarpras['total']).' ('.$this->angka($sarpras['persen']).'%) · batas '.$this->rupiah($sarpras['batas_nominal']),
            route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
            $sarpras['status'] === 'sesuai' ? [] : [[
                'judul' => 'Sarpras melebihi batas '.$this->angka($sarpras['batas_persen']).'%',
                'detail' => $this->rupiah($sarpras['total']).' ('.$this->angka($sarpras['persen']).'%) — kurangi '
                    .$this->rupiah($sarpras['total'] - $sarpras['batas_nominal']).'.',
                'tautan' => route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Lihat monitoring',
            ]],
            $dinilaiJuknis,
            $alasanJuknis
        );

        // 8. Jatah 50% per tahap. JUKNIS menuntut alokasi Tahap I minimal 50%,
        //    sementara penyaluran hanya menyediakan 50% kas per tahap — jadi
        //    rencana Tahap I TIDAK boleh melebihi 50% (bukan hanya "minimal").
        $tahap1Juknis = $summary['tahap1'];
        $masalahTahap1 = [];
        if ($tahap1Juknis['status'] === 'kurang') {
            $masalahTahap1[] = [
                'judul' => 'Alokasi Tahap I kurang dari 50%',
                'detail' => 'Baru '.$this->rupiah($tahap1Juknis['total']).' ('.$this->angka($tahap1Juknis['persen'])
                    .'%), minimal '.$this->rupiah($tahap1Juknis['batas_nominal']).'.',
                'tautan' => route('rkas.index', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Buka lembar kerja',
            ];
        } elseif ($tahap1Juknis['status'] === 'melebihi') {
            $masalahTahap1[] = [
                'judul' => 'Alokasi Tahap I melebihi jatah 50%',
                'detail' => $this->rupiah($tahap1Juknis['total']).' ('.$this->angka($tahap1Juknis['persen'])
                    .'%) melebihi jatah Tahap I '.$this->rupiah($tahap1Juknis['batas_maks_nominal'])
                    .' — kelebihan '.$this->rupiah($tahap1Juknis['kelebihan'])
                    .'. Pindahkan item/kegiatan tersebut ke bulan Juli–Desember (Tahap II).',
                'tautan' => route('rkas.index', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Atur alokasi bulan',
            ];
        }
        $checks[] = $this->cek(
            'juknis_tahap1',
            'Alokasi Tahap I (Jan–Jun) tepat 50% dari pagu',
            $tahap1Juknis['status'] === 'sesuai',
            $this->rupiah($tahap1Juknis['total']).' ('.$this->angka($tahap1Juknis['persen']).'%) · jatah '
                .$this->rupiah($tahap1Juknis['batas_maks_nominal']),
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            $masalahTahap1,
            $dinilaiJuknis,
            $alasanJuknis
        );

        // 8b. Tahap II juga hanya dijatah 50% — mencegah rencana semester 2
        //     melampaui dana yang cair pada Tahap II.
        $tahap2Juknis = $summary['tahap2'];
        $masalahTahap2 = [];
        if ($tahap2Juknis['status'] === 'melebihi') {
            $masalahTahap2[] = [
                'judul' => 'Alokasi Tahap II melebihi jatah 50%',
                'detail' => $this->rupiah($tahap2Juknis['total']).' ('.$this->angka($tahap2Juknis['persen'])
                    .'%) melebihi jatah Tahap II '.$this->rupiah($tahap2Juknis['batas_maks_nominal'])
                    .' — kelebihan '.$this->rupiah($tahap2Juknis['kelebihan']).'.',
                'tautan' => route('rkas.index', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Atur alokasi bulan',
            ];
        }
        $checks[] = $this->cek(
            'juknis_tahap2',
            'Alokasi Tahap II (Jul–Des) maksimal 50% dari pagu',
            $tahap2Juknis['status'] === 'sesuai',
            $this->rupiah($tahap2Juknis['total']).' ('.$this->angka($tahap2Juknis['persen']).'%) · jatah '
                .$this->rupiah($tahap2Juknis['batas_maks_nominal']),
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            $masalahTahap2,
            $dinilaiJuknis,
            $alasanJuknis
        );

        // 9. Setiap item wajib punya kegiatan (program) & kode rekening.
        $tanpaKegiatan = $items->filter(fn (RkasItem $i) => ! $i->program || ! $i->kodeRekening);
        $masalahKegiatan = $tanpaKegiatan->take(self::BATAS_MASALAH)->map(fn (RkasItem $i) => [
            'judul' => $i->uraian ?: '(tanpa uraian)',
            'detail' => 'Kegiatan: '.($i->program->nama ?? '— belum dipilih —').' · Rekening: '.($i->kodeRekening->kode ?? '— belum dipilih —'),
            'tautan' => $this->tautanItem($i),
            'aksi' => 'Perbaiki item',
        ])->all();
        $checks[] = $this->cek(
            'item_lengkap',
            'Semua item punya kegiatan & kode rekening',
            $tanpaKegiatan->isEmpty(),
            $tanpaKegiatan->isEmpty() ? 'Semua '.$items->count().' item lengkap' : $tanpaKegiatan->count().' item belum lengkap',
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            $this->batasi($masalahKegiatan, $tanpaKegiatan->count()),
            $dinilaiItem,
            $alasanItem
        );

        // 10. Tidak ada item bernilai Rp 0 / tanpa alokasi bulan.
        $kosong = $items->filter(function (RkasItem $i) {
            $volumeAlokasi = (float) $i->alokasiBulan->sum('volume');

            return (float) $i->jumlah <= 0 || $volumeAlokasi <= 0;
        });
        $masalahKosong = $kosong->take(self::BATAS_MASALAH)->map(fn (RkasItem $i) => [
            'judul' => $i->uraian ?: '(tanpa uraian)',
            'detail' => 'Jumlah '.$this->rupiah((float) $i->jumlah).' · volume bulanan '.(float) $i->alokasiBulan->sum('volume')
                .' '.(string) $i->satuan,
            'tautan' => $this->tautanItem($i),
            'aksi' => 'Isi volume',
        ])->all();
        $checks[] = $this->cek(
            'item_nilai',
            'Tidak ada item Rp 0 / tanpa volume bulan',
            $kosong->isEmpty(),
            $kosong->isEmpty() ? 'Semua item bernilai & beralokasi' : $kosong->count().' item bernilai Rp 0 / tanpa volume',
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            $this->batasi($masalahKosong, $kosong->count()),
            $dinilaiItem,
            $alasanItem
        );

        // 11. Tidak ada harga di atas batas atas SSH (KONTROL MELEBIHI).
        $melebihi = $items->filter(fn (RkasItem $i) => $i->kontrol === 'MELEBIHI');
        $masalahSsh = $melebihi->take(self::BATAS_MASALAH)->map(fn (RkasItem $i) => [
            'judul' => $i->uraian ?: '(tanpa uraian)',
            'detail' => 'Harga '.$this->rupiah((float) $i->harga_satuan).' melebihi batas atas SSH '.$this->rupiah($i->batas_ssh),
            'tautan' => $this->tautanItem($i),
            'aksi' => 'Turunkan harga',
        ])->all();
        $checks[] = $this->cek(
            'ssh',
            'Tidak ada harga di atas batas atas SSH',
            $melebihi->isEmpty(),
            $melebihi->isEmpty() ? 'Semua harga dalam batas SSH' : $melebihi->count().' item melebihi batas atas SSH',
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            $this->batasi($masalahSsh, $melebihi->count()),
            $dinilaiItem,
            $alasanItem
        );

        // 12. Tidak ada uraian kosong atau baris kembar (tak bisa dibedakan).
        $masalahUraian = [];
        $tanpaUraian = $items->filter(fn (RkasItem $i) => trim((string) $i->uraian) === '');
        foreach ($tanpaUraian->take(self::BATAS_MASALAH) as $item) {
            $masalahUraian[] = [
                'judul' => '(uraian kosong)',
                'detail' => 'Kegiatan '.($item->program->kode ?? '—').' · rekening '.($item->kodeRekening->kode ?? '—'),
                'tautan' => $this->tautanItem($item),
                'aksi' => 'Isi uraian',
            ];
        }

        $kembar = $items
            ->filter(fn (RkasItem $i) => trim((string) $i->uraian) !== '')
            ->groupBy(fn (RkasItem $i) => implode('|', [
                Str::lower(Str::squish((string) $i->uraian)),
                Str::lower(Str::squish((string) $i->keterangan_kustom)),
                (string) $i->master_kode_rekening_id,
                (string) $i->master_program_id,
                (string) round((float) $i->harga_satuan, 2),
                Str::lower(trim((string) $i->satuan)),
            ]))
            ->filter(fn ($grup) => $grup->count() > 1);

        foreach ($kembar as $grup) {
            $pertama = $grup->first();
            $masalahUraian[] = [
                'judul' => Str::limit((string) $pertama->uraian, 70),
                'detail' => $grup->count().' baris identik (uraian, keterangan, rekening, harga, satuan sama) — gabungkan atau beri keterangan khusus pembeda.',
                'tautan' => $this->tautanItem($pertama),
                'aksi' => 'Periksa baris',
            ];
        }

        $checks[] = $this->cek(
            'uraian',
            'Tidak ada uraian kosong / baris kembar',
            $masalahUraian === [],
            $masalahUraian === [] ? 'Uraian lengkap & tidak ada kembaran' : count($masalahUraian).' temuan uraian',
            route('rkas.index', ['tahun' => $this->tahun->tahun]),
            array_slice($masalahUraian, 0, self::BATAS_MASALAH),
            $dinilaiItem,
            $alasanItem
        );

        // 13. RKA Gelondongan — hanya dinilai bila sekolah mengisi target Dinas.
        $gelondongan = RkaGelondonganService::calculate($this->tahun);
        $hasTarget = $this->tahun->target_barjas !== null
            || $this->tahun->target_modal_mesin !== null
            || $this->tahun->target_modal_aset !== null;
        if ($hasTarget) {
            $gagalTarget = [];
            foreach ([
                'barang_jasa' => 'Barang Jasa',
                'modal_mesin' => 'Modal Mesin',
                'modal_aset_lainnya' => 'Modal Aset Lainnya',
            ] as $kunci => $nama) {
                if (($gelondongan['status_per_row'][$kunci] ?? 'belum_diisi') !== 'sesuai') {
                    $gagalTarget[] = [
                        'judul' => 'Kategori '.$nama.' belum sesuai target',
                        'detail' => 'Realisasi '.$this->rupiah((float) ($gelondongan['realisasis'][$kunci] ?? 0))
                            .' vs target '.$this->rupiah((float) ($gelondongan['targets'][$kunci] ?? 0)).'.',
                        'tautan' => route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
                        'aksi' => 'Lihat monitoring',
                    ];
                }
            }
            $checks[] = $this->cek(
                'gelondongan',
                '3 kategori RKA sesuai target Dinas',
                $gagalTarget === [],
                $gagalTarget === [] ? 'Semua kategori sesuai target' : count($gagalTarget).' kategori belum sesuai',
                route('monitoring.juknis', ['tahun' => $this->tahun->tahun]),
                $gagalTarget
            );
        }

        // Skor hanya dihitung dari syarat yang memang bisa dinilai. Bila belum
        // ada satu pun item anggaran, kesiapan = 0% (bukan warisan "lulus
        // otomatis" dari pemeriksaan yang kosong).
        $dinilai = collect($checks)->where('dinilai', true)->values();
        $belumDinilai = collect($checks)->where('dinilai', false)->values();
        $total = $dinilai->count();
        $okCount = $dinilai->where('ok', true)->count();
        $skor = $dinilaiItem && $total > 0 ? (int) round($okCount / $total * 100) : 0;

        return [
            'tahun' => (int) $this->tahun->tahun,
            'pagu_total' => $this->paguTotal,
            'sudah_dianggarkan' => $sudah,
            'sisa' => $sisa,
            'jumlah_item' => $items->count(),
            'belum_ada_item' => $items->isEmpty(),
            'checks' => $checks,
            'ok_count' => $okCount,
            'total' => $total,
            'skor' => $skor,
            'siap' => $dinilaiItem && $total > 0 && $okCount === $total && $belumDinilai->isEmpty(),
            'gagal' => $dinilai->where('ok', false)->pluck('label')->values()->all(),
            'belum_dinilai' => $belumDinilai->pluck('label')->values()->all(),
            'masalah' => $dinilai
                ->where('ok', false)
                ->flatMap(fn (array $c) => $c['masalah'])
                ->values()
                ->all(),
        ];
    }

    /**
     * Tautan "cari item ini" di lembar kerja (filter pencarian data terinput).
     */
    protected function tautanItem(RkasItem $item): string
    {
        $kunci = trim((string) ($item->uraian ?: ''));

        return route('rkas.index', array_filter([
            'tahun' => $this->tahun->tahun,
            'q' => $kunci !== '' ? Str::limit($kunci, 60, '') : (string) $item->no_urut,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $masalah
     * @return array<int, array<string, mixed>>
     */
    protected function batasi(array $masalah, int $jumlahAsli): array
    {
        if ($jumlahAsli > count($masalah)) {
            $masalah[] = [
                'judul' => '… dan '.($jumlahAsli - count($masalah)).' item lainnya',
                'detail' => 'Gunakan pencarian di lembar kerja untuk menelusuri sisanya.',
                'tautan' => route('rkas.index', ['tahun' => $this->tahun->tahun]),
                'aksi' => 'Buka lembar kerja',
            ];
        }

        return $masalah;
    }

    /**
     * @param  array<int, array<string, mixed>>  $masalah
     * @param  bool  $dinilai  false = syarat belum relevan (data belum ada) → tidak ikut skor
     * @return array<string, mixed>
     */
    protected function cek(string $kunci, string $label, bool $ok, string $ringkas, string $tautan, array $masalah = [], bool $dinilai = true, string $alasanBelum = ''): array
    {
        if (! $dinilai) {
            return [
                'kunci' => $kunci,
                'label' => $label,
                'ok' => false,
                'dinilai' => false,
                'ringkas' => 'Belum bisa dinilai — '.$alasanBelum,
                'tautan' => $tautan,
                'masalah' => [],
            ];
        }

        return [
            'kunci' => $kunci,
            'label' => $label,
            'ok' => $ok,
            'dinilai' => true,
            'ringkas' => $ringkas,
            'tautan' => $tautan,
            // array_values wajib: hasil Collection::map() mewarisi indeks asli,
            // sehingga $check['masalah'][0] bisa tidak ada walau daftarnya terisi.
            'masalah' => $ok ? [] : array_values($masalah),
        ];
    }

    protected function rupiah(float $nilai): string
    {
        $tanda = $nilai < 0 ? '−' : '';

        return $tanda.'Rp '.number_format(abs($nilai), 0, ',', '.');
    }

    protected function angka(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 2, ',', '.'), '0'), ',');
    }
}
