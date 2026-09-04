<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    @include('rkas.partials.pdf-styles')
</head>
<body>
    @include('rkas.partials.pdf-kop', [
        'kopJudul' => 'Rincian Rencana Belanja Dana BOSP Reguler',
        'kopSub' => 'Tahun Anggaran '.($tahunAnggaran->tahun ?? 2026).' · '.($tahunAnggaran->sumber_dana ?? 'BOSP REGULER').' · Rincian per Kegiatan dan Rekening Belanja',
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
                @include('rkas.partials.pdf-rekening-pita', ['rek' => $rek, 'colspan' => 12])
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

    @include('rkas.partials.pdf-legend', ['showValidasi' => true])
    @include('rkas.partials.pdf-footer')
</body>
</html>
