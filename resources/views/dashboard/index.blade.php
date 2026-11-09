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

    // Skor Kesiapan RKAS — dihitung di DashboardController (kondisional 6/7 item untuk gelondongan)
    // Fallback kalau view dirender tanpa controller (misal test lama)
    if (!isset($checks)) {
        $batasTahapFallback = ((float) ($summary['pagu_total'] ?? 0)) / 2;
        $checks = [
            ['label' => 'Pagu Tahap I & II masing-masing ≤ 50%', 'ok' => (float) ($tahunAnggaran->pagu_tahap1 ?? 0) <= $batasTahapFallback + 0.5
                && (float) ($tahunAnggaran->pagu_tahap2 ?? 0) <= $batasTahapFallback + 0.5],
            ['label' => 'Honor tidak melebihi batas', 'ok' => $summary['honor']['status'] === 'sesuai'],
            ['label' => 'Anggaran buku memenuhi minimum', 'ok' => $summary['buku']['status'] === 'sesuai'],
            ['label' => 'Sarpras tidak melebihi batas', 'ok' => $summary['sarpras']['status'] === 'sesuai'],
            ['label' => 'Alokasi Tahap I tepat 50%', 'ok' => $summary['tahap1']['status'] === 'sesuai'],
            ['label' => 'Alokasi Tahap II maksimal 50%', 'ok' => ($summary['tahap2']['status'] ?? 'sesuai') === 'sesuai'],
            ['label' => 'Total anggaran tidak melebihi pagu', 'ok' => $summary['sudah_dianggarkan'] <= $summary['pagu_total']],
        ];
        $okCount = collect($checks)->where('ok', true)->count();
        $score = round($okCount / count($checks) * 100);
    }
    if (!isset($okCount)) $okCount = collect($checks)->where('ok', true)->count();
    if (!isset($score)) $score = count($checks) > 0 ? round($okCount / count($checks) * 100) : 0;
    // Gelondongan — fallback jika controller belum passing (old tests)
    if (!isset($gelondongan)) {
        $gelondongan = ['rows' => [], 'jumlah_target' => null, 'jumlah' => 0, 'pagu_total' => $summary['pagu_total'] ?? 0];
    }

    $maxBulan = max(array_merge(array_values($bulanData), [1]));
    $maxJenis = max(array_merge(array_values($proporsiJenis), [1]));
    $totalBelanjaJenis = array_sum($proporsiJenis);

    // Persentase JUKNIS baru bermakna bila pagu sudah diisi DAN sudah ada
    // uraian anggaran. Tanpa itu, badge "SESUAI" menyesatkan pemakai baru.
    $juknisBisaDinilai = ((float) ($summary['pagu_total'] ?? 0)) > 0 && (int) ($totalItem ?? 0) > 0;
@endphp

<div class="space-y-6">
    <!-- Banner Update Aplikasi (auto cek, non-blocking) -->
    <div id="banner-update" class="hidden rounded-xl border-2 border-amber-300 dark:border-amber-500/40 bg-amber-50 dark:bg-amber-500/10 px-4 py-3 flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-start gap-3">
            <span class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0">⬆</span>
            <div>
                <div class="text-sm font-extrabold text-amber-800 dark:text-amber-300">Versi <span id="upd-versi-baru">-</span> tersedia</div>
                <div class="text-xs text-amber-700 dark:text-amber-300/80">Anda pakai v<span id="upd-versi-sekarang">-</span> — ukuran <span id="upd-ukuran">-</span> MB</div>
                <div id="upd-hint" class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">Unduh sekali (±35–45 MB), lalu instal manual — data RKAS tetap aman.</div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button id="btn-unduh-update" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold shadow">Unduh Sekarang</button>
            <a id="btn-unduh-manual" href="#" class="hidden text-xs font-bold text-amber-800 dark:text-amber-300 hover:underline">Unduh manual</a>
            <button onclick="document.getElementById('banner-update').classList.add('hidden')" class="px-3 py-2 text-xs text-amber-700 hover:underline">Nanti</button>
        </div>
    </div>
    <div id="banner-update-progress" class="hidden rounded-xl border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 px-4 py-3 text-sm text-blue-700 dark:text-blue-300">
        <span class="inline-flex items-center gap-2"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> <span id="upd-progress-text">Sedang mengunduh installer... mohon tunggu (jangan tutup aplikasi)</span></span>
        <div class="mt-2 h-2 rounded-full bg-blue-200/70 dark:bg-blue-500/20 overflow-hidden">
            <div id="upd-progress-bar" class="h-full bg-blue-600 dark:bg-blue-400 transition-[width] duration-300" style="width:0%"></div>
        </div>
    </div>
    <div id="banner-update-done" class="hidden rounded-xl border-2 border-emerald-300 dark:border-emerald-500/40 bg-emerald-50 dark:bg-emerald-500/10 px-4 py-3 flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="text-sm font-bold text-emerald-700 dark:text-emerald-400">✓ Unduhan selesai — klik <span class="underline">Simpan File Installer</span> untuk memilih lokasi penyimpanan.</div>
        <a id="btn-buka-installer" href="#" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow">Simpan File Installer</a>
    </div>

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Dashboard RKAS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $sekolah->nama_sekolah }} (NPSN {{ $sekolah->npsn }}) &middot; {{ $tahunAnggaran->sumber_dana }} {{ $tahunAnggaran->tahun ?? 2026 }}
                <span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400">Status: {{ ucfirst($summary['status_sekolah'] ?? 'negeri') }} &middot; Honor maks {{ $summary['honor']['batas_persen'] ?? 20 }}%</span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap lg:flex-nowrap">
            <button type="button" onclick="try{if(window.__karsaStartDashboardTour) window.__karsaStartDashboardTour(); else { const t=document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content||''; fetch('/tur/reset-semua',{method:'POST',headers:{'X-CSRF-TOKEN':t,'Accept':'application/json'},credentials:'same-origin'}).finally(()=>location.reload()); }}catch(e){}" class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl border border-amber-300 dark:border-amber-500/40 bg-amber-50 dark:bg-amber-500/10 text-sm font-semibold text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors" title="Mulai tur panduan dashboard">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Tur
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif

    <!-- Sapaan — kenalkan aplikasi (logo tidak mengganggu) -->
    <div class="card p-5 flex items-center gap-4" x-data="{ g: '' }" x-init="const h=new Date().getHours(); g=h<11?'Selamat pagi':h<15?'Selamat siang':h<18?'Selamat sore':'Selamat malam'">
        <div class="shrink-0 w-11 h-11 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 p-1.5 shadow-sm flex items-center justify-center">
            <img src="{{ asset('icons/logo.png') }}" alt="KARSA" class="w-full h-full object-contain">
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-bold text-slate-800 dark:text-white"><span x-text="g"></span>, {{ Auth::user()->name ?? 'Operator RKAS' }} 👋</div>
            <div class="text-xs font-semibold text-slate-600 dark:text-slate-300 mt-0.5"><span class="font-display font-extrabold">KARSA</span> — Kertas Kerja RKAS <span class="font-normal text-slate-500 dark:text-slate-400">• Aplikasi desktop offline pengganti Excel untuk menyusun RKAS secara mandiri — rujukan sebelum isi ARKAS</span></div>
        </div>
    </div>

    <!-- Kepatuhan komponen top -->
    <div id="tour-dashboard-juknis" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Honor Belanja', 'hint' => 'maks {p}% dari pagu', 'key' => 'honor', 'bar' => $summary['honor']['persen'] / max($summary['honor']['batas_persen'],1) * 100, 'color' => 'bg-blue-500'],
            ['label' => 'Anggaran Buku', 'hint' => 'min {p}% dari pagu', 'key' => 'buku', 'bar' => $summary['buku']['persen'] / max($summary['buku']['batas_persen'],1) * 100, 'color' => 'bg-indigo-500'],
            ['label' => 'Sarpras', 'hint' => 'maks {p}% dari pagu', 'key' => 'sarpras', 'bar' => $summary['sarpras']['persen'] / max($summary['sarpras']['batas_persen'],1) * 100, 'color' => 'bg-violet-500'],
            ['label' => 'Tahap I', 'hint' => 'tepat {p}% dari pagu', 'key' => 'tahap1', 'bar' => $summary['tahap1']['persen'] / max($summary['tahap1']['batas_maks_persen'] ?? $summary['tahap1']['batas_persen'],1) * 100, 'color' => 'bg-emerald-500'],
        ] as $comp)
            @php
                $d = $summary[$comp['key']];
                $hint = str_replace('{p}', $d['batas_persen'] ?? $d['batas_maks_persen'] ?? 50, $comp['hint']);
                $barColor = match ($d['status']) {
                    'melebihi' => 'bg-red-500',
                    'kurang' => 'bg-amber-500',
                    default => $comp['color'],
                };
            @endphp
            <div class="card p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $comp['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border whitespace-nowrap {{ $juknisBisaDinilai ? ($statusBg[$d['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200') : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-600' }}">{{ $juknisBisaDinilai ? ($statusLabel[$d['status']] ?? $d['status']) : 'BELUM DINILAI' }}</span>
                </div>
                <div class="text-2xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($d['total'], 0, ',', '.') }}</div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    @if($juknisBisaDinilai)
                        {{ number_format($d['persen'], 2, ',', '.') }}% &middot; {{ $hint }}
                    @else
                        Isi pagu &amp; uraian anggaran agar persentase bisa dihitung
                    @endif
                </div>
                <div class="mt-3 h-2 rounded-full bg-slate-100 dark:bg-slate-700/60 overflow-hidden">
                    <div class="h-full rounded-full {{ $barColor }} transition-all" style="width: {{ $juknisBisaDinilai ? min($comp['bar'], 100) : 0 }}%"></div>
                </div>
                @if($juknisBisaDinilai && $comp['key'] === 'tahap1' && ($d['status'] ?? '') === 'melebihi')
                    <div class="mt-2 text-[10px] font-semibold text-red-600 dark:text-red-400">
                        Kelebihan Rp {{ number_format($d['kelebihan'] ?? 0, 0, ',', '.') }} dari jatah — pindahkan ke Juli–Desember
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Statistik utama + Skor kesiapan -->
    @php $sisa = $summary['pagu_total'] - $summary['sudah_dianggarkan']; $sudahPct = $summary['pagu_total'] > 0 ? round($summary['sudah_dianggarkan'] / $summary['pagu_total'] * 100, 1) : 0; @endphp
    <div id="tour-dashboard-pagu" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card p-5 flex flex-col">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <span class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-500/15 flex items-center justify-center text-blue-600 dark:text-blue-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v3m2 4v1a3 3 0 003 3h6a3 3 0 003-3v-1m-6-4l-3-3m0 0l-3 3m3-3v12"/></svg></span>
                    Pagu Total
                </div>
                <div class="text-xl font-extrabold text-slate-800 dark:text-white mt-3">Rp {{ number_format($summary['pagu_total'], 0, ',', '.') }}</div>
                <div class="mt-3 space-y-1.5">
                    @foreach([
                        ['Tahap I', 'Jan–Jun', $summary['tahap1']],
                        ['Tahap II', 'Jul–Des', $summary['tahap2']],
                    ] as [$namaTahap, $rentang, $t])
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 px-2.5 py-1.5">
                            <div class="flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wide">
                                <span class="text-slate-500 dark:text-slate-400">{{ $namaTahap }} <span class="font-normal normal-case tracking-normal">{{ $rentang }}</span></span>
                                <span class="{{ $t['status'] === 'melebihi' ? 'text-red-600 dark:text-red-400' : ($t['status'] === 'kurang' ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">{{ number_format($t['persen'], 2, ',', '.') }}%</span>
                            </div>
                            <div class="mt-0.5 text-[11px] leading-tight">
                                <span class="font-bold {{ $t['status'] === 'melebihi' ? 'text-red-600 dark:text-red-400' : ($t['status'] === 'kurang' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200') }}">Rp {{ number_format($t['total'], 0, ',', '.') }}</span>
                                <span class="text-slate-400 dark:text-slate-500">/ {{ number_format($t['batas_maks_nominal'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 text-[10px] text-slate-400 dark:text-slate-500">Jatah tiap tahap 50% dari pagu 1 tahun.</div>
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
                <div class="mt-3 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden" title="Terpakai {{ $sudahPct }}% dari pagu">
                    <div class="h-full {{ $sisa < 0 ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min($sudahPct, 100) }}%"></div>
                </div>
            </div>
        </div>

        <!-- Skor Kesiapan -->
        <div id="tour-dashboard-kesiapan" class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Kesiapan RKAS</h3>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $kesiapan['belum_ada_item'] ? 'belum ada data' : $okCount.'/'.$kesiapan['total'] }}</span>
                    <a href="{{ route('kesiapan.index', ['tahun' => $tahunAnggaran->tahun]) }}" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">Detail &rarr;</a>
                </div>
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
                        @if($kesiapan['belum_ada_item'])
                            Belum ada anggaran diinput
                        @else
                            {{ $score >= 100 ? 'Siap untuk pengesahan' : ($score >= 60 ? 'Mendekati siap' : 'Masih perlu dilengkapi') }}
                        @endif
                    </div>
                    <div class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                        @if($kesiapan['belum_ada_item'])
                            Tambah uraian di Lembar Kerja agar skor mulai terhitung.
                        @elseif($score >= 100)
                            Semua syarat kepatuhan terpenuhi.
                        @else
                            Selesaikan {{ $kesiapan['total'] - $okCount }} syarat lagi.
                        @endif
                    </div>
                </div>
            </div>
            @php
                $gagalCek = collect($checks)->filter(fn ($c) => ! empty($c['dinilai']) && empty($c['ok']));
                $belumCek = collect($checks)->filter(fn ($c) => empty($c['dinilai']));
            @endphp
            @if($gagalCek->isEmpty())
                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Semua {{ $kesiapan['total'] }} syarat terpenuhi — siap disahkan.
                </div>
            @else
                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-2">Belum terpenuhi ({{ $gagalCek->count() }})</div>
                <ul class="space-y-2 max-h-56 overflow-y-auto pr-1">
                    @foreach($gagalCek as $c)
                        <li class="flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex items-center justify-center w-4 h-4 rounded-full shrink-0 bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                            </span>
                            <span class="leading-snug">{{ $c['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">
                    @if($kesiapan['belum_ada_item'])
                        Mulai dari mengisi Pagu Total di menu <span class="font-semibold">Pagu &amp; Status</span>, lalu tambah uraian di Lembar Kerja.
                    @else
                        {{ $okCount }} syarat sudah terpenuhi &mdash; buka Detail untuk perbaikan per temuan.
                        @if($belumCek->isNotEmpty())
                            <br>{{ $belumCek->count() }} syarat lain menunggu data (belum dinilai).
                        @endif
                    @endif
                </div>
            @endif
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
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-5">Jenis belanja resmi ARKAS terhadap total belanja (hanya yang sudah terpakai).</p>
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
    <div class="card overflow-hidden" id="ringkasan-juknis">
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
                        ['Alokasi Tahap II', $summary['tahap2']],
                    ] as [$label, $data])
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-6 py-3.5 font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-200">Rp {{ number_format($data['total'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-300">{{ number_format($data['persen'], 2, ',', '.') }}%</td>
                            <td class="px-4 py-3.5 text-right text-slate-500 dark:text-slate-400">{{ $data['batas_persen'] ?? $data['batas_maks_persen'] ?? 0 }}%</td>
                            <td class="px-4 py-3.5 text-right {{ ($data['sisa'] ?? 0) < 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-500 dark:text-slate-400' }}">
                                @if(($data['sisa'] ?? 0) < 0) Lebih @endif Rp {{ number_format(abs($data['sisa'] ?? 0), 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $statusBg[$data['status']] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ $statusLabel[$data['status']] ?? strtoupper($data['status']) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan RKA Gelondongan — reuse style & service yang sama dengan Monitoring JUKNIS -->
    <div class="card overflow-hidden" id="ringkasan-rka-gelondongan">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Ringkasan RKA Gelondongan</h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Rekap 3 kategori sesuai file PAK/RKA Dinas — target diisi di Pengaturan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                        <th class="px-6 py-3 text-left font-semibold">Kategori</th>
                        <th class="px-4 py-3 text-right font-semibold">Target</th>
                        <th class="px-4 py-3 text-right font-semibold">Realisasi</th>
                        <th class="px-4 py-3 text-right font-semibold">Selisih</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($gelondongan['rows'] ?? [] as $row)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-6 py-3.5 font-semibold text-slate-700 dark:text-slate-200">{{ $row['label'] }}</td>
                            <td class="px-4 py-3.5 text-right">
                                @if($row['target'] === null)
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-700/40 dark:text-slate-400 dark:border-slate-600">Target belum diisi</span>
                                @else
                                    <span class="text-slate-700 dark:text-slate-200">Rp {{ number_format($row['target'], 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right text-slate-700 dark:text-slate-200">Rp {{ number_format($row['realisasi'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3.5 text-right text-slate-500 dark:text-slate-400">
                                @if($row['target'] === null)
                                    <span class="text-slate-400">—</span>
                                @else
                                    Rp {{ number_format(abs($row['selisih']), 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($row['status'] === 'sesuai')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">SESUAI</span>
                                @elseif($row['status'] === 'kurang')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30">KURANG</span>
                                @elseif($row['status'] === 'lebih')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30">LEBIH</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border bg-slate-50 text-slate-500 border-slate-200 dark:bg-slate-700/40 dark:text-slate-400 dark:border-slate-600">BELUM DIISI</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-xs text-slate-400">Belum ada data gelondongan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let updInfo = null;
  async function cekUpdate(){
    try{
      const r = await fetch('/update/cek', {headers:{'Accept':'application/json'}});
      if(!r.ok) return;
      const j = await r.json();
      if(!j.ada_update) return;
      updInfo = j;
      document.getElementById('upd-versi-baru').textContent = j.versi_baru;
      document.getElementById('upd-versi-sekarang').textContent = j.versi_sekarang;
      document.getElementById('upd-ukuran').textContent = j.ukuran_mb;
      document.getElementById('banner-update').classList.remove('hidden');
      document.getElementById('banner-update').classList.add('flex');
    }catch(e){}
  }
  const fmtUnduh = (b) => (b/1048576).toFixed(1) + ' MB';
  function tampilkanProgresUnduh(text, persen){
    const txt = document.getElementById('upd-progress-text');
    const bar = document.getElementById('upd-progress-bar');
    if(txt) txt.textContent = text;
    if(bar) bar.style.width = Math.max(0, Math.min(100, persen||0)) + '%';
  }
  async function unduh(){
    if(!updInfo) return;
    document.getElementById('banner-update').classList.add('hidden');
    document.getElementById('banner-update-progress').classList.remove('hidden');
    tampilkanProgresUnduh('Menyiapkan unduhan…', 0);
    try{
      if(typeof window.karsaUnduhUpdate !== 'function') throw new Error('Fungsi unduh belum siap — muat ulang halaman.');
      await window.karsaUnduhUpdate(updInfo.url_unduh, {
        onProgress(msg){
          if(msg.phase === 'start'){
            tampilkanProgresUnduh(msg.already ? 'File installer sudah ada — memeriksa…' : 'Menghubungi server unduhan…', 0);
            return;
          }
          const total = msg.total || updInfo.size_bytes || 0;
          const now = msg.downloaded || 0;
          const persen = total > 0 ? (now / total * 100) : 0;
          tampilkanProgresUnduh(
            total > 0
              ? 'Mengunduh ' + fmtUnduh(now) + ' / ' + fmtUnduh(total) + ' (' + Math.round(persen) + '%)'
              : 'Mengunduh ' + fmtUnduh(now),
            persen
          );
        },
        onDone(j){
          document.getElementById('banner-update-progress').classList.add('hidden');
          document.getElementById('btn-buka-installer').href = '/update/download-file/' + encodeURIComponent(j.file);
          document.getElementById('banner-update-done').classList.remove('hidden');
          document.getElementById('banner-update-done').classList.add('flex');
          if(window.karsaToast) window.karsaToast(j.already ? 'File installer sudah ada — klik "Simpan File Installer" untuk memilih lokasi.' : 'Unduhan selesai — klik "Simpan File Installer" untuk memilih lokasi penyimpanan.', 'success', 10000);
        },
        onError(j){
          document.getElementById('banner-update-progress').classList.add('hidden');
          document.getElementById('banner-update').classList.remove('hidden');
          document.getElementById('banner-update').classList.add('flex');
          if(window.karsaToast) window.karsaToast(j.message || 'Gagal mengunduh installer.', 'error', 12000);
        }
      });
    }catch(e){
      document.getElementById('banner-update-progress').classList.add('hidden');
      document.getElementById('banner-update').classList.remove('hidden');
      document.getElementById('banner-update').classList.add('flex');
      if(window.karsaToast) window.karsaToast('Gagal mengunduh: '+e.message, 'error', 12000);
    }
  }
  const desktop = !!window.karsaDesktop;
  const btnUtama = document.getElementById('btn-unduh-update');
  const btnManual = document.getElementById('btn-unduh-manual');
  if(desktop){
    if(btnUtama) btnUtama.textContent = 'Perbarui Sekarang';
    if(btnManual) btnManual.classList.remove('hidden');
    const hint = document.getElementById('upd-hint');
    if(hint) hint.textContent = 'Pemasangan otomatis — aplikasi menutup dan terbuka kembali dengan versi baru. Data RKAS tetap aman.';
  }

  async function perbaruiOtomatis(){
    if(!updInfo) return;
    const yakin = window.confirm('Perbarui KARSA ke versi ' + updInfo.versi_baru + ' sekarang?\n\nAplikasi akan tertutup dan terbuka kembali otomatis setelah pemasangan selesai. Data RKAS tidak dihapus.');
    if(!yakin) return;

    document.getElementById('banner-update').classList.add('hidden');
    document.getElementById('banner-update-progress').classList.remove('hidden');
    tampilkanProgresUnduh('Memeriksa pembaruan…', 0);

    try{
      if(typeof window.karsaPasangUpdate !== 'function') throw new Error('Fitur pembaruan belum siap — muat ulang aplikasi.');
      const hasil = await window.karsaPasangUpdate({
        onProgress(s){
          if(s.tahap === 'unduh'){
            const total = s.total || updInfo.size_bytes || 0;
            const now = s.terunduh || 0;
            const persen = total > 0 ? (now / total * 100) : (s.persen || 0);
            tampilkanProgresUnduh(
              total > 0
                ? 'Mengunduh ' + fmtUnduh(now) + ' / ' + fmtUnduh(total) + ' (' + Math.round(persen) + '%)'
                : 'Mengunduh ' + fmtUnduh(now),
              persen
            );
          } else if(s.tahap === 'pasang'){
            tampilkanProgresUnduh('Memasang pembaruan… aplikasi akan menutup dan terbuka kembali.', 100);
          } else if(s.tahap === 'cek'){
            tampilkanProgresUnduh('Memeriksa pembaruan…', 0);
          }
        }
      });
      if(hasil === 'terbaru'){
        document.getElementById('banner-update-progress').classList.add('hidden');
        if(window.karsaToast) window.karsaToast('KARSA sudah versi terbaru.', 'info', 8000);
      }
    }catch(e){
      document.getElementById('banner-update-progress').classList.add('hidden');
      document.getElementById('banner-update').classList.remove('hidden');
      document.getElementById('banner-update').classList.add('flex');
      const pesan = (e && (e.message || e)) || 'tidak diketahui';
      if(window.karsaToast) window.karsaToast('Gagal memperbarui otomatis: ' + pesan + ' — silakan pakai "Unduh manual".', 'error', 14000);
    }
  }

  btnUtama?.addEventListener('click', () => { if(desktop) perbaruiOtomatis(); else unduh(); });
  btnManual?.addEventListener('click', (ev) => { ev.preventDefault(); unduh(); });
  if(document.readyState==='complete') setTimeout(cekUpdate,800);
  else window.addEventListener('load',()=> setTimeout(cekUpdate,800));
})();
</script>
@endsection
