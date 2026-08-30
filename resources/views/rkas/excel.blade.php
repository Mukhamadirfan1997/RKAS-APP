<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS {{ $tahunAnggaran->tahun ?? 2026 }}</title>
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
        .total-row td { background: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)</h1>
        <div class="sub">
            {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }} &middot;
            {{ $sekolah->alamat }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}
        </div>
        <div class="sub">{{ $tahunAnggaran->sumber_dana ?? 'BOSP REGULER' }} Tahun {{ $tahunAnggaran->tahun ?? 2026 }} &middot; {{ $tahunAnggaran->status_pengesahan ?? 'Draft' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="c">No</th>
                <th>Kode Prog</th>
                <th>Kegiatan / Program</th>
                <th>Uraian</th>
                <th>Keterangan Khusus</th>
                <th>Kode Rekening</th>
                <th>Nama Rekening</th>
                <th class="r">Volume</th>
                <th>Satuan</th>
                <th class="r">Harga Satuan</th>
                <th class="r">Harga ARKAS</th>
                <th class="r">KOREKSI (Rp)</th>
                <th class="r">Jumlah Kontrol (Rp)</th>
                <th class="r">Jumlah +Koreksi (Rp)</th>
                <th class="c">KONTROL</th>
                <th class="r">Tahap I (Rp)</th>
                <th class="r">Tahap II (Rp)</th>
                <th>Bulan Aktif</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandKontrol = 0;
                $grandKoreksi = 0;
                $grandT1 = 0;
                $grandT2 = 0;
                $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            @endphp
            @foreach($items as $item)
                @php
                    $active = $item->alokasiBulan->where('volume', '>', 0);
                    $label = $active->count() === 12 ? 'Jan-Des' : $active->map(fn($a) => $bulan[$a->bulan])->implode(', ');
                    $t1 = (float) $item->tahap1;
                    $t2 = (float) $item->tahap2;
                    $grandKontrol += (float) $item->jumlah;
                    $grandKoreksi += (float) $item->koreksi;
                    $grandT1 += $t1;
                    $grandT2 += $t2;
                @endphp
                <tr>
                    <td class="c">{{ $item->no_urut }}</td>
                    <td>{{ $item->program->kode ?? '-' }}</td>
                    <td>{{ $item->program->nama ?? '-' }}</td>
                    <td>{{ $item->uraian }}</td>
                    <td>{{ $item->keterangan_kustom ?? '' }}</td>
                    <td>{{ $item->kodeRekening->kode ?? '-' }}</td>
                    <td>{{ $item->kodeRekening->nama ?? '-' }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format((float) $item->volume, 2, ',', '.'), '0'), ',') }}</td>
                    <td>{{ $item->satuan }}</td>
                    <td class="r">{{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format((float) $item->harga_satuan_arkas, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format((float) $item->koreksi, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format((float) $item->jumlah, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format((float) $item->jumlah_koreksi, 0, ',', '.') }}</td>
                    <td class="c">{{ $item->kontrol }}</td>
                    <td class="r">{{ number_format($t1, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format($t2, 0, ',', '.') }}</td>
                    <td>{{ $label }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="12" class="r">TOTAL</td>
                <td class="r">{{ number_format($grandKontrol, 0, ',', '.') }}</td>
                <td class="r">{{ number_format($grandKontrol + $grandKoreksi, 0, ',', '.') }}</td>
                <td></td>
                <td class="r">{{ number_format($grandT1, 0, ',', '.') }}</td>
                <td class="r">{{ number_format($grandT2, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>