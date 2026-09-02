<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS Grouped {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .kop { margin-bottom: 10px; }
        .sub { font-size: 10px; color: #334155; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #94a3b8; padding: 4px 6px; }
        th { background: #e2e8f0; text-align: center; font-size: 10px; }
        .r { text-align: right; }
        .c { text-align: center; }
        .kegiatan-row td { background: #dbeafe; font-weight: bold; color: #1e3a8a; }
        .subtotal-row td { background: #f1f5f9; font-weight: bold; }
        .grand-row td { background: #1e3a8a; color: #fff; font-weight: bold; }
        .grand-row td.r { color: #fff; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) — RINCIAN BULANAN PER KEGIATAN</h1>
        <div class="sub">
            {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }} &middot;
            {{ $sekolah->alamat }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}
        </div>
        <div class="sub">{{ $tahunAnggaran->sumber_dana ?? 'BOSP REGULER' }} Tahun {{ $tahunAnggaran->tahun ?? 2026 }} &middot; {{ $tahunAnggaran->status_pengesahan ?? 'Draft' }}</div>
        @php $st = strtoupper($tahunAnggaran->status_pengesahan ?? 'DRAFT'); @endphp
        <div style="margin-top:6px;"><span style="display:inline-block; padding:3px 12px; border:2px solid {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; color:{{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; font-size:11px; font-weight:800; letter-spacing:2px;">{{ $st }}</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="c">No</th>
                <th>ID Barang ARKAS</th>
                <th>Uraian</th>
                <th>Kode Rekening</th>
                <th class="r">Volume</th>
                <th class="r">Harga Satuan</th>
                <th class="r">Jan</th>
                <th class="r">Feb</th>
                <th class="r">Mar</th>
                <th class="r">Apr</th>
                <th class="r">Mei</th>
                <th class="r">Jun</th>
                <th class="r">Jul</th>
                <th class="r">Agu</th>
                <th class="r">Sep</th>
                <th class="r">Okt</th>
                <th class="r">Nov</th>
                <th class="r">Des</th>
                <th class="r">Jumlah 1 Tahun</th>
                <th class="r">Koreksi</th>
                <th class="r">Jumlah+Koreksi</th>
                <th class="c">Kontrol</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $g)
                <tr class="kegiatan-row">
                    <td colspan="22">KEGIATAN: [{{ $g['kode'] }}] {{ $g['nama'] }} @if($g['sub_program']) — {{ $g['sub_program'] }} @endif ({{ $g['jumlah_item'] }} item)</td>
                </tr>
                @foreach($g['items'] as $item)
                <tr>
                    <td class="c">{{ $item->no_urut }}</td>
                    <td class="c">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                    <td>{{ $item->uraian }}@if($item->keterangan_kustom) ({{ $item->keterangan_kustom }}) @endif</td>
                    <td>{{ $item->kodeRekening->kode ?? '-' }} {{ $item->kodeRekening->nama ?? '' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float)$item->volume,2,',','.'),'0'),',') }} {{ $item->satuan }}</td>
                    <td class="r">{{ (int)$item->harga_satuan }}</td>
                    <td class="r">{{ (int)$item->bulanMap[1] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[2] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[3] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[4] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[5] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[6] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[7] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[8] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[9] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[10] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[11] }}</td>
                    <td class="r">{{ (int)$item->bulanMap[12] }}</td>
                    <td class="r">{{ (int)$item->jumlah }}</td>
                    <td class="r">{{ (int)$item->koreksi }}</td>
                    <td class="r">{{ (int)$item->jumlah_koreksi }}</td>
                    <td class="c">{{ $item->kontrol }}</td>
                </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td colspan="6" class="r">Subtotal {{ $g['kode'] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][1] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][2] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][3] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][4] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][5] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][6] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][7] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][8] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][9] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][10] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][11] }}</td>
                    <td class="r">{{ (int)$g['perBulan'][12] }}</td>
                    <td class="r">{{ (int)$g['total_kontrol'] }}</td>
                    <td class="r">{{ (int)$g['total_koreksi'] }}</td>
                    <td class="r">{{ (int)$g['total_sudah'] }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="22" class="c">Belum ada rincian belanja.</td></tr>
            @endforelse
            <tr class="grand-row">
                <td colspan="6" class="r">GRAND TOTAL</td>
                <td class="r">{{ (int)$grandPerBulan[1] }}</td>
                <td class="r">{{ (int)$grandPerBulan[2] }}</td>
                <td class="r">{{ (int)$grandPerBulan[3] }}</td>
                <td class="r">{{ (int)$grandPerBulan[4] }}</td>
                <td class="r">{{ (int)$grandPerBulan[5] }}</td>
                <td class="r">{{ (int)$grandPerBulan[6] }}</td>
                <td class="r">{{ (int)$grandPerBulan[7] }}</td>
                <td class="r">{{ (int)$grandPerBulan[8] }}</td>
                <td class="r">{{ (int)$grandPerBulan[9] }}</td>
                <td class="r">{{ (int)$grandPerBulan[10] }}</td>
                <td class="r">{{ (int)$grandPerBulan[11] }}</td>
                <td class="r">{{ (int)$grandPerBulan[12] }}</td>
                <td class="r">{{ (int)$grandKontrol }}</td>
                <td class="r">{{ (int)$grandKoreksi }}</td>
                <td class="r">{{ (int)$grandTotal }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="22" class="r">Sisa Pagu: Rp {{ number_format($sisaPagu,0,',','.') }} | Tahap I: Rp {{ number_format($grandTahap1,0,',','.') }} | Tahap II: Rp {{ number_format($grandTahap2,0,',','.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
