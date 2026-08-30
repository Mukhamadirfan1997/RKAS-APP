@extends('layouts.app')

@section('content')
@php
    $bulanIndonesia = ['', 'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $statusBadge = [
        'sesuai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'melebihi' => 'bg-red-50 text-red-700 border-red-200',
        'kurang' => 'bg-amber-50 text-amber-700 border-amber-200',
    ];
    $statusLabel = ['sesuai' => 'SESUAI', 'melebihi' => 'MELEBIHI', 'kurang' => 'KURANG'];
    $maxBulan = max(array_merge($bulanData, [1]));
    $maxJenis = max(array_merge(array_values($proporsiJenis), [1]));
@endphp

<div class="p-5 md:p-6 max-w-[1440px] mx-auto">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800">Dashboard RKAS</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                {{ $sekolah->nama_sekolah }} (NPSN {{ $sekolah->npsn }}) &mdash; {{ $tahunAnggaran->sumber_dana }} {{ $tahunAnggaran->tahun ?? 2026 }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('rkas.index') }}" class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50">Lembar Kerja</a>
            <a href="{{ route('monitoring.juknis') }}" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold">Monitoring JUKNIS</a>
        </div>
    </div>

    <!-- Ringkasan Kepatuhan -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Honor (maks {{ $summary['honor']['batas_persen'] }}%)</div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['honor']['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$summary['honor']['status']] ?? $summary['honor']['status'] }}</span>
            </div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['honor']['total'], 0, ',', '.') }}</div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full {{ $summary['honor']['status'] === 'sesuai' ? 'bg-emerald-500' : 'bg-red-500' }}" style="width: {{ min($summary['honor']['persen'] / $summary['honor']['batas_persen'] * 100, 100) }}%"></div>
            </div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $summary['honor']['persen'] }}% dari pagu (batas {{ $summary['honor']['batas_persen'] }}%)</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Buku (min {{ $summary['buku']['batas_persen'] }}%)</div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['buku']['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$summary['buku']['status']] ?? $summary['buku']['status'] }}</span>
            </div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['buku']['total'], 0, ',', '.') }}</div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full {{ $summary['buku']['status'] === 'sesuai' ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ min($summary['buku']['persen'] / $summary['buku']['batas_persen'] * 100, 100) }}%"></div>
            </div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $summary['buku']['persen'] }}% dari pagu (batas {{ $summary['buku']['batas_persen'] }}%)</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Sarpras (maks {{ $summary['sarpras']['batas_persen'] }}%)</div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['sarpras']['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$summary['sarpras']['status']] ?? $summary['sarpras']['status'] }}</span>
            </div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['sarpras']['total'], 0, ',', '.') }}</div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full {{ $summary['sarpras']['status'] === 'sesuai' ? 'bg-emerald-500' : 'bg-red-500' }}" style="width: {{ min($summary['sarpras']['persen'] / $summary['sarpras']['batas_persen'] * 100, 100) }}%"></div>
            </div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $summary['sarpras']['persen'] }}% dari pagu (batas {{ $summary['sarpras']['batas_persen'] }}%)</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Tahap I (min {{ $summary['tahap1']['batas_persen'] }}%)</div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['tahap1']['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$summary['tahap1']['status']] ?? $summary['tahap1']['status'] }}</span>
            </div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['tahap1']['total'], 0, ',', '.') }}</div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full {{ $summary['tahap1']['status'] === 'sesuai' ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ min($summary['tahap1']['persen'] / $summary['tahap1']['batas_persen'] * 100, 100) }}%"></div>
            </div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $summary['tahap1']['persen'] }}% dari pagu (target {{ $summary['tahap1']['batas_persen'] }}%)</div>
        </div>
    </div>

    <!-- Statistik utama -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl text-white p-4 shadow-sm">
            <div class="text-[11px] font-medium text-blue-100 uppercase tracking-wide">Pagu Total</div>
            <div class="text-lg font-bold mt-1">Rp {{ number_format($summary['pagu_total'], 0, ',', '.') }}</div>
        </div>
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl text-white p-4 shadow-sm">
            <div class="text-[11px] font-medium text-indigo-100 uppercase tracking-wide">Sudah Dianggarkan</div>
            <div class="text-lg font-bold mt-1">Rp {{ number_format($summary['sudah_dianggarkan'], 0, ',', '.') }}</div>
            <div class="text-[11px] text-indigo-200 mt-0.5">{{ $totalItem }} item belanja</div>
        </div>
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-xl text-white p-4 shadow-sm">
            <div class="text-[11px] font-medium text-emerald-100 uppercase tracking-wide">Sisa Pagu</div>
            <div class="text-lg font-bold mt-1">Rp {{ number_format($summary['pagu_total'] - $summary['sudah_dianggarkan'], 0, ',', '.') }}</div>
        </div>
        <div class="bg-gradient-to-br from-slate-700 to-slate-800 rounded-xl text-white p-4 shadow-sm">
            <div class="text-[11px] font-medium text-slate-300 uppercase tracking-wide">Serapan Pagu</div>
            <div class="text-lg font-bold mt-1">{{ $summary['pagu_total'] > 0 ? round($summary['sudah_dianggarkan'] / $summary['pagu_total'] * 100) : 0 }}%</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Tahap I: {{ $summary['tahap1']['persen'] }}%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Grafik proporsi bulanan -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h3 class="text-sm font-bold text-slate-700 mb-1">Alokasi per Bulan</h3>
            <p class="text-[11px] text-slate-400 mb-4">Kartu biru = Tahap I, hijau = Tahap II (paling kiri adalah Januari).</p>
            <div class="flex items-end h-44 gap-1.5">
                @foreach($bulanData as $bulan => $nominal)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full rounded-t-md {{ $bulan <= 6 ? 'bg-blue-500' : 'bg-emerald-500' }} hover:opacity-80 transition-opacity" style="height: {{ max($nominal / $maxBulan * 150, 4) }}px" title="Rp {{ number_format($nominal, 0, ',', '.') }}"></div>
                        <div class="text-[9px] text-slate-400 mt-1">{{ $bulanIndonesia[$bulan] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Grafik proporsi jenis belanja -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h3 class="text-sm font-bold text-slate-700 mb-1">Proporsi Antar Jenis Belanja</h3>
            <p class="text-[11px] text-slate-400 mb-4">BARJAS / MODAL / HONOR terhadap total belanja.</p>
            <div class="space-y-4">
                @foreach($proporsiJenis as $jenis => $nominal)
                    @php
                        $totalBelanja = array_sum($proporsiJenis);
                        $pct = $totalBelanja > 0 ? round($nominal / $totalBelanja * 100) : 0;
                        $colors = ['BARJAS' => 'bg-blue-500', 'MODAL' => 'bg-indigo-500', 'HONOR' => 'bg-rose-500'];
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-semibold text-slate-600">{{ $jenis }}</span>
                            <span class="text-slate-400">Rp {{ number_format($nominal, 0, ',', '.') }} ({{ $pct }}%)</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $colors[$jenis] ?? 'bg-slate-400' }}" style="width: {{ min($nominal / $maxJenis * 100, 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
                @if(empty($proporsiJenis))
                    <div class="text-xs text-slate-400 text-center py-6">Belum ada data belanja.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Ringkasan Kategori JUKNIS -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-700">Ringkasan Kepatuhan JUKNIS</h3>
                <p class="text-[11px] text-slate-400">Referensi pengisian ARKAS &mdash; Permendikdasmen No. 8/2026.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-2.5 text-left font-semibold">Komponen</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Dianggarkan</th>
                        <th class="px-4 py-2.5 text-right font-semibold">% Pagu</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Batas</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Sisa/Kurang</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $rows = [
                            ['Honor', $summary['honor']],
                            ['Buku', $summary['buku']],
                            ['Sarpras', $summary['sarpras']],
                            ['Alokasi Tahap I', $summary['tahap1']],
                        ];
                    @endphp
                    @foreach($rows as [$label, $data])
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $label }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ $data['persen'] }}%</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ $data['arah_persen'] ?? $data['batas_persen'] }}%</td>
                            <td class="px-4 py-3 text-right {{ ($data['sisa'] ?? 0) >= 0 ? 'text-slate-500' : 'text-red-600' }}">Rp {{ number_format(abs($data['sisa'] ?? 0), 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge[$data['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$data['status']] ?? strtoupper($data['status']) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection