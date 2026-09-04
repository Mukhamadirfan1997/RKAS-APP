<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 9px; }
        h1 { font-size: 13px; margin: 0 0 4px; }
        .kop { margin-bottom: 8px; }
        .sub { font-size: 9px; color: #334155; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 4px; font-size: 8px; }
        th { background: #e2e8f0; text-align: center; font-size: 7px; font-weight: 700; }
        .r { text-align: right; }
        .c { text-align: center; }
        .kegiatan-row td { background: #f8fafc; font-weight: 700; color: #1e293b; }
        .rekening-row td { background: #f1f5f9; font-weight: 700; color: #334155; }
        .item-row td { background: #ffffff; }
        .grand-row td { background: #e2e8f0; font-weight: 700; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) TA {{ $tahunAnggaran->tahun ?? 2026 }}<br><span style="font-size:9px; font-weight:700; color:#1e293b;">Rincian Rencana Belanja Dana BOSP Reguler</span></h1>
        <p class="sub" style="margin:0 0 2px;">
            {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }} &middot;
            {{ $sekolah->alamat }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}<br>
            {{ $tahunAnggaran->sumber_dana ?? 'BOSP REGULER' }} Tahun {{ $tahunAnggaran->tahun ?? 2026 }} &middot; {{ $tahunAnggaran->status_pengesahan ?? 'Draft' }} &middot; Rincian per Kegiatan dan Rekening Belanja
            @php $st = strtoupper($tahunAnggaran->status_pengesahan ?? 'DRAFT'); @endphp
            &middot; <span style="display:inline-block; padding:1px 6px; border:1px solid {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; color:{{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; font-size:8px; font-weight:800; letter-spacing:0.5px;">{{ $st }}</span>
        </p>
    </div>

    @php $displayGroups = $flatGroups ?? $groups ?? collect(); $noExcel = 1; @endphp

    <table>
        <thead>
            <tr>
                <th class="c" style="width:28px;">No</th>
                <th style="width:70px;">Kode Barang</th>
                <th style="width:180px;">Uraian</th>
                <th style="width:75px;">Kode Rekening</th>
                <th class="r" style="width:55px;">Volume</th>
                <th style="width:45px;">Satuan</th>
                <th class="r" style="width:70px;">Harga Satuan</th>
                <th class="r" style="width:50px;">Jan Vol</th><th class="r" style="width:60px;">Jan Jml</th>
                <th class="r" style="width:50px;">Feb Vol</th><th class="r" style="width:60px;">Feb Jml</th>
                <th class="r" style="width:50px;">Mar Vol</th><th class="r" style="width:60px;">Mar Jml</th>
                <th class="r" style="width:50px;">Apr Vol</th><th class="r" style="width:60px;">Apr Jml</th>
                <th class="r" style="width:50px;">Mei Vol</th><th class="r" style="width:60px;">Mei Jml</th>
                <th class="r" style="width:50px;">Jun Vol</th><th class="r" style="width:60px;">Jun Jml</th>
                <th class="r" style="width:70px;">JUMLAH TAHAP I</th>
                <th class="r" style="width:50px;">Jul Vol</th><th class="r" style="width:60px;">Jul Jml</th>
                <th class="r" style="width:50px;">Agu Vol</th><th class="r" style="width:60px;">Agu Jml</th>
                <th class="r" style="width:50px;">Sep Vol</th><th class="r" style="width:60px;">Sep Jml</th>
                <th class="r" style="width:50px;">Okt Vol</th><th class="r" style="width:60px;">Okt Jml</th>
                <th class="r" style="width:50px;">Nov Vol</th><th class="r" style="width:60px;">Nov Jml</th>
                <th class="r" style="width:50px;">Des Vol</th><th class="r" style="width:60px;">Des Jml</th>
                <th class="r" style="width:70px;">JUMLAH TAHAP II</th>
                <th class="r" style="width:85px;">TOTAL (TAHAP I + TAHAP II)</th>
                <th class="c" style="width:50px;">Kontrol</th>
                <th class="c" style="width:55px;">Validasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($displayGroups as $g)
                {{-- Baris KEGIATAN: hanya Kode Barang (diisi Kode Kegiatan) + Uraian terisi --}}
                <tr class="kegiatan-row">
                    <td class="c"></td>
                    <td>{{ $g['kode'] }}</td>
                    <td>{{ $g['nama'] }}</td>
                    <td></td>
                    <td class="r"></td>
                    <td></td>
                    <td class="r"></td>
                    @for($b=1;$b<=6;$b++)<td class="r"></td><td class="r"></td>@endfor
                    <td class="r"></td>
                    @for($b=7;$b<=12;$b++)<td class="r"></td><td class="r"></td>@endfor
                    <td class="r"></td>
                    <td class="r"></td>
                    <td class="c"></td>
                    <td class="c"></td>
                </tr>
                @foreach($g['rekenings'] as $rek)
                    {{-- Baris REKENING: hanya Kode Rekening + Uraian terisi --}}
                    <tr class="rekening-row">
                        <td class="c"></td>
                        <td></td>
                        <td>{{ $rek['nama'] }}</td>
                        <td>{{ $rek['kode'] }}</td>
                        <td class="r"></td>
                        <td></td>
                        <td class="r"></td>
                        @for($b=1;$b<=6;$b++)<td class="r"></td><td class="r"></td>@endfor
                        <td class="r"></td>
                        @for($b=7;$b<=12;$b++)<td class="r"></td><td class="r"></td>@endfor
                        <td class="r"></td>
                        <td class="r"></td>
                        <td class="c"></td>
                        <td class="c"></td>
                    </tr>
                    @foreach($rek['items'] as $item)
                    <tr class="item-row">
                        <td class="c">{{ $noExcel++ }}</td>
                        <td class="c">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                        <td>{{ $item->uraian }}@if($item->keterangan_kustom) ({{ $item->keterangan_kustom }})@endif</td>
                        <td>{{ $item->kodeRekening->kode ?? '' }}</td>
                        <td class="r">{{ rtrim(rtrim(number_format((float)$item->volume,2,',','.'),'0'),',') }}</td>
                        <td>{{ $item->satuan }}</td>
                        <td class="r">{{ (int)$item->harga_satuan }}</td>
                        @for($b=1;$b<=6;$b++)
                            <td class="r">{{ $item->bulanVol[$b] ? rtrim(rtrim(number_format((float)$item->bulanVol[$b],2,',','.'),'0'),',') : '0' }}</td>
                            <td class="r">{{ (int)($item->bulanJml[$b] ?? 0) }}</td>
                        @endfor
                        <td class="r">{{ (int)($item->tahap1Jml ?? 0) }}</td>
                        @for($b=7;$b<=12;$b++)
                            <td class="r">{{ $item->bulanVol[$b] ? rtrim(rtrim(number_format((float)$item->bulanVol[$b],2,',','.'),'0'),',') : '0' }}</td>
                            <td class="r">{{ (int)($item->bulanJml[$b] ?? 0) }}</td>
                        @endfor
                        <td class="r">{{ (int)($item->tahap2Jml ?? 0) }}</td>
                        <td class="r">{{ (int)$item->jumlah_koreksi }}</td>
                        <td class="c">{{ $item->kontrol }}</td>
                        <td class="c">{{ $item->validasiBulanan }}</td>
                    </tr>
                    @endforeach
                @endforeach
            @empty
                <tr><td colspan="36" class="c">Belum ada rincian belanja.</td></tr>
            @endforelse
            <tr class="grand-row">
                <td colspan="4" class="r">GRAND TOTAL</td>
                <td class="r"></td>
                <td></td>
                <td class="r"></td>
                @for($b=1;$b<=6;$b++)
                    <td class="r">{{ rtrim(rtrim(number_format((float)($grandPerBulanVol[$b] ?? 0),2,',','.'),'0'),',') }}</td>
                    <td class="r">{{ (int)($grandPerBulanJml[$b] ?? $grandPerBulan[$b] ?? 0) }}</td>
                @endfor
                <td class="r">{{ (int)($grandTahap1Jml ?? $grandTahap1 ?? 0) }}</td>
                @for($b=7;$b<=12;$b++)
                    <td class="r">{{ rtrim(rtrim(number_format((float)($grandPerBulanVol[$b] ?? 0),2,',','.'),'0'),',') }}</td>
                    <td class="r">{{ (int)($grandPerBulanJml[$b] ?? $grandPerBulan[$b] ?? 0) }}</td>
                @endfor
                <td class="r">{{ (int)($grandTahap2Jml ?? $grandTahap2 ?? 0) }}</td>
                <td class="r">{{ (int)($grandTotal ?? 0) }}</td>
                <td class="c"></td>
                <td class="c"></td>
            </tr>
            <tr>
                <td colspan="36" class="r">Sisa Pagu: Rp {{ number_format($sisaPagu,0,',','.') }} | Tahap I: Rp {{ number_format($grandTahap1Jml ?? $grandTahap1 ?? 0,0,',','.') }} | Tahap II: Rp {{ number_format($grandTahap2Jml ?? $grandTahap2 ?? 0,0,',','.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
