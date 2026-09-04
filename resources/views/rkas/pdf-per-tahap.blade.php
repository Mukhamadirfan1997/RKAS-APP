<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja Per Tahap {{ $tahap ?? 1 }} {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    @include('rkas.partials.pdf-styles')
    <style>
        /* Perkecil font khusus PDF Per Tahap (paling banyak kolom) */
        body { font-size: 10px; }
        th { font-size: 8.5px; padding: 5px 6px; }
        td { font-size: 10px; padding: 5px 6px; }
        .blok-kegiatan .kegiatan-title { font-size: 9px; padding: 4px 8px; }
        .rekening-row td.rekening-nama { font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 0; }
    </style>
</head>
<body>
    @php
        $bulanShort = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $bulanNamaList = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $tahapVal = $tahap ?? 1;
        $tahapBulansLocal = $tahapBulans ?? ($tahapVal===1 ? [1,2,3,4,5,6] : [7,8,9,10,11,12]);
        $tahapLabelLocal = $tahapLabel ?? ($tahapVal===1 ? 'Tahap I (Januari - Juni)' : 'Tahap II (Juli - Desember)');
        $judulPerTahap = 'RINCIAN KERTAS KERJA PER TAHAP';
        $subPerTahap = 'Tahap: '.$tahapLabelLocal.' '.($tahunAnggaran->tahun ?? 2026);
    @endphp
    @include('rkas.partials.pdf-kop', [
        'kopJudul' => $judulPerTahap,
        'kopSub' => $subPerTahap,
    ])

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
                <col style="width:8%">
                <col style="width:24%">
                <col style="width:11%">
                <col style="width:6%">
                <col style="width:5%">
                <col style="width:7%">
                @foreach($tahapBulansLocal as $mb)
                <col style="width:4.2%">
                <col style="width:5.2%">
                @endforeach
                <col style="width:7%">
                <col style="width:5%">
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
                    @foreach($tahapBulansLocal as $mb)
                    <th class="r">{{ $bulanShort[$mb] }} Vol</th><th class="r">{{ $bulanShort[$mb] }} Jml</th>
                    @endforeach
                    <th class="r">Subtotal {{ $tahapVal===1 ? 'Tahap I' : 'Tahap II' }}</th>
                    <th class="c col-kontrol">Kontrol<small>Harga vs Katalog</small></th>
                </tr>
            </thead>
            <tbody>
                @foreach($g['rekenings'] as $rek)
                @include('rkas.partials.pdf-rekening-pita', ['rek' => $rek, 'colspan' => 21])
                @foreach($rek['items'] as $item)
                <tr class="item-row">
                    <td class="c">{{ $noPdf++ }}</td>
                    <td class="c col-kode-barang" style="word-break: break-all; font-size:9px;">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                    <td><span class="auraian">{{ $item->uraian }}</span>@if($item->keterangan_kustom)<span class="keterangan">{{ $item->keterangan_kustom }}</span>@endif</td>
                    <td class="col-kode-rekening">{{ $item->kodeRekening->kode ?? '-' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float)($item->volumeTahap ?? 0),2,',','.'),'0'),',') }}</td>
                    <td class="c">{{ $item->satuan }}</td>
                    <td class="r">{{ number_format((float)$item->harga_satuan,0,',','.') }}</td>
                    @foreach($tahapBulansLocal as $mb)
                    <td class="r">{{ $item->bulanVol[$mb] ? rtrim(rtrim(number_format((float)$item->bulanVol[$mb],2,',','.'),'0'),',') : '—' }}</td>
                    <td class="r">{{ $item->bulanJml[$mb] ? number_format((float)$item->bulanJml[$mb],0,',','.') : '—' }}</td>
                    @endforeach
                    <td class="r" style="font-weight:700;">{{ number_format((float)($item->jumlahTahap ?? 0),0,',','.') }}</td>
                    <td class="c"><span class="badge-kontrol {{ strtolower($item->kontrol) == 'ok' ? 'ok' : 'selisih' }}">{{ $item->kontrol }}</span></td>
                </tr>
                @endforeach
                @endforeach
                <tr class="subtotal">
                    <td colspan="4" class="r">Subtotal {{ $g['kode'] }}</td>
                    <td class="r"></td><td></td><td class="r"></td>
                    @foreach($tahapBulansLocal as $mb)<td class="r"></td><td class="r"></td>@endforeach
                    <td class="r">{{ number_format($g['subTahapJml'] ?? 0,0,',','.') }}</td>
                    <td class="c"></td>
                </tr>
            </tbody>
        </table>
    </div>
    @empty
    <p style="text-align:center; color:#64748b; padding:20px; font-size:10px;">Tidak ada rincian belanja untuk {{ $tahapLabelLocal }} {{ $tahunAnggaran->tahun ?? 2026 }}.</p>
    @endforelse

    <div style="height:6px;"></div>

    @php
        $totalTahap = (float) ($grandTahapTotal ?? $grandTahapJml ?? 0);
        $paguTahapVal = (float) ($paguTahap ?? 0);
        $persenTahapVal = (float) ($persenTahap ?? 0);
        $tahapRomawi = $tahapVal===1 ? 'I' : 'II';
    @endphp
    <table style="margin-top: 8px;">
        <colgroup>
            <col style="width:40%">
            <col style="width:30%">
            <col style="width:30%">
        </colgroup>
        <thead>
            <tr>
                <th colspan="3" style="background:#1e3a8a; color:#fff; font-size:9px; padding:7px; letter-spacing:0.5px;">RINGKASAN TAHAP {{ $tahapRomawi }} {{ $tahunAnggaran->tahun ?? 2026 }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight:700; background:#f8fafc; text-align:center;">Total Tahap Ini</td>
                <td class="r" style="font-weight:800; color:#1e3a8a; font-size:11px; background:#eff6ff;">Rp {{ number_format($totalTahap,0,',','.') }}</td>
                <td class="r" style="font-size:10px; background:#f8fafc;">{{ $persenTahapVal }}% dari Pagu Tahap {{ $tahapRomawi }} <span style="color:#64748b; font-size:7.5px;">(Rp {{ number_format($paguTahapVal,0,',','.') }})</span></td>
            </tr>
        </tbody>
    </table>

    @include('rkas.partials.pdf-legend', ['showValidasi' => false])
    @include('rkas.partials.pdf-footer')
</body>
</html>
