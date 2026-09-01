@extends('layouts.app')

@section('content')
@php
    $bulanIndonesia = ['', 'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $statusBg = [
        'sesuai' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30',
        'melebihi' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
        'kurang' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
    ];
    $statusLabel = ['sesuai' => 'SESUAI', 'melebihi' => 'MELEBIHI', 'kurang' => 'BELUM CUKUP'];

    // Skor Kesiapan RKAS
    $checks = [
        ['label' => 'Honor tidak melebihi batas', 'ok' => $summary['honor']['status'] === 'sesuai'],
        ['label' => 'Anggaran buku memenuhi minimum', 'ok' => $summary['buku']['status'] === 'sesuai'],
        ['label' => 'Sarpras tidak melebihi batas', 'ok' => $summary['sarpras']['status'] === 'sesuai'],
        ['label' => 'Alokasi Tahap I ≥ 50%', 'ok' => $summary['tahap1']['status'] === 'sesuai'],
        ['label' => 'Total anggaran tidak melebihi pagu', 'ok' => $summary['sudah_dianggarkan'] <= $summary['pagu_total']],
    ];
    $okCount = collect($checks)->where('ok', true)->count();
    $score = round($okCount / count($checks) * 100);

    $maxBulan = max(array_merge(array_values($bulanData), [1]));
    $maxJenis = max(array_merge(array_values($proporsiJenis), [1]));
    $totalBelanjaJenis = array_sum($proporsiJenis);
@endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Dashboard RKAS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $sekolah->nama_sekolah }} (NPSN {{ $sekolah->npsn }}) &middot; {{ $tahunAnggaran->sumber_dana }} {{ $tahunAnggaran->tahun ?? 2026 }}
                <span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400">Status: {{ ucfirst($summary['status_sekolah'] ?? 'negeri') }} &middot; Honor maks {{ $summary['honor']['batas_persen'] ?? 20 }}%</span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('rkas.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Lembar Kerja
            </a>
            <a href="{{ route('monitoring.juknis') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Monitoring JUKNIS
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif

    <!-- Kepatuhan komponen top -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Honor Belanja', 'hint' => 'maks {p}% dari pagu', 'key' => 'honor', 'bar' => $summary['honor']['persen'] / max($summary['honor']['batas_persen'],1) * 100, 'color' => 'bg-blue-500'],
            ['label' => 'Anggaran Buku', 'hint' => 'min {p}% dari pagu', 'key' => 'buku', 'bar' => $summary['buku']['persen'] / max($summary['buku']['batas_persen'],1) * 100, 'color' => 'bg-indigo-500'],
            ['label' => 'Sarpras', 'hint' => 'maks {p}% dari pagu', 'key' => 'sarpras', 'bar' => $summary['sarpras']['persen'] / max($summary['sarpras']['batas_persen'],1) * 100, 'color' => 'bg-violet-500'],
            ['label' => 'Tahap I', 'hint' => 'min {p}% dari pagu', 'key' => 'tahap1', 'bar' => $summary['tahap1']['persen'] / max($summary['tahap1']['batas_persen'],1) * 100, 'color' => 'bg-emerald-500'],
        ] as $comp)
            @php $d = $summary[$comp['key']]; $hint = str_replace('{p}', $d['batas_persen'], $comp['hint']); @endphp
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $comp['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBg[$d['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$d['status']] ?? $d['status'] }}</span>
                </div>
                <div class="text-2xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($d['total'], 0, ',', '.') }}</div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $d['persen'] }}% &middot; {{ $hint }}</div>
                <div class="mt-3 h-2 rounded-full bg-slate-100 dark:bg-slate-700/60 overflow-hidden">
                    <div class="h-full rounded-full {{ $comp['color'] }} transition-all" style="width: {{ min($comp['bar'], 100) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Statistik utama + Skor kesiapan -->
    @php $sisa = $summary['pagu_total'] - $summary['sudah_dianggarkan']; $sudahPct = $summary['pagu_total'] > 0 ? round($summary['sudah_dianggarkan'] / $summary['pagu_total'] * 100, 1) : 0; @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card p-5 flex flex-col">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <span class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-500/15 flex items-center justify-center text-blue-600 dark:text-blue-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v3m2 4v1a3 3 0 003 3h6a3 3 0 003-3v-1m-6-4l-3-3m0 0l-3 3m3-3v12"/></svg></span>
                    Pagu Total
                </div>
                <div class="text-xl font-extrabold text-slate-800 dark:text-white mt-3">Rp {{ number_format($summary['pagu_total'], 0, ',', '.') }}</div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Tahap I Rp {{ number_format($tahunAnggaran->pagu_tahap1 ?? 0, 0, ',', '.') }} · Tahap II Rp {{ number_format($tahunAnggaran->pagu_tahap2 ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="card p-5 flex flex-col">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 flex items-center justify-center text-indigo-600 dark:text-indigo-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
                    Sudah Dianggarkan
                </div>
                <div class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-3">Rp {{ number_format($summary['sudah_dianggarkan'], 0, ',', '.') }}</div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ $totalItem }} item · {{ $sudahPct }}% dari pagu</div>
                <div class="mt-3 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden"><div class="h-full bg-indigo-500" style="width: {{ min($sudahPct,100) }}%"></div></div>
            </div>
            <div class="card p-5 flex flex-col {{ $sisa < 0 ? 'ring-2 ring-red-200 dark:ring-red-500/30' : ($sisa === 0 ? 'ring-2 ring-emerald-200 dark:ring-emerald-500/30' : '') }}">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide {{ $sisa < 0 ? 'text-red-600 dark:text-red-400' : ($sisa === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400') }}">
                    <span class="w-8 h-8 rounded-lg {{ $sisa < 0 ? 'bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-400' : ($sisa === 0 ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400') }} flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg></span>
                    Sisa Pagu
                </div>
                <div class="text-xl font-extrabold mt-3 {{ $sisa < 0 ? 'text-red-600 dark:text-red-400' : ($sisa === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-emerald-600 dark:text-emerald-400') }}">Rp {{ number_format(abs($sisa), 0, ',', '.') }}</div>
                <div class="text-[11px] mt-1 {{ $sisa < 0 ? 'text-red-500' : 'text-slate-400 dark:text-slate-500' }}">{{ $sisa < 0 ? 'Melebihi pagu — kurangi anggaran' : ($sisa === 0 ? 'Pas — siap untuk pengesahan ✓' : 'Belum dianggarkan — harus Rp 0 sebelum pengesahan') }}</div>
            </div>
        </div>

        <!-- Skor Kesiapan -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Kesiapan RKAS</h3>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $okCount }}/{{ count($checks) }}</span>
            </div>
            <div class="flex items-center gap-4 mb-4">
                <div class="relative w-20 h-20">
                    <svg class="w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                        <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" class="stroke-slate-100 dark:stroke-slate-700/60"></circle>
                        <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-linecap="round"
                            class="{{ $score >= 100 ? 'stroke-emerald-500' : ($score >= 60 ? 'stroke-blue-500' : 'stroke-amber-500') }}"
                            stroke-dasharray="{{ (2 * 3.14159 * 34) }}" stroke-dashoffset="{{ (2 * 3.14159 * 34) * (1 - $score / 100) }}"></circle>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center text-lg font-extrabold text-slate-800 dark:text-white">{{ $score }}%</div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ $score >= 100 ? 'Siap untuk pengesahan' : ($score >= 60 ? 'Mendekati siap' : 'Masih perlu dilengkapi') }}
                    </div>
                    <div class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ $score >= 100 ? 'Semua syarat kepatuhan terpenuhi.' : ('Selesaikan ' . (count($checks) - $okCount) . ' langkah lagi.' ) }}</div>
                </div>
            </div>
            <ul class="space-y-2">
                @foreach($checks as $c)
                    <li class="flex items-center gap-2.5 text-xs {{ $c['ok'] ? 'text-slate-600 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' }}">
                        <span class="flex items-center justify-center w-5 h-5 rounded-full shrink-0 {{ $c['ok'] ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700/60 dark:text-slate-500' }}">
                            @if($c['ok'])
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                        </span>
                        {{ $c['label'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Grafik -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card p-6">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Alokasi per Bulan</h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-5">Biru = Tahap I (Jan&ndash;Jun) &middot; Hijau = Tahap II (Jul&ndash;Des)</p>
            <div class="flex items-end h-48 gap-1.5">
                @foreach($bulanData as $bulan => $nominal)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full rounded-t-lg {{ $bulan <= 6 ? 'bg-blue-500 hover:bg-blue-600' : 'bg-emerald-500 hover:bg-emerald-600' }} dark:opacity-90 transition-all" style="height: {{ max($nominal / $maxBulan * 170, 4) }}px" title="Rp {{ number_format($nominal, 0, ',', '.') }}"></div>
                        <div class="text-[9px] text-slate-400 dark:text-slate-500 mt-1.5">{{ $bulanIndonesia[$bulan] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card p-6">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Proporsi Jenis Belanja</h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-5">9 Jenis resmi ARKAS terhadap total belanja.</p>
            <div class="space-y-4">
                @forelse($proporsiJenis as $jenis => $nominal)
                    @php
                        $pct = $totalBelanjaJenis > 0 ? round($nominal / $totalBelanjaJenis * 100) : 0;
                        $palette = ['bg-blue-500','bg-indigo-500','bg-violet-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-cyan-500','bg-orange-500','bg-slate-500','bg-red-500'];
                        $idx = abs(crc32($jenis)) % count($palette);
                        $color = $palette[$idx];
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="font-semibold text-slate-600 dark:text-slate-300 text-xs leading-tight">{{ $jenis }}</span>
                            <span class="text-slate-400 dark:text-slate-500 text-xs whitespace-nowrap ml-2">Rp {{ number_format($nominal, 0, ',', '.') }} ({{ $pct }}%)</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-700/60 overflow-hidden">
                            <div class="h-full rounded-full {{ $color }} transition-all" style="width: {{ min($nominal / $maxJenis * 100, 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 dark:text-slate-500 text-center py-6">Belum ada data belanja.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tabel ringkasan kepatuhan -->
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Ringkasan Kepatuhan JUKNIS</h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Permendikdasmen No. 8/2026 &mdash; referensi pengisian ARKAS.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                        <th class="px-6 py-3 text-left font-semibold">Komponen</th>
                        <th class="px-4 py-3 text-right font-semibold">Dianggarkan</th>
                        <th class="px-4 py-3 text-right font-semibold">% Pagu</th>
                        <th class="px-4 py-3 text-right font-semibold">Batas</th>
                        <th class="px-4 py-3 text-right font-semibold">Sisa/Kurang</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach([
                        ['Honor', $summary['honor']],
                        ['Buku', $summary['buku']],
                        ['Sarpras', $summary['sarpras']],
                        ['Alokasi Tahap I', $summary['tahap1']],
                    ] as [$label, $data])
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-6 py-3.5 font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-200">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-300">{{ $data['persen'] }}%</td>
                            <td class="px-4 py-3.5 text-right text-slate-500 dark:text-slate-400">{{ $data['arah_persen'] ?? $data['batas_persen'] }}%</td>
                            <td class="px-4 py-3.5 text-right {{ ($data['sisa'] ?? 0) >= 0 ? 'text-slate-500 dark:text-slate-400' : 'text-red-600 dark:text-red-400' }}">Rp {{ number_format(abs($data['sisa'] ?? 0), 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $statusBg[$data['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$data['status']] ?? strtoupper($data['status']) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
