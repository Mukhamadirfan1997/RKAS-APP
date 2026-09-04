<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja Per Bulan {{ $bulanNama ?? '' }} {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    @include('rkas.partials.pdf-styles')
</head>
<body>
    @php
        $judulPerBulan = 'RINCIAN KERTAS KERJA PER BULAN';
        $subPerBulan = 'Bulan: '.($bulanNama ?? '').' '.($tahunAnggaran->tahun ?? 2026);
    @endphp
    @include('rkas.partials.pdf-kop', [
        'kopJudul' => $judulPerBulan,
        'kopSub' => $subPerBulan,
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
                <col style="width:4%">
                <col style="width:11%">
                <col style="width:30%">
                <col style="width:14%">
                <col style="width:8%">
                <col style="width:7%">
                <col style="width:10%">
                <col style="width:11%">
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
                    <th class="r">Jumlah</th>
                    <th class="c col-kontrol">Kontrol<small>Harga vs Katalog</small></th>
                </tr>
            </thead>
            <tbody>
                @foreach($g['rekenings'] as $rek)
                @include('rkas.partials.pdf-rekening-pita', ['rek' => $rek, 'colspan' => 9])
                @foreach($rek['items'] as $item)
                <tr class="item-row">
                    <td class="c">{{ $noPdf++ }}</td>
                    <td class="c col-kode-barang" style="word-break: break-all; font-size:9px;">{{ $item->barang->id_barang_arkas ?? $item->barang->kode ?? '' }}</td>
                    <td><span class="auraian">{{ $item->uraian }}</span>@if($item->keterangan_kustom)<span class="keterangan">{{ $item->keterangan_kustom }}</span>@endif</td>
                    <td class="col-kode-rekening">{{ $item->kodeRekening->kode ?? '-' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float)($item->volumeBulan ?? $item->bulanVol[$bulan] ?? 0),2,',','.'),'0'),',') }}</td>
                    <td class="c">{{ $item->satuanBulan ?? $item->satuan }}</td>
                    <td class="r">{{ number_format((float)$item->harga_satuan,0,',','.') }}</td>
                    <td class="r" style="font-weight:700;">{{ number_format((float)($item->jumlahBulan ?? $item->bulanJml[$bulan] ?? 0),0,',','.') }}</td>
                    <td class="c"><span class="badge-kontrol {{ strtolower($item->kontrol) == 'ok' ? 'ok' : 'selisih' }}">{{ $item->kontrol }}</span></td>
                </tr>
                @endforeach
                @endforeach
                <tr class="subtotal">
                    <td colspan="4" class="r">Subtotal {{ $g['kode'] }}</td>
                    <td class="r"></td><td></td><td class="r"></td>
                    <td class="r">{{ number_format($g['subBulanJml'] ?? 0,0,',','.') }}</td>
                    <td class="c"></td>
                </tr>
            </tbody>
        </table>
    </div>
    @empty
    <p style="text-align:center; color:#64748b; padding:20px; font-size:10px;">Tidak ada rincian belanja untuk bulan {{ $bulanNama ?? '' }} {{ $tahunAnggaran->tahun ?? 2026 }}.</p>
    @endforelse

    <div style="height:6px;"></div>

    @php
        $paguTotal = (float) ($tahunAnggaran->pagu_total ?? 0);
        $totalBulan = (float) ($grandBulanTotal ?? $grandBulanJml ?? 0);
        $persenBulanVal = (float) ($persenBulan ?? 0);
    @endphp
    <table style="margin-top: 8px;">
        <colgroup>
            <col style="width:40%">
            <col style="width:30%">
            <col style="width:30%">
        </colgroup>
        <thead>
            <tr>
                <th colspan="3" style="background:#1e3a8a; color:#fff; font-size:9px; padding:7px; letter-spacing:0.5px;">RINGKASAN BULAN {{ strtoupper($bulanNama ?? '') }} {{ $tahunAnggaran->tahun ?? 2026 }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight:700; background:#f8fafc; text-align:center;">Total Bulan Ini</td>
                <td class="r" style="font-weight:800; color:#1e3a8a; font-size:11px; background:#eff6ff;">Rp {{ number_format($totalBulan,0,',','.') }}</td>
                <td class="r" style="font-size:10px; background:#f8fafc;">{{ $persenBulanVal }}% dari Pagu Total 1 Tahun <span style="color:#64748b; font-size:7.5px;">(Rp {{ number_format($paguTotal,0,',','.') }})</span></td>
            </tr>
        </tbody>
    </table>

    @include('rkas.partials.pdf-legend', ['showValidasi' => false])
    @include('rkas.partials.pdf-footer')
</body>
</html>
