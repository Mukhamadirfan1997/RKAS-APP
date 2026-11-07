@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Kepala halaman --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Kesiapan RKAS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Daftar periksa sebelum pengesahan TA {{ $tahunAnggaran->tahun }} &mdash; setiap temuan bisa diklik ke halaman perbaikannya.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-bold border
                @if($statusRkas === 'Disahkan') bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30
                @elseif($statusRkas === 'Pergeseran') bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30
                @else bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600
                @endif">{{ $statusRkas }}</span>
            <a href="{{ route('pengaturan.pagu', ['tahun' => $tahunAnggaran->tahun]) }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                Pagu &amp; Status
            </a>
            <a href="{{ route('rkas.index', ['tahun' => $tahunAnggaran->tahun]) }}" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors">
                Lembar Kerja
            </a>
        </div>
    </div>

    {{-- Ringkasan skor --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="card p-6 flex items-center gap-5 lg:col-span-1">
            <div class="relative w-24 h-24 shrink-0">
                <svg class="w-24 h-24 -rotate-90" viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" class="stroke-slate-100 dark:stroke-slate-700/60"></circle>
                    <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-linecap="round"
                        class="{{ $kesiapan['skor'] >= 100 ? 'stroke-emerald-500' : ($kesiapan['skor'] >= 60 ? 'stroke-blue-500' : 'stroke-amber-500') }}"
                        stroke-dasharray="{{ (2 * 3.14159 * 34) }}" stroke-dashoffset="{{ (2 * 3.14159 * 34) * (1 - $kesiapan['skor'] / 100) }}"></circle>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center text-xl font-extrabold text-slate-800 dark:text-white">{{ $kesiapan['skor'] }}%</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Status Kesiapan</div>
                <div class="text-base font-extrabold {{ $kesiapan['siap'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} mt-1">
                    {{ $kesiapan['siap'] ? 'Siap disahkan' : 'Belum siap disahkan' }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $kesiapan['ok_count'] }} dari {{ $kesiapan['total'] }} syarat terpenuhi</div>
                @if(! $kesiapan['siap'])
                    <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ count($kesiapan['masalah']) }} temuan perlu diperbaiki</div>
                @endif
            </div>
        </div>

        <div class="card p-6 lg:col-span-2">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Pagu 1 Tahun</div>
                    <div class="text-sm font-extrabold text-slate-800 dark:text-white mt-1">Rp {{ number_format($kesiapan['pagu_total'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Sudah Dianggarkan</div>
                    <div class="text-sm font-extrabold text-blue-600 dark:text-blue-400 mt-1">Rp {{ number_format($kesiapan['sudah_dianggarkan'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Sisa Pagu</div>
                    <div class="text-sm font-extrabold mt-1 {{ abs($kesiapan['sisa']) < 0.5 ? 'text-emerald-600 dark:text-emerald-400' : ($kesiapan['sisa'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400') }}">
                        Rp {{ number_format($kesiapan['sisa'], 0, ',', '.') }}
                    </div>
                </div>
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Jumlah Item</div>
                    <div class="text-sm font-extrabold text-slate-800 dark:text-white mt-1">{{ $kesiapan['jumlah_item'] }} baris</div>
                </div>
            </div>
            <div class="mt-4 rounded-xl px-4 py-3 text-xs
                {{ $kesiapan['siap']
                    ? 'bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300'
                    : 'bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300' }}">
                @if($kesiapan['siap'])
                    Semua syarat terpenuhi. RKAS sudah bisa disahkan dari tab <b>Pagu &amp; Status RKAS</b>.
                @else
                    Sahkan hanya setelah daftar ini bersih. Bila memang ada kondisi khusus, pengesahan masih bisa dilakukan dengan menuliskan alasan di tab <b>Pagu &amp; Status RKAS</b> (tercatat di audit log).
                @endif
            </div>
        </div>
    </div>

    {{-- Daftar pemeriksaan --}}
    <div class="space-y-3">
        @foreach($kesiapan['checks'] as $check)
            <div class="card overflow-hidden">
                <div class="px-5 py-4 flex items-start gap-3">
                    <span class="flex items-center justify-center w-7 h-7 rounded-full shrink-0 mt-0.5 {{ $check['ok'] ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' }}">
                        @if($check['ok'])
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div class="text-sm font-bold {{ $check['ok'] ? 'text-slate-700 dark:text-slate-200' : 'text-red-700 dark:text-red-400' }}">{{ $check['label'] }}</div>
                            <a href="{{ $check['tautan'] }}" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline shrink-0">Buka halaman terkait &rarr;</a>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $check['ringkas'] }}</div>

                        @if(! $check['ok'] && ! empty($check['masalah']))
                            <div class="mt-3 rounded-xl border border-red-200 dark:border-red-500/30 overflow-hidden">
                                <div class="px-3 py-2 bg-red-50 dark:bg-red-500/10 text-[11px] font-bold text-red-700 dark:text-red-400">
                                    {{ count($check['masalah']) }} item perlu diperbaiki
                                </div>
                                <ul class="divide-y divide-red-100 dark:divide-red-500/20">
                                    @foreach($check['masalah'] as $masalah)
                                        <li class="px-3 py-2 flex items-start justify-between gap-3 bg-white dark:bg-slate-800/40">
                                            <div class="min-w-0">
                                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $masalah['judul'] }}</div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $masalah['detail'] }}</div>
                                            </div>
                                            <a href="{{ $masalah['tautan'] }}" class="shrink-0 text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">{{ $masalah['aksi'] ?? 'Perbaiki' }} &rarr;</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-[11px] text-slate-400 dark:text-slate-500">
        Penilaian memakai aturan JUKNIS BOSP (Permendikdasmen No. 8/2026): buku &ge; 10%, honor &le; 20% (negeri) / 40% (swasta),
        sarpras &le; 20%, Tahap I &ge; 50%, serta pagu per tahap maksimal 50% dari pagu 1 tahun.
    </p>
</div>
@endsection
