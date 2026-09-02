<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS Grouped {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 7px; color: #1e293b; margin: 0; padding: 0; }
        .kop { text-align: left; margin-bottom: 10px; border-bottom: 2px solid #1e3a8a; padding-bottom: 6px; }
        .kop h1 { font-size: 12px; margin: 0 0 2px; color: #1e3a8a; }
        .kop .sub { font-size: 7px; color: #475569; }
        h2.center { text-align: center; font-size: 11px; margin: 0 0 2px; }
        p.center { text-align: center; font-size: 7px; margin: 0 0 8px; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #94a3b8; padding: 2px 3px; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; font-size: 6px; text-transform: uppercase; text-align: center; }
        td.r, th.r { text-align: right; }
        td.c, th.c { text-align: center; }
        .kegiatan-header { background: #f1f5f9; padding: 5px 6px; border: 1px solid #94a3b8; border-bottom: none; font-size: 8px; }
        .kegiatan-header .kode { display: inline-block; background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 3px; font-weight: 800; font-size: 7px; }
        .kegiatan-header .nama { font-weight: 800; color: #1e293b; margin-left: 6px; }
        .kegiatan-header .sub { color: #64748b; font-size: 6px; margin-left: 4px; }
        .subtotal td { background: #f8fafc; font-weight: bold; font-size: 7px; }
        .total-kegiatan td { background: #e0f2fe; font-weight: bold; }
        .grand td { background: #1e3a8a; color: #fff; font-weight: bold; }
        .grand td.r { color: #fff; }
        .tahap-label { font-size: 6px; font-weight: 700; color: #0f172a; background: #f1f5f9; text-align: left; padding: 2px 4px; }
        .blok-kegiatan { page-break-inside: avoid; margin-bottom: 10px; }
        .footer { margin-top: 12px; }
        .ttd { width: 100%; }
        .ttd td { border: none; padding: 0; vertical-align: top; }
        .ttd .kolom { width: 33.33%; text-align: center; font-size: 7px; }
        .ttd .kolom p { margin: 3px 0; }
        .auraian { color: #334155; }
        .keterangan { color: #4f46e5; font-style: italic; font-size: 6px; }
        .small { font-size: 6px; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) — RINCIAN BULANAN</h1>
        <div class="sub">
            {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }}<br>
            {{ $sekolah->alamat }} {{ $sekolah->desa_kelurahan }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}
        </div>
    </div>

    <h2 class="center">RINCIAN RENCANA BELANJA {{ $tahunAnggaran->tahun ?? 2026 }} — PER KEGIATAN (12 BULAN)</h2>
    <p class="center">{{ $tahunAnggaran->sumber_dana ?? 'BOSP REGULER' }} &middot; {{ $tahunAnggaran->status_pengesahan ?? 'Draft' }}</p>
    @php $st = strtoupper($tahunAnggaran->status_pengesahan ?? 'DRAFT'); @endphp
    <div style="text-align:center; margin-bottom:8px;">
        <span style="display:inline-block; padding:3px 12px; border:1.5px solid {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; color:{{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; font-size:8px; font-weight:800; letter-spacing:1.5px; border-radius:5px;">{{ $st }}</span>
    </div>

    @forelse($groups as $g)
    <div class="blok-kegiatan">
        <div class="kegiatan-header">
            <span class="kode">{{ $g['kode'] }}</span>
            <span class="nama">{{ $g['nama'] }}</span>
            @if($g['sub_program'])<span class="sub">Sub: {{ $g['sub_program'] }}</span>@endif
            <span style="float:right; font-size:6px; color:#475569;">{{ $g['jumlah_item'] }} item &middot; Total: Rp {{ number_format($g['total_sudah'],0,',','.') }}</span>
        </div>

        {{-- Tahap I: Jan-Jun --}}
        <div class="tahap-label">Tahap I — Januari s.d. Juni</div>
        <table>
            <thead>
                <tr>
                    <th class="c" style="width:14px;">No</th>
                    <th style="width:58px;">ID Barang</th>
                    <th>Uraian &amp; Keterangan</th>
                    <th style="width:62px;">Kode Rekening</th>
                    <th class="r" style="width:34px;">Vol</th>
                    <th class="r" style="width:48px;">Harga</th>
                    <th class="r" style="width:42px;">Jan</th>
                    <th class="r" style="width:42px;">Feb</th>
                    <th class="r" style="width:42px;">Mar</th>
                    <th class="r" style="width:42px;">Apr</th>
                    <th class="r" style="width:42px;">Mei</th>
                    <th class="r" style="width:42px;">Jun</th>
                    <th class="r" style="width:44px;">Koreksi</th>
                    <th class="r" style="width:50px;">Jml+Koreksi</th>
                    <th class="c" style="width:28px;">Ktrl</th>
                </tr>
            </thead>
            <tbody>
                @foreach($g['items'] as $item)
                <tr>
                    <td class="c">{{ $item->no_urut }}</td>
                    <td class="c" style="font-size:6px;">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                    <td>
                        <div class="auraian">{{ $item->uraian }}</div>
                        @if($item->keterangan_kustom)<div class="keterangan">{{ $item->keterangan_kustom }}</div>@endif
                    </td>
                    <td><div style="font-size:6px;">{{ $item->kodeRekening->kode ?? '-' }}</div><div class="small">{{ $item->kodeRekening->nama ?? '' }}</div></td>
                    <td class="r">{{ rtrim(rtrim(number_format((float)$item->volume,2,',','.'),'0'),',') }} {{ $item->satuan }}</td>
                    <td class="r">{{ number_format((float)$item->harga_satuan,0,',','.') }}</td>
                    <td class="r">{{ $item->bulanMap[1] ? number_format($item->bulanMap[1],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[2] ? number_format($item->bulanMap[2],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[3] ? number_format($item->bulanMap[3],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[4] ? number_format($item->bulanMap[4],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[5] ? number_format($item->bulanMap[5],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[6] ? number_format($item->bulanMap[6],0,',','.') : '—' }}</td>
                    <td class="r">{{ (float)$item->koreksi == 0 ? '—' : number_format((float)$item->koreksi,0,',','.') }}</td>
                    <td class="r">{{ number_format((float)$item->jumlah_koreksi,0,',','.') }}</td>
                    <td class="c" style="font-size:6px;">{{ $item->kontrol }}</td>
                </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="6" class="r">Subtotal Tahap I (Jan–Jun)</td>
                    <td class="r">{{ number_format($g['perBulan'][1],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][2],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][3],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][4],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][5],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][6],0,',','.') }}</td>
                    <td colspan="3" class="r">Tahap I: Rp {{ number_format($g['subTahap1'],0,',','.') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Tahap II: Jul-Des --}}
        <div class="tahap-label">Tahap II — Juli s.d. Desember</div>
        <table>
            <thead>
                <tr>
                    <th class="c" style="width:14px;">No</th>
                    <th style="width:58px;">ID Barang</th>
                    <th>Uraian &amp; Keterangan</th>
                    <th style="width:62px;">Kode Rekening</th>
                    <th class="r" style="width:34px;">Vol</th>
                    <th class="r" style="width:48px;">Harga</th>
                    <th class="r" style="width:42px;">Jul</th>
                    <th class="r" style="width:42px;">Agu</th>
                    <th class="r" style="width:42px;">Sep</th>
                    <th class="r" style="width:42px;">Okt</th>
                    <th class="r" style="width:42px;">Nov</th>
                    <th class="r" style="width:42px;">Des</th>
                    <th class="r" style="width:44px;">Koreksi</th>
                    <th class="r" style="width:50px;">Jml+Koreksi</th>
                    <th class="c" style="width:28px;">Ktrl</th>
                </tr>
            </thead>
            <tbody>
                @foreach($g['items'] as $item)
                <tr>
                    <td class="c">{{ $item->no_urut }}</td>
                    <td class="c" style="font-size:6px;">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                    <td>
                        <div class="auraian">{{ $item->uraian }}</div>
                        @if($item->keterangan_kustom)<div class="keterangan">{{ $item->keterangan_kustom }}</div>@endif
                    </td>
                    <td><div style="font-size:6px;">{{ $item->kodeRekening->kode ?? '-' }}</div><div class="small">{{ $item->kodeRekening->nama ?? '' }}</div></td>
                    <td class="r">{{ rtrim(rtrim(number_format((float)$item->volume,2,',','.'),'0'),',') }} {{ $item->satuan }}</td>
                    <td class="r">{{ number_format((float)$item->harga_satuan,0,',','.') }}</td>
                    <td class="r">{{ $item->bulanMap[7] ? number_format($item->bulanMap[7],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[8] ? number_format($item->bulanMap[8],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[9] ? number_format($item->bulanMap[9],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[10] ? number_format($item->bulanMap[10],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[11] ? number_format($item->bulanMap[11],0,',','.') : '—' }}</td>
                    <td class="r">{{ $item->bulanMap[12] ? number_format($item->bulanMap[12],0,',','.') : '—' }}</td>
                    <td class="r">{{ (float)$item->koreksi == 0 ? '—' : number_format((float)$item->koreksi,0,',','.') }}</td>
                    <td class="r">{{ number_format((float)$item->jumlah_koreksi,0,',','.') }}</td>
                    <td class="c" style="font-size:6px;">{{ $item->kontrol }}</td>
                </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="6" class="r">Subtotal Tahap II (Jul–Des)</td>
                    <td class="r">{{ number_format($g['perBulan'][7],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][8],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][9],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][10],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][11],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['perBulan'][12],0,',','.') }}</td>
                    <td colspan="3" class="r">Tahap II: Rp {{ number_format($g['subTahap2'],0,',','.') }}</td>
                </tr>
                <tr class="total-kegiatan">
                    <td colspan="6" class="r">Total Kegiatan (1 Tahun)</td>
                    <td colspan="6" class="r">Rp {{ number_format($g['subTahap1'] + $g['subTahap2'],0,',','.') }}</td>
                    <td class="r">{{ number_format($g['total_koreksi'],0,',','.') }}</td>
                    <td class="r">Rp {{ number_format($g['total_sudah'],0,',','.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    @empty
    <p style="text-align:center; color:#64748b; padding:20px;">Belum ada rincian belanja.</p>
    @endforelse

    <table>
        <tbody>
            <tr class="grand">
                <td colspan="6" class="r">GRAND TOTAL (1 Tahun)</td>
                <td class="r">Rp {{ number_format($grandPerBulan[1],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[2],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[3],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[4],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[5],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[6],0,',','.') }}</td>
                <td colspan="3" class="r">Tahap I: Rp {{ number_format($grandTahap1,0,',','.') }}</td>
            </tr>
            <tr class="grand">
                <td colspan="6" class="r"></td>
                <td class="r">Rp {{ number_format($grandPerBulan[7],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[8],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[9],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[10],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[11],0,',','.') }}</td>
                <td class="r">Rp {{ number_format($grandPerBulan[12],0,',','.') }}</td>
                <td colspan="3" class="r">Tahap II: Rp {{ number_format($grandTahap2,0,',','.') }}</td>
            </tr>
            <tr class="grand">
                <td colspan="6" class="r">Total +Koreksi &amp; Sisa Pagu</td>
                <td colspan="6" class="r">Total: Rp {{ number_format($grandTotal,0,',','.') }} (Koreksi: Rp {{ number_format($grandKoreksi,0,',','.') }})</td>
                <td colspan="3" class="r">Sisa Pagu: Rp {{ number_format($sisaPagu,0,',','.') }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 6px; font-size: 6px; color: #64748b;">
        Pagu Total: Rp {{ number_format($tahunAnggaran->pagu_total ?? 0, 0, ',', '.') }} &middot;
        Tahap I: Rp {{ number_format($tahunAnggaran->pagu_tahap1 ?? 0, 0, ',', '.') }} &middot;
        Tahap II: Rp {{ number_format($tahunAnggaran->pagu_tahap2 ?? 0, 0, ',', '.') }}
    </p>

    <div class="footer">
        <table class="ttd">
            <tr>
                <td class="kolom">
                    <p>Dibuat oleh,</p>
                    <p><b>{{ $sekolah->nama_bendahara ?? '................................' }}</b></p>
                    <p>Bendahara</p>
                    <p>NIP. {{ $sekolah->nip_bendahara ?? '........................' }}</p>
                </td>
                <td class="kolom">
                    <p>Mengetahui,</p>
                    <p><b>{{ $sekolah->nama_kepala_sekolah ?? '................................' }}</b></p>
                    <p>Kepala Sekolah</p>
                    <p>NIP. {{ $sekolah->nip_kepala_sekolah ?? '........................' }}</p>
                </td>
                <td class="kolom"></td>
            </tr>
        </table>
    </div>
</body>
</html>
