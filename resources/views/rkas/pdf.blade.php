<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kertas Kerja RKAS {{ $tahunAnggaran->tahun ?? 2026 }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .kop {
            text-align: left;
            margin-bottom: 14px;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
        }
        .kop h1 { font-size: 14px; margin: 0 0 2px; color: #1e3a8a; }
        .kop .sub { font-size: 9px; color: #475569; }
        h2.center { text-align: center; font-size: 12px; margin: 0 0 3px; }
        p.center { text-align: center; font-size: 9px; margin: 0 0 12px; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #94a3b8; padding: 3px 4px; text-align: left; }
        th { background: #e2e8f0; font-size: 8px; text-transform: uppercase; text-align: center; }
        td.r, th.r { text-align: right; }
        td.c, th.c { text-align: center; }
        .total-row td { background: #f1f5f9; font-weight: bold; }
        .footer { margin-top: 24px; }
        .ttd { width: 100%; }
        .ttd td { border: none; padding: 0; vertical-align: top; }
        .ttd .kolom { width: 33.33%; text-align: center; font-size: 9px; }
        .ttd .kolom p { margin: 4px 0; }
        .auraian { color: #334155; }
        .keterangan { color: #4f46e5; font-style: italic; font-size: 8px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)</h1>
        <div class="sub">
            {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }}<br>
            {{ $sekolah->alamat }} {{ $sekolah->desa_kelurahan }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}
        </div>
    </div>

    <h2 class="center">RINCIAN RENCANA BELANJA {{ $tahunAnggaran->tahun ?? 2026 }}</h2>
    <p class="center">{{ $tahunAnggaran->sumber_dana ?? 'BOSP REGULER' }} &middot; {{ $tahunAnggaran->status_pengesahan ?? 'Draft' }}</p>

    <table>
        <thead>
            <tr>
                <th class="c" style="width: 22px;">No</th>
                <th>Kode Kegiatan / Program</th>
                <th style="width: 240px;">Uraian &amp; Keterangan Khusus</th>
                <th>Kode Rekening</th>
                <th class="r">Volume</th>
                <th>Satuan</th>
                <th class="r">Harga Satuan</th>
                <th class="r">Jumlah (Rp)</th>
                <th class="r">Tahap I (Rp)</th>
                <th class="r">Tahap II (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotal = 0;
                $grandT1 = 0;
                $grandT2 = 0;
            @endphp
            @forelse($items as $item)
                @php
                    $jumlah = (float) $item->jumlah;
                    $t1 = (float) $item->alokasiBulan->whereBetween('bulan', [1, 6])->sum('jumlah');
                    $t2 = (float) $item->alokasiBulan->whereBetween('bulan', [7, 12])->sum('jumlah');
                    $grandTotal += $jumlah;
                    $grandT1 += $t1;
                    $grandT2 += $t2;
                @endphp
                <tr>
                    <td class="c">{{ $item->no_urut }}</td>
                    <td>
                        <div>{{ $item->program->kode ?? '-' }}</div>
                        <div class="keterangan">{{ $item->program->nama ?? '' }}</div>
                    </td>
                    <td>
                        <div class="auraian">{{ $item->uraian }}</div>
                        @if($item->keterangan_kustom)
                            <div class="keterangan">{{ $item->keterangan_kustom }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $item->kodeRekening->kode ?? '-' }}</div>
                        <div class="keterangan">{{ $item->kodeRekening->nama ?? '' }}</div>
                    </td>
                    <td class="r">{{ number_format((float) $item->volume, 2, ',', '.') }}</td>
                    <td>{{ $item->satuan }}</td>
                    <td class="r">{{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format($jumlah, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format($t1, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format($t2, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="c">Belum ada rincian belanja.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="7" class="r">TOTAL</td>
                <td class="r">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                <td class="r">{{ number_format($grandT1, 0, ',', '.') }}</td>
                <td class="r">{{ number_format($grandT2, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 8px; font-size: 8px; color: #64748b;">
        Pagu Total: Rp {{ number_format($tahunAnggaran->pagu_total ?? 0, 0, ',', '.') }} &middot;
        Pagu Tahap I: Rp {{ number_format($tahunAnggaran->pagu_tahap1 ?? 0, 0, ',', '.') }} &middot;
        Pagu Tahap II: Rp {{ number_format($tahunAnggaran->pagu_tahap2 ?? 0, 0, ',', '.') }}
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