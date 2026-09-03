<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        .kop { text-align: left; margin-bottom: 12px; border-bottom: 3px solid #1e3a8a; padding-bottom: 7px; }
        .kop h1 { font-size: 18px; margin: 0 0 4px; color: #1e3a8a; letter-spacing: 0.3px; font-weight: 800; }
        .kop h1 span.tahun { color: #ffffff; background: #1e3a8a; padding: 3px 9px; border-radius: 4px; font-size: 13px; vertical-align: middle; margin-left: 8px; }
        .kop .sub { font-size: 10px; color: #475569; line-height: 1.4; }
        h2.center { text-align: center; font-size: 14px; margin: 0 0 4px; font-weight: 800; color: #1e293b; letter-spacing: 0.2px; }
        p.center { text-align: center; font-size: 10px; margin: 0 0 8px; color: #475569; }
        .status-badge { display: inline-block; padding: 4px 16px; border-width: 1.5px; border-style: solid; font-size: 9px; font-weight: 800; letter-spacing: 1px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; vertical-align: middle; overflow: hidden; word-wrap: break-word; }
        th { background: #e2e8f0; font-size: 9.5px; text-transform: uppercase; text-align: center; font-weight: 800; letter-spacing: 0.3px; color: #1e293b; }
        th.col-kode-barang { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        th.col-kode-rekening { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        th.col-kontrol { background: #f1f5f9; color: #334155; font-size: 8px; line-height: 1.2; }
        th.col-kontrol small { display: block; font-size: 7px; font-weight: 400; text-transform: none; color: #64748b; margin-top: 1px; }
        th.col-validasi { background: #f1f5f9; color: #334155; font-size: 8px; line-height: 1.2; }
        th.col-validasi small { display: block; font-size: 7px; font-weight: 400; text-transform: none; color: #64748b; margin-top: 1px; }
        td { font-size: 11px; line-height: 1.4; }
        td.r, th.r { text-align: right; }
        td.c, th.c { text-align: center; }
        .kegiatan-row td { background: #f1f5f9; font-weight: 700; color: #1e293b; border-color: #cbd5e1; }
        .rekening-row td { background: #fffbeb; font-weight: 700; color: #92400e; border-color: #fde68a; }
        .item-row td { border-color: #e2e8f0; }
        .item-row:nth-child(even) td { background: #f8fafc; }
        .item-row:nth-child(odd) td { background: #ffffff; }
        .item-row td.col-kode-barang { background: #eff6ff; color: #1e40af; text-align: center; font-size: 9px; font-weight: 600; }
        .item-row:nth-child(even) td.col-kode-barang { background: #dbeafe; }
        .item-row td.col-kode-rekening { background: #fffbeb; color: #92400e; text-align: center; font-size: 9px; font-weight: 600; }
        .item-row:nth-child(even) td.col-kode-rekening { background: #fef3c7; }
        .badge-kontrol { display: inline-block; padding: 3px 7px; border-radius: 10px; font-size: 8px; font-weight: 800; letter-spacing: 0.3px; }
        .badge-kontrol.ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-kontrol.selisih { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-validasi { display: inline-block; padding: 3px 7px; border-radius: 10px; font-size: 8px; font-weight: 800; }
        .badge-validasi.benar { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-validasi.salah { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .subtotal td { background: #f1f5f9; font-weight: 700; font-size: 10px; border-color: #94a3b8; }
        .grand td { background: #1e3a8a; color: #fff; font-weight: 800; border-color: #1e3a8a; font-size: 10px; }
        .grand td.r { color: #fff; }
        .grand-sisa td { background: #f1f5f9; font-weight: 700; color: #1e293b; text-align: right; padding: 10px 11px; font-size: 10px; border-color: #cbd5e1; }
        .blok-kegiatan { page-break-inside: avoid; margin-bottom: 3px; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; }
        .blok-kegiatan .kegiatan-title { background: #f8fafc; padding: 5px 9px; font-size: 10px; font-weight: 700; border-bottom: 1px solid #cbd5e1; color: #1e293b; }
        .blok-kegiatan .kegiatan-title span.kode { background: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 3px; font-size: 9px; border: 1px solid #93c5fd; }
        .legend { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 9px 12px; margin: 10px 0 12px; font-size: 9px; color: #334155; line-height: 1.6; }
        .legend strong { color: #1e293b; }
        .legend .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; vertical-align: middle; margin-right: 3px; }
        .footer { margin-top: 22px; }
        .ttd { width: 100%; margin-top: 14px; }
        .ttd td { border: none; padding: 0; vertical-align: top; }
        .ttd .kolom { width: 33.33%; text-align: center; font-size: 11px; }
        .ttd .kolom p { margin: 5px 0; }
        .ttd .jarak-ttd { height: 85px; }
        .auraian { color: #1e293b; font-weight: 600; }
        .keterangan { color: #64748b; font-style: italic; font-size: 8px; display: block; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) <span class="tahun">TA {{ $tahunAnggaran->tahun ?? 2026 }}</span></h1>
        <div class="sub">
            {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }}<br>
            {{ $sekolah->alamat }} {{ $sekolah->desa_kelurahan }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}
        </div>
    </div>

    <h2 class="center">Rincian Rencana Belanja Dana BOSP Reguler</h2>
    <p class="center">Tahun Anggaran {{ $tahunAnggaran->tahun ?? 2026 }} &middot; {{ $tahunAnggaran->sumber_dana ?? 'BOSP REGULER' }} &middot; Rincian per Kegiatan dan Rekening Belanja</p>
    @php $st = strtoupper($tahunAnggaran->status_pengesahan ?? 'DRAFT'); @endphp
    <div style="text-align:center; margin-bottom:10px;">
        <span class="status-badge" style="border-color: {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; color: {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }};">{{ $st }}</span>
    </div>

    @php
        $displayGroups = $flatGroups ?? $groups ?? collect();
        $noPdf = 1;
    @endphp

    @forelse($displayGroups as $g)
    <div class="blok-kegiatan">
        <div class="kegiatan-title"><span class="kode">[{{ $g['kode'] }}]</span> {{ $g['nama'] }} <span style="font-weight:400; color:#64748b; font-size:8.5px;">— {{ $g['jumlah_rekening'] }} rekening, {{ $g['jumlah_item'] }} item</span></div>
        <table>
            <colgroup>
                <col style="width:3%">
                <col style="width:9%">
                <col style="width:28%">
                <col style="width:11%">
                <col style="width:5%">
                <col style="width:5%">
                <col style="width:8%">
                <col style="width:7%">
                <col style="width:7%">
                <col style="width:8%">
                <col style="width:4.5%">
                <col style="width:4.5%">
            </colgroup>
            <thead>
                <tr>
                    <th class="c">No</th>
                    <th class="col-kode-barang">Kode Barang</th>
                    <th>Uraian</th>
                    <th class="col-kode-rekening">Kode Rekening</th>
                    <th class="r">Volume</th>
                    <th>Satuan</th>
                    <th class="r">Harga Satuan</th>
                    <th class="r">JUMLAH TAHAP I</th>
                    <th class="r">JUMLAH TAHAP II</th>
                    <th class="r">Jumlah</th>
                    <th class="c col-kontrol">Kontrol<small>Harga vs Katalog</small></th>
                    <th class="c col-validasi">Validasi<small>Jumlah vs Bulanan</small></th>
                </tr>
            </thead>
            <tbody>
                @foreach($g['rekenings'] as $rek)
                <tr class="rekening-row">
                    <td class="c"></td>
                    <td></td>
                    <td>{{ $rek['nama'] }}</td>
                    <td class="col-kode-rekening">{{ $rek['kode'] }}</td>
                    <td class="r"></td><td></td><td class="r"></td><td class="r"></td><td class="r"></td><td class="r"></td><td class="c"></td><td class="c"></td>
                </tr>
                @foreach($rek['items'] as $item)
                <tr class="item-row">
                    <td class="c">{{ $noPdf++ }}</td>
                    <td class="c col-kode-barang" style="word-break: break-all;">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                    <td><span class="auraian">{{ $item->uraian }}</span>@if($item->keterangan_kustom)<span class="keterangan">{{ $item->keterangan_kustom }}</span>@endif</td>
                    <td class="col-kode-rekening">{{ $item->kodeRekening->kode ?? '-' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float)$item->volume,2,',','.'),'0'),',') }}</td>
                    <td class="c">{{ $item->satuan }}</td>
                    <td class="r">{{ number_format((float)$item->harga_satuan,0,',','.') }}</td>
                    <td class="r">{{ number_format($item->tahap1Jml ?? 0,0,',','.') }}</td>
                    <td class="r">{{ number_format($item->tahap2Jml ?? 0,0,',','.') }}</td>
                    <td class="r" style="font-weight:700;">{{ number_format((float)$item->jumlah_koreksi,0,',','.') }}</td>
                    <td class="c"><span class="badge-kontrol {{ strtolower($item->kontrol) == 'ok' ? 'ok' : 'selisih' }}">{{ $item->kontrol }}</span></td>
                    <td class="c"><span class="badge-validasi {{ strtolower($item->validasiBulanan) == 'benar' ? 'benar' : 'salah' }}">{{ $item->validasiBulanan }}</span></td>
                </tr>
                @endforeach
                @endforeach
                <tr class="subtotal">
                    <td colspan="4" class="r">Subtotal {{ $g['kode'] }}</td>
                    <td class="r"></td><td></td><td class="r"></td>
                    <td class="r">{{ number_format($g['subTahap1Jml'] ?? 0,0,',','.') }}</td>
                    <td class="r">{{ number_format($g['subTahap2Jml'] ?? 0,0,',','.') }}</td>
                    <td class="r">{{ number_format($g['total_sudah'],0,',','.') }}</td>
                    <td class="c"></td><td class="c"></td>
                </tr>
            </tbody>
        </table>
    </div>
    @empty
    <p style="text-align:center; color:#64748b; padding:20px; font-size:10px;">Belum ada rincian belanja.</p>
    @endforelse

    <div style="height:6px;"></div>

    @php
        $paguTotal = (float) ($tahunAnggaran->pagu_total ?? 0);
        $paguTahap1 = (float) ($tahunAnggaran->pagu_tahap1 ?? 0);
        $paguTahap2 = (float) ($tahunAnggaran->pagu_tahap2 ?? 0);
        $realTahap1 = (float) ($grandTahap1Jml ?? $grandTahap1 ?? 0);
        $realTahap2 = (float) ($grandTahap2Jml ?? $grandTahap2 ?? 0);
        $realTotal = (float) ($grandTotal ?? 0);
        $sisaPaguVal = (float) ($sisaPagu ?? 0);
        $persenTotal = $paguTotal > 0 ? round($realTotal / $paguTotal * 100, 1) : 0;
        $persenTahap1 = $paguTahap1 > 0 ? round($realTahap1 / $paguTahap1 * 100, 1) : 0;
        $persenTahap2 = $paguTahap2 > 0 ? round($realTahap2 / $paguTahap2 * 100, 1) : 0;
        $statusPagu = $sisaPaguVal == 0 ? 'SEIMBANG' : ($sisaPaguVal > 0 ? 'SISA' : 'LEBIH');
        $warnaSisa = $sisaPaguVal == 0 ? '#166534' : ($sisaPaguVal > 0 ? '#92400e' : '#991b1b');
        $bgSisa = $sisaPaguVal == 0 ? '#dcfce7' : ($sisaPaguVal > 0 ? '#fef3c7' : '#fee2e2');
    @endphp
    <table style="margin-top: 8px;">
        <colgroup>
            <col style="width:25%">
            <col style="width:25%">
            <col style="width:25%">
            <col style="width:25%">
        </colgroup>
        <thead>
            <tr>
                <th colspan="4" style="background:#1e3a8a; color:#fff; font-size:9px; padding:7px; letter-spacing:0.5px;">RINGKASAN PAGU ANGGARAN</th>
            </tr>
            <tr>
                <th style="background:#f1f5f9; font-size:7.5px;">Uraian</th>
                <th style="background:#dbeafe; color:#1e40af; font-size:7.5px;">Pagu 1 Tahun</th>
                <th style="background:#e0e7ff; color:#3730a3; font-size:7.5px;">Pagu Tahap I</th>
                <th style="background:#fef3c7; color:#92400e; font-size:7.5px;">Pagu Tahap II</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight:700; background:#f8fafc; text-align:center;">Pagu Ditetapkan</td>
                <td class="r" style="font-weight:800; background:#eff6ff; color:#1e40af;">Rp {{ number_format($paguTotal,0,',','.') }}</td>
                <td class="r" style="background:#eff6ff; color:#3730a3;">Rp {{ number_format($paguTahap1,0,',','.') }}</td>
                <td class="r" style="background:#fffbeb; color:#92400e;">Rp {{ number_format($paguTahap2,0,',','.') }}</td>
            </tr>
            <tr>
                <td style="font-weight:700; background:#f8fafc; text-align:center; font-size:9px;">Jumlah Dianggarkan (Rencana)</td>
                <td class="r" style="font-weight:800; color:#1e293b; font-size:10px;">Rp {{ number_format($realTotal,0,',','.') }} <span style="color:#64748b; font-size:7.5px;">({{ $persenTotal }}%)</span></td>
                <td class="r" style="font-size:10px;">Rp {{ number_format($realTahap1,0,',','.') }} <span style="color:#64748b; font-size:7.5px;">({{ $persenTahap1 }}%)</span></td>
                <td class="r" style="font-size:10px;">Rp {{ number_format($realTahap2,0,',','.') }} <span style="color:#64748b; font-size:7.5px;">({{ $persenTahap2 }}%)</span></td>
            </tr>
            <tr>
                <td style="font-weight:700; background:#f8fafc; text-align:center;">Sisa Pagu</td>
                <td colspan="3" class="r" style="font-weight:800; background:{{ $bgSisa }}; color:{{ $warnaSisa }}; border:1px solid {{ $warnaSisa }}; font-size:10px;">
                    Rp {{ number_format($sisaPaguVal,0,',','.') }} — {{ $statusPagu }}
                    @if($sisaPaguVal > 0)
                        <span style="font-weight:400; font-size:7px; color:#92400e;"> (masih ada sisa, perlu tambah belanja {{ number_format($persenTotal,1) }}% terpakai)</span>
                    @elseif($sisaPaguVal < 0)
                        <span style="font-weight:400; font-size:7px; color:#991b1b;"> (melebihi pagu!)</span>
                    @else
                        <span style="font-weight:400; font-size:7px; color:#166534;"> (pas, 100% terpakai)</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <div class="legend">
        <strong>Keterangan Kolom Kontrol &amp; Validasi:</strong><br>
        <span class="dot" style="background:#dcfce7; border:1px solid #86efac;"></span> <strong>Kontrol OK</strong> = Harga Satuan sesuai Harga Katalog ARKAS &nbsp;|&nbsp;
        <span class="dot" style="background:#fee2e2; border:1px solid #fca5a5;"></span> <strong>SELISIH</strong> = Harga berbeda dari Katalog &nbsp;&nbsp;
        <span class="dot" style="background:#dcfce7; border:1px solid #86efac;"></span> <strong>Validasi BENAR</strong> = Jumlah (Vol×Harga) = Tahap I + Tahap II &nbsp;|&nbsp;
        <span class="dot" style="background:#fee2e2; border:1px solid #fca5a5;"></span> <strong>SALAH</strong> = Tidak konsisten (perlu perbaiki alokasi bulanan)
    </div>

    <div class="footer">
        <table class="ttd">
            <tr>
                <td class="kolom" style="width:50%; text-align:center;">
                    <p>Bendahara,</p>
                    <div class="jarak-ttd"></div>
                    <p><b>{{ $sekolah->nama_bendahara ?? '................................' }}</b></p>
                    <p>NIP. {{ $sekolah->nip_bendahara ?? '........................' }}</p>
                </td>
                <td class="kolom" style="width:50%; text-align:center;">
                    <p>Kepala Sekolah,</p>
                    <div class="jarak-ttd"></div>
                    <p><b>{{ $sekolah->nama_kepala_sekolah ?? '................................' }}</b></p>
                    <p>NIP. {{ $sekolah->nip_kepala_sekolah ?? '........................' }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
