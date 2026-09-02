@extends('layouts.app')

@section('content')
@php
    $bulanIndonesia = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
@endphp

<div class="space-y-6">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-400">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-400">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Lembar Kerja RKAS {{ $tahunAnggaran->tahun }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $tahunAnggaran->sumber_dana }} &mdash; {{ $sekolah->nama_sekolah }}
                (NPSN {{ $sekolah->npsn }})
                <span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full text-[11px] font-bold border
                    @if($tahunAnggaran->status_pengesahan === 'Disahkan') bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30
                    @elseif($tahunAnggaran->status_pengesahan === 'Pergeseran') bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30
                    @else bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600
                    @endif">{{ $tahunAnggaran->status_pengesahan }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('rkas.export') }}" class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 dark:hover:bg-slate-700/50 transition-colors" title="Ringkasan cepat (flat)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel Ringkas
            </a>
            <a href="{{ route('rkas.export-grouped') }}" class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl border border-indigo-300 dark:border-indigo-600 text-sm font-semibold text-indigo-600 dark:text-indigo-300 hover:bg-indigo-50 hover:border-indigo-400 dark:hover:bg-indigo-500/10 transition-colors" title="Per kegiatan + 12 bulan (untuk paste ke ARKAS)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h13M3 12h13M3 16h13M3 20h13M16 8l3 3-3 3"/></svg>
                Excel Bulanan
            </a>
            <a href="{{ route('rkas.pdf') }}" class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors" title="Ringkasan cepat">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                PDF Ringkas
            </a>
            <a href="{{ route('rkas.pdf-grouped') }}" class="inline-flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors" title="Per kegiatan + 12 bulan">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                PDF Bulanan
            </a>
            @if($tahunAnggaran->status_pengesahan !== 'Disahkan')
            <button type="button" id="btn-open-modal" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Anggaran
            </button>
            @else
            <span class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-400 dark:text-slate-500 text-sm font-semibold border border-slate-200 dark:border-slate-600 cursor-not-allowed" title="RKAS terkunci — buka kembali di Pengaturan untuk revisi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Terkunci
            </span>
            @endif
        </div>
    </div>

    @if($tahunAnggaran->status_pengesahan === 'Disahkan')
        <div class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-400 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <div>
                <div class="font-bold">RKAS ini sudah disahkan. Data terkunci — tidak bisa ditambah/diedit/dihapus.</div>
                <div class="text-xs mt-1 opacity-80">Jika perlu revisi, buka kembali dari <a href="{{ route('pengaturan.index') }}" class="underline font-semibold hover:no-underline">halaman Pengaturan → Status RKAS</a>.</div>
            </div>
        </div>
    @elseif($tahunAnggaran->status_pengesahan === 'Pergeseran')
        <div class="px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-300 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <div class="font-bold">RKAS ini sedang dalam masa revisi (dibuka kembali dari status Disahkan).</div>
                <div class="text-xs mt-1 opacity-80">Perubahan tetap tercatat di audit log. Sahkan kembali jika revisi selesai.</div>
            </div>
        </div>
    @endif

    @php
        $tahap1Total = \App\Models\RkasItemBulan::whereHas('item', fn($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))->whereBetween('bulan', [1, 6])->sum('jumlah');
        $tahap1Pct = $tahunAnggaran->pagu_total > 0 ? round($tahap1Total / $tahunAnggaran->pagu_total * 100) : 0;
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-5">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Pagu Total</div>
            <div class="text-lg md:text-xl font-extrabold text-slate-800 dark:text-white mt-1">Rp {{ number_format($tahunAnggaran->pagu_total, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Tahap I: Rp {{ number_format($tahunAnggaran->pagu_tahap1, 0, ',', '.') }} &middot; Tahap II: Rp {{ number_format($tahunAnggaran->pagu_tahap2, 0, ',', '.') }}</div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Sudah Dianggarkan</div>
            <div class="text-lg md:text-xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">Rp {{ number_format($totalSudahDianggarkan, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $items->count() }} item belanja</div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Belum Dianggarkan <span class="normal-case">&middot; Sisa Pagu</span></div>
            <div class="text-lg md:text-xl font-extrabold {{ $sisaPagu >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600' }} mt-1">Rp {{ number_format($sisaPagu, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $sisaPagu >= 0 ? 'Siap untuk tambahan kegiatan (harus Rp 0 sebelum pengesahan)' : 'Melebihi pagu total' }}</div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Capaian Tahap I (min 50%)</div>
            <div class="text-lg md:text-xl font-extrabold {{ $tahap1Pct >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600' }} mt-1">Rp {{ number_format($tahap1Total, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $tahap1Pct }}% dari pagu &middot; {{ $tahap1Pct >= 50 ? 'memenuhi minimal 50%' : 'belum mencapai minimal 50%' }}</div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 lg:px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Kertas Kerja &mdash; Daftar Kegiatan &amp; Rincian Anggaran</h2>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Pilih bulan untuk melihat rencana pelaksanaan. Tiap kegiatan menampilkan total anggaran yang sudah dianggarkan.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($selectedBulan > 0)
                        <a href="{{ route('rkas.index', ['bulan' => 0]) }}" class="text-xs text-slate-400 dark:text-slate-500 hover:text-red-500">Reset</a>
                    @endif
                </div>
            </div>
            <form method="GET" action="{{ route('rkas.index') }}" class="mt-4">
                <div class="flex gap-1.5 overflow-x-auto pb-1 items-center">
                    <button type="submit" name="bulan" value="0" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $selectedBulan === 0 ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600/50' }}">Semua</button>
                    @for($b = 1; $b <= 12; $b++)
                        <button type="submit" name="bulan" value="{{ $b }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors {{ $selectedBulan === $b ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600/50' }}">{{ substr($bulanIndonesia[$b], 0, 3) }}</button>
                    @endfor
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-3 text-center font-semibold w-12">No <span class="normal-case text-[9px] font-normal text-slate-400">per kegiatan / #global</span></th>
                        <th class="px-4 py-3 text-left font-semibold min-w-[280px]">Uraian &amp; Keterangan Khusus</th>
                        <th class="px-4 py-3 text-left font-semibold min-w-[220px]">Kode Rekening &amp; Jenis Belanja</th>
                        <th class="px-4 py-3 text-right font-semibold min-w-[120px]">Volume</th>
                        <th class="px-4 py-3 text-right font-semibold min-w-[140px]">Harga Satuan</th>
                        <th class="px-4 py-3 text-right font-semibold min-w-[150px]">Total Anggaran</th>
                        <th class="px-4 py-3 text-left font-semibold min-w-[150px]">Bulan Pelaksanaan</th>
                        <th class="px-4 py-3 text-center font-semibold w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($kegiatanGroups as $g)
                        <tr class="bg-slate-100/80 dark:bg-slate-800/80">
                            <td colspan="8" class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-3">
                                        <span class="px-2 py-1 rounded-md bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300 text-[11px] font-bold">{{ $g['kode'] }}</span>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-white text-sm">{{ $g['nama'] }}</div>
                                            @if($g['sub_program'])
                                                <div class="text-[11px] text-slate-400 dark:text-slate-500">Sub Program: {{ $g['sub_program'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                        @if($tahunAnggaran->status_pengesahan !== 'Disahkan')
                                        <button type="button" data-action="sisip-kegiatan" data-kegiatan-id="{{ $g['id'] }}" data-kegiatan-text="[{{ $g['kode'] }}] {{ $g['nama'] }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-slate-600 border border-blue-200 dark:border-slate-600 shadow-xs transition-colors" title="Sisipkan belanja baru pada kegiatan ini">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            <span>Sisip Uraian</span>
                                        </button>
                                        @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700/50 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-600 cursor-not-allowed" title="Terkunci — RKAS sudah disahkan"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg> Terkunci</span>
                                        @endif
                                        <span>{{ $g['jumlah_item'] }} item</span>
                                        @if($selectedBulan > 0)
                                            <span class="inline-flex items-center gap-1 font-bold text-blue-600 dark:text-blue-400">Bulan {{ $bulanIndonesia[$selectedBulan] }}: Rp {{ number_format($g['total_bulan'], 0, ',', '.') }}</span>
                                            <span class="text-[11px] text-slate-400">1 Thn: Rp {{ number_format($g['total_sudah'], 0, ',', '.') }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 font-bold text-indigo-600 dark:text-indigo-400">Sudah Dianggarkan: Rp {{ number_format($g['total_sudah'], 0, ',', '.') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @foreach($g['items'] as $item)
                        @php
                            $aktif = $item->alokasiBulan->where('volume', '>', 0);
                            $labelBulan = $aktif->map(fn($ab) => $bulanIndonesia[$ab->bulan])->implode(', ');
                            $manyBulan = $aktif->count();
                            $volRow = $selectedBulan > 0 ? ($item->volume_bulan ?? 0) : $item->volume;
                            $jmlRow = $selectedBulan > 0 ? ($item->jumlah_bulan ?? 0) : $item->jumlah_koreksi;
                        @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-4 text-center">
                                <div class="font-bold text-slate-800 dark:text-slate-100">{{ $loop->iteration }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500">#{{ $item->no_urut }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-slate-800 dark:text-slate-100 font-medium">{{ $item->uraian }}</div>
                                @if($item->keterangan_kustom)
                                    <div class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">
                                        📌 {{ $item->keterangan_kustom }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-slate-700 dark:text-slate-200 font-medium">{{ $item->kodeRekening->nama ?? '-' }}</div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $item->kodeRekening->kode ?? '' }} &middot; {{ $item->kodeRekening->jenisBelanja->nama ?? '' }}</div>
                            </td>
                            <td class="px-4 py-4 text-right text-slate-700 dark:text-slate-200">
                                <div class="font-semibold">{{ rtrim(rtrim(number_format($volRow, 2, ',', '.'), '0'), ',') }} <span class="text-xs font-normal text-slate-500">{{ $item->satuan }}</span></div>
                                @if($selectedBulan > 0 && (float)$item->volume !== (float)$volRow)
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500">1 Thn: {{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }} {{ $item->satuan }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right text-slate-700 dark:text-slate-200">
                                <div class="font-medium">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</div>
                                <div class="flex items-center justify-end gap-1.5 mt-0.5">
                                    @if($item->kontrol === 'OK')
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            <span class="w-1 h-1 rounded-full bg-emerald-500"></span>OK
                                        </span>
                                    @else
                                        <span title="Harga berbeda dari acuan ARKAS: Rp {{ number_format($item->harga_satuan_arkas, 0, ',', '.') }}" class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                            <span class="w-1 h-1 rounded-full bg-amber-500"></span>SELISIH
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4 text-right font-bold text-slate-800 dark:text-white">
                                <div>Rp {{ number_format($jmlRow, 0, ',', '.') }}</div>
                                @if((float)$item->koreksi !== 0.0 && $selectedBulan === 0)
                                    <div class="text-[10px] font-normal text-amber-600 dark:text-amber-400">(Koreksi: {{ $item->koreksi > 0 ? '+' : '' }}Rp {{ number_format($item->koreksi, 0, ',', '.') }})</div>
                                @endif
                                @if($selectedBulan > 0 && (float)$item->jumlah_koreksi !== (float)$jmlRow)
                                    <div class="text-[10px] font-normal text-slate-400 dark:text-slate-500">1 Thn: Rp {{ number_format($item->jumlah_koreksi, 0, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-500 dark:text-slate-400">
                                <span class="text-xs font-semibold">{{ $manyBulan }} bulan</span>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 block leading-snug">{{ $manyBulan < 12 && $manyBulan > 0 ? $labelBulan : ($manyBulan === 12 ? 'Januari s.d. Desember' : '-') }}</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                @if($tahunAnggaran->status_pengesahan !== 'Disahkan')
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" data-action="sisip-item"
                                        data-kegiatan-id="{{ $item->master_program_id }}"
                                        data-kegiatan-text="[{{ $item->program->kode ?? '' }}] {{ $item->program->nama ?? '' }}"
                                        data-rekening-id="{{ $item->master_kode_rekening_id }}"
                                        data-rekening-text="[{{ $item->kodeRekening->kode ?? '' }}] {{ $item->kodeRekening->nama ?? '' }}"
                                        title="Sisipkan belanja baru pada rekening ini" class="p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/15 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <button type="button" data-action="edit" data-id="{{ $item->id }}" title="Ubah Anggaran" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/15 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" data-action="delete" data-id="{{ $item->id }}" title="Hapus Anggaran" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/15 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                                @else
                                <span class="inline-flex items-center justify-center p-1.5 text-slate-300 dark:text-slate-600" title="Terkunci — RKAS sudah disahkan"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-slate-400 dark:text-slate-500">
                                <div class="text-sm font-semibold">Belum ada rincian anggaran</div>
                                <div class="text-xs mt-1">Klik tombol &ldquo;Sisip Uraian Anggaran&rdquo; untuk mulai menyusun RKAS {{ $tahunAnggaran->tahun }}.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($items->isNotEmpty())
                <tfoot class="bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700/60 text-sm">
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-right font-bold text-slate-600 dark:text-slate-300">Total Keseluruhan</td>
                        <td class="px-4 py-4 text-right font-extrabold text-blue-700 dark:text-blue-400 text-base">Rp {{ number_format($items->sum('jumlah_koreksi'), 0, ',', '.') }}</td>
                        <td colspan="2" class="px-4 py-4 text-[11px] text-slate-400 dark:text-slate-500">Tahap I: Rp {{ number_format($items->sum(fn($i) => $i->alokasiBulan->whereBetween('bulan',[1,6])->sum('jumlah')), 0, ',', '.') }} &middot; Tahap II: Rp {{ number_format($items->sum(fn($i) => $i->alokasiBulan->whereBetween('bulan',[7,12])->sum('jumlah')), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div id="rkas-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" style="background-color: rgba(15,23,42,.6); backdrop-filter: blur(3px);">
        <div class="min-h-full flex items-center justify-center p-4" x-data="modalForm()">
            <div class="bg-white dark:bg-slate-800 w-full max-w-5xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh] ring-1 ring-slate-200 dark:ring-slate-700/60">
                <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200 dark:border-slate-700/60">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800 dark:text-white" x-text="mode === 'sisip' ? 'Sisip Uraian — Detail Anggaran' : mode === 'edit' ? 'Ubah Anggaran — Detail Kegiatan' : 'Tambah Anggaran — Detail Kegiatan'"></h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ $tahunAnggaran->sumber_dana }} Tahun {{ $tahunAnggaran->tahun }} <span x-show="mode === 'sisip'" x-cloak class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 font-semibold">mode sisip — otomatis terisi dari baris sumber</span></p>
                    </div>
                    <button @click="close()" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div x-show="mode === 'sisip' && sisipContext" x-cloak class="mx-6 mt-4 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30 text-xs text-amber-800 dark:text-amber-300 flex items-start justify-between gap-3">
                    <div>
                        <div class="font-bold">Sisip dari: <span x-text="sisipContext"></span></div>
                        <div class="text-[11px] opacity-80 mt-0.5">Kegiatan &amp; rekening otomatis terisi — tetap bisa diubah manual.</div>
                    </div>
                    <button type="button" @click="clearSisip()" class="shrink-0 text-[11px] font-semibold underline hover:no-underline">Kosongkan</button>
                </div>

                <!-- Step indicator -->
                <div class="mx-6 mt-4 flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span :class="step===1 ? 'bg-blue-600 text-white' : 'bg-emerald-500 text-white'" class="w-7 h-7 rounded-full flex items-center justify-center font-bold">1</span>
                        <span :class="step===1 ? 'text-slate-800 dark:text-white font-bold' : 'text-emerald-600 dark:text-emerald-400 font-semibold'">Pilih Kegiatan & Rekening</span>
                    </div>
                    <div class="flex-1 h-0.5" :class="step===2 ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-700'"></div>
                    <div class="flex items-center gap-2">
                        <span :class="step===2 ? 'bg-blue-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'" class="w-7 h-7 rounded-full flex items-center justify-center font-bold">2</span>
                        <span :class="step===2 ? 'text-slate-800 dark:text-white font-bold' : 'text-slate-400'">Detail Uraian & Alokasi</span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                    <!-- STEP 1 -->
                    <div x-show="step===1" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Kegiatan <span class="text-red-500">*</span></label>
                            <div class="relative" @click.outside="pickers.kegiatan.open = false">
                                <input type="text" x-model="pickers.kegiatan.q" @input="debouncedSearch('kegiatan')" placeholder="Ketik untuk mencari kegiatan dari 8 SNP..." autocomplete="off"
                                    @focus="if (pickers.kegiatan.q) pickers.kegiatan.open = true"
                                    class="input">
                                <template x-if="pickers.kegiatan.open && pickers.kegiatan.loading"><div class="absolute z-20 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600/60 rounded-lg shadow-lg px-3 py-2 text-xs text-slate-400">Mencari...</div></template>
                                <template x-if="pickers.kegiatan.open && !pickers.kegiatan.loading">
                                    <ul class="absolute z-20 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600/60 rounded-xl shadow-lg max-h-60 overflow-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                                        <template x-for="r in pickers.kegiatan.results" :key="r.id">
                                            <li @click="selectKegiatan(r)" class="px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-500/15 cursor-pointer">
                                                <div class="text-sm text-slate-700 dark:text-slate-200" x-text="r.text"></div>
                                                <div class="text-[11px] text-slate-400 dark:text-slate-500" x-text="'Sub Program: ' + r.subtext"></div>
                                            </li>
                                        </template>
                                        <template x-if="pickers.kegiatan.results.length === 0"><li class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500">Tidak ada data cocok.</li></template>
                                    </ul>
                                </template>
                                <template x-if="pickers.kegiatan.label"><div class="mt-1 flex items-center gap-1.5 text-[11px]"><span class="text-emerald-600 dark:text-emerald-400" x-text="'Terpilih: ' + pickers.kegiatan.label"></span><span x-show="mode === 'sisip'" x-cloak class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 text-[10px] font-bold">otomatis terisi</span></div></template>
                            </div>
                        </div>
                        <div>
                            <label class="label">Kode Rekening Belanja <span class="text-red-500">*</span></label>
                            <div class="relative" @click.outside="pickers.rekening.open = false">
                                <input type="text" x-model="pickers.rekening.q" @input="debouncedSearch('rekening')" placeholder="Ketik untuk mencari kode rekening belanja..." autocomplete="off"
                                    @focus="if (pickers.rekening.q) pickers.rekening.open = true"
                                    class="input">
                                <template x-if="pickers.rekening.open && pickers.rekening.loading"><div class="absolute z-20 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600/60 rounded-lg shadow-lg px-3 py-2 text-xs text-slate-400">Mencari...</div></template>
                                <template x-if="pickers.rekening.open && !pickers.rekening.loading">
                                    <ul class="absolute z-20 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600/60 rounded-xl shadow-lg max-h-60 overflow-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                                        <template x-for="r in pickers.rekening.results" :key="r.id">
                                            <li @click="selectRekening(r)" class="px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-500/15 cursor-pointer">
                                                <div class="text-sm text-slate-700 dark:text-slate-200" x-text="r.text"></div>
                                                <div class="text-[11px] text-slate-400 dark:text-slate-500" x-text="r.subtext"></div>
                                            </li>
                                        </template>
                                        <template x-if="pickers.rekening.results.length === 0"><li class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500">Tidak ada data cocok.</li></template>
                                    </ul>
                                </template>
                                <template x-if="pickers.rekening.label"><div class="mt-1 flex items-center gap-1.5 text-[11px]"><span class="text-emerald-600 dark:text-emerald-400" x-text="'Terpilih: ' + pickers.rekening.label"></span><span x-show="mode === 'sisip'" x-cloak class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 text-[10px] font-bold">otomatis terisi</span></div></template>
                            </div>
                        </div>
                    </div>
                    </div>
                    <!-- STEP 2 -->
                    <div x-show="step===2" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Uraian <span class="text-red-500">*</span></label>
                            <div class="relative" @click.outside="pickers.barang.open = false">
                                <textarea x-model="form.uraian" maxlength="500" rows="2" placeholder="Ketik uraian bebas. Mulai mengetik untuk melihat saran barang dari katalog..." class="input resize-none"
                                    @input="searchUraianSuggestion($event)"
                                    @focus="if (pickers.barang.q && pickers.barang.results.length) pickers.barang.open = true"></textarea>
                                <template x-if="pickers.barang.open && pickers.barang.loading"><div class="absolute z-20 inset-x-0 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600/60 rounded-lg shadow-lg px-3 py-2 text-xs text-slate-400">Mencari...</div></template>
                                <template x-if="pickers.barang.open && !pickers.barang.loading">
                                    <ul class="absolute z-20 inset-x-0 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600/60 rounded-xl shadow-lg max-h-60 overflow-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                                        <template x-for="r in pickers.barang.results" :key="r.id">
                                            <li @click="applyBarangSuggestion(r)" class="px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-500/15 cursor-pointer">
                                                <div class="text-sm text-slate-700 dark:text-slate-200 font-medium" x-text="r.nama"></div>
                                                <div class="text-[11px] text-slate-400 dark:text-slate-500" x-text="r.subtext"></div>
                                            </li>
                                        </template>
                                        <template x-if="pickers.barang.results.length === 0"><li class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500">Tidak ada data cocok.</li></template>
                                    </ul>
                                </template>
                            </div>
                            <div class="mt-0.5 flex items-center justify-between gap-2">
                                <span class="text-[10px] text-slate-400 dark:text-slate-500" x-show="pickers.barang.open" x-cloak>Saran dari katalog barang resmi ARKAS</span>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 ml-auto" x-text="form.uraian.length + ' / 500'"></span>
                            </div>
                        </div>
                        <div>
                            <label class="label">Keterangan Khusus / Peruntukan Anggaran</label>
                            <textarea x-model="form.keterangan_kustom" maxlength="255" rows="2" placeholder="Contoh: Pemeliharaan Ruang Kelas 1" class="input resize-none"></textarea>
                            <div class="text-[10px] text-slate-400 mt-0.5">Ketikan bebas untuk membedakan peruntukan walau kode rekening sama.</div>
                        </div>
                    </div>

                    <div>
                        <label class="label">Harga Satuan yang Dianggarkan <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-1">
                            <span class="text-sm text-slate-400">Rp</span>
                            <input type="text" inputmode="numeric" x-bind:value="form.harga_satuan.toLocaleString('id-ID')"
                                @input="form.harga_satuan = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0"
                                placeholder="0" class="input !w-auto max-w-xs font-bold text-slate-800 dark:text-white">
                        </div>

                        {{-- SSH / Batas Harga Info & Warning --}}
                        <div x-show="form.harga_min > 0 || form.harga_max > 0" class="mt-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 text-xs">
                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Standar Satuan Harga (SSH)</span>
                                <span>Batas: Rp <span x-text="form.harga_min.toLocaleString('id-ID')"></span> s.d. Rp <span x-text="form.harga_max.toLocaleString('id-ID')"></span></span>
                            </div>
                            <div x-show="form.harga_max > 0 && form.harga_satuan > form.harga_max" class="mt-1.5 text-[11px] font-bold text-red-600 dark:text-red-400 flex items-center gap-1">
                                ⚠️ Harga melebihi Batas Atas SSH (Maks Rp <span x-text="form.harga_max.toLocaleString('id-ID')"></span>)
                            </div>
                            <div x-show="form.harga_min > 0 && form.harga_satuan < form.harga_min && form.harga_satuan > 0" class="mt-1.5 text-[11px] font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                ℹ️ Harga di bawah Batas Bawah SSH (Min Rp <span x-text="form.harga_min.toLocaleString('id-ID')"></span>)
                            </div>
                            <div x-show="form.harga_min > 0 && form.harga_max > 0 && form.harga_satuan >= form.harga_min && form.harga_satuan <= form.harga_max" class="mt-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                ✓ Sesuai rentang Standar Satuan Harga (SSH)
                            </div>
                        </div>

                        <div class="mt-1 text-[10px] text-slate-400" x-show="form.harga_satuan_arkas > 0 && form.harga_satuan !== form.harga_satuan_arkas">
                            Harga acuan ARKAS: Rp <span x-text="form.harga_satuan_arkas.toLocaleString('id-ID')"></span> &mdash; akan ditandai <span class="font-bold text-amber-600 dark:text-amber-400">SELISIH</span> pada kolom KONTROL.
                        </div>
                    </div>

                    {{-- Opsi Lanjutan: Koreksi Manual (Disembunyikan dalam menu lipat) --}}
                    <details class="group rounded-xl border border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40 transition-all" :open="form.koreksi !== 0">
                        <summary class="px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer flex items-center justify-between select-none hover:text-blue-600 dark:hover:text-blue-400">
                            <span class="flex items-center gap-2">
                                <span>⚙️</span>
                                <span>Penyesuaian Khusus / Koreksi Manual (Opsional)</span>
                                <span x-show="form.koreksi !== 0" x-cloak class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300" x-text="'Koreksi: Rp ' + form.koreksi.toLocaleString('id-ID')"></span>
                            </span>
                            <span class="text-[10px] text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <div class="px-4 pb-3.5 pt-2 border-t border-slate-200/60 dark:border-slate-700/40">
                            <label class="label text-[11px]">KOREKSI (selisih rupiah manual, boleh negatif)</label>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="text-sm text-slate-400 font-semibold">Rp</span>
                                <input type="text" inputmode="numeric" x-bind:value="form.koreksi.toLocaleString('id-ID')"
                                    @input="form.koreksi = Number(String($event.target.value).replace(/[^\d-]/g, '')) || 0"
                                    placeholder="0" class="input !w-auto max-w-xs text-xs">
                            </div>
                            <div class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                                Angka ini untuk penyesuaian khusus selisih pembulatan rupiah. Total terpakai = (&Sigma; Vol &times; Harga) + Koreksi = <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="'Rp ' + totalAll()"></span>
                            </div>
                        </div>
                    </details>

                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">Dianggarkan untuk Bulan</span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 ml-2">Kartu biru = Tahap I (Jan&ndash;Jun) &middot; kartu hijau = Tahap II (Jul&ndash;Des)</span>
                            </div>
                            <button type="button" @click="copyJanToAll()" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                📋 Salin Volume Jan ke Semua Bulan
                            </button>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                            <template x-for="b in months" :key="b.no">
                                <div :class="b.no <= 6 ? 'border-blue-300 dark:border-blue-500/40 bg-blue-50/30 dark:bg-blue-900/10' : 'border-emerald-300 dark:border-emerald-500/40 bg-emerald-50/30 dark:bg-emerald-900/10'" class="rounded-xl border p-2 transition-all">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300" x-text="b.nama"></span>
                                        <span :class="b.no <= 6 ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'" class="px-1.5 py-0.5 rounded text-[9px] font-bold" x-text="b.no <= 6 ? 'Tahap I' : 'Tahap II'"></span>
                                    </div>
                                    <input type="number" x-model.number="alokasi[b.no].volume" min="0" step="any" placeholder="Vol" class="w-full rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    <input type="text" x-model="alokasi[b.no].satuan" placeholder="satuan" class="w-full rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-1.5 py-1 text-[10px] mt-1 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 text-right font-medium" x-text="subtotal(b.no)"></div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold text-blue-500 dark:text-blue-400 uppercase">Subtotal Tahap I</div>
                                <div class="text-sm font-bold text-blue-700 dark:text-blue-300" x-text="sumTahap(1)"></div>
                            </div>
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase">Subtotal Tahap II</div>
                                <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300" x-text="sumTahap(2)"></div>
                            </div>
                            <div class="rounded-xl bg-slate-800 dark:bg-slate-900 border border-slate-900 dark:border-slate-700 px-3 py-2.5">
                                <div class="text-[10px] font-semibold text-slate-400 uppercase">Total Anggaran</div>
                                <div class="text-sm font-bold text-white" x-text="totalAll()"></div>
                            </div>
                        </div>
                    </div>

                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-700/60 gap-3">
                        <div class="text-[11px]" x-show="error" x-cloak style="color:#dc2626" x-text="error"></div>
                        <div class="flex items-center gap-2 ml-auto">
                            <button @click="close()" class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">Tutup</button>
                            <template x-if="step===1">
                                <button @click="nextStep()" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20">Lanjut ke Detail →</button>
                            </template>
                            <template x-if="step===2">
                                <div class="flex items-center gap-2">
                                    <button @click="prevStep()" class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">← Kembali</button>
                                    <button @click="submit()" :disabled="saving" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold inline-flex items-center gap-2 shadow-md shadow-blue-600/20">
                                        <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                        <span x-text="editingId ? 'Simpan Perubahan' : 'Simpan ke Anggaran'"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = @json(url('/'));
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

const NAMA_BULAN = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const BULAN_KE = [1,2,3,4,5,6,7,8,9,10,11,12];

function makeAlokasi() {
    const a = {};
    BULAN_KE.forEach(m => a[m] = { volume: 0, satuan: '' });
    return a;
}

function makePickerState() {
    return { q: '', results: [], open: false, loading: false, value: '', label: '' };
}

function modalForm() {
    return {
        open: false,
        editingId: null,
        saving: false,
        error: '',
        mode: 'new',
        step: 1,
        sisipContext: '',
        months: BULAN_KE.map(m => ({ no: m, nama: NAMA_BULAN[m] })),
        form: { uraian: '', keterangan_kustom: '', harga_satuan: 0, harga_satuan_arkas: 0, harga_min: 0, harga_max: 0, koreksi: 0, kode_barang_id: null },
        alokasi: makeAlokasi(),
        pickers: { kegiatan: makePickerState(), rekening: makePickerState(), barang: makePickerState() },
        _timers: {},

        reset() {
            this.editingId = null;
            this.error = '';
            this.mode = 'new';
            this.step = 1;
            this.sisipContext = '';
            this.form = { uraian: '', keterangan_kustom: '', harga_satuan: 0, harga_satuan_arkas: 0, harga_min: 0, harga_max: 0, koreksi: 0, kode_barang_id: null };
            this.alokasi = makeAlokasi();
            this.pickers = { kegiatan: makePickerState(), rekening: makePickerState(), barang: makePickerState() };
        },
        nextStep(){
            this.error='';
            if(!this.pickers.kegiatan.value){ this.error='Silakan pilih Kegiatan terlebih dahulu.'; return; }
            if(!this.pickers.rekening.value){ this.error='Silakan pilih Kode Rekening terlebih dahulu.'; return; }
            this.step=2;
        },
        prevStep(){ this.error=''; this.step=1; },
        clearSisip() {
            this.mode = 'new';
            this.sisipContext = '';
            this.pickers.kegiatan = makePickerState();
            this.pickers.rekening = makePickerState();
        },

        close() {
            if (window.__rkasModalHost) window.__rkasModalHost.close();
        },

        copyJanToAll() {
            const janVol = Number(this.alokasi[1]?.volume) || 0;
            const janSat = this.alokasi[1]?.satuan || '';
            BULAN_KE.forEach(m => {
                this.alokasi[m].volume = janVol;
                if (janSat && !this.alokasi[m].satuan) this.alokasi[m].satuan = janSat;
            });
        },

        async debouncedSearch(key) {
            clearTimeout(this._timers[key]);
            this._timers[key] = setTimeout(() => this.search(key), 250);
        },

        async search(key) {
            const url = key === 'kegiatan' ? '/api/search/kegiatan' : key === 'rekening' ? '/api/search/rekening' : '/api/search/barang';
            const p = this.pickers[key];
            p.loading = true;
            p.open = true;
            try {
                const res = await fetch(url + '?q=' + encodeURIComponent(p.q));
                const d = await res.json();
                p.results = d.results || [];
            } catch (e) {
                p.results = [];
            } finally {
                p.loading = false;
            }
        },

        selectKegiatan(r) {
            const p = this.pickers.kegiatan;
            p.q = r.text; p.value = r.id; p.label = r.text; p.open = false;
        },
        selectRekening(r) {
            const p = this.pickers.rekening;
            p.q = r.text; p.value = r.id; p.label = r.text; p.open = false;
        },
        searchUraianSuggestion(ev) {
            const v = (ev && ev.target && ev.target.value) || this.form.uraian || '';
            const trimmed = v.trim();
            this.pickers.barang.q = trimmed;
            if (trimmed.length < 2) { this.pickers.barang.open = false; this.pickers.barang.results = []; return; }
            this.debouncedSearch('barang');
        },
        applyBarangSuggestion(r) {
            const p = this.pickers.barang;
            if (r.nama) { this.form.uraian = r.nama; p.q = r.nama; }
            p.value = r.id; p.open = false;
            this.form.kode_barang_id = r.id;
            this.form.harga_min = Number(r.harga_min) || 0;
            this.form.harga_max = Number(r.harga_max) || 0;
            this.form.harga_satuan_arkas = Number(r.harga) || 0;
            if (r.satuan) BULAN_KE.forEach(m => { if (!this.alokasi[m].satuan) this.alokasi[m].satuan = r.satuan; });
            if (Number(r.harga) > 0 && this.form.harga_satuan === 0) this.form.harga_satuan = Number(r.harga);
            this.$nextTick(() => { p.results = []; });
        },

        subtotal(b) {
            return 'Rp ' + ((Number(this.alokasi[b].volume) || 0) * (this.form.harga_satuan || 0)).toLocaleString('id-ID');
        },
        sumTahap(phase) {
            const lo = phase === 1 ? 1 : 7, hi = phase === 1 ? 6 : 12;
            let total = 0;
            for (let b = lo; b <= hi; b++) total += (Number(this.alokasi[b].volume) || 0) * (this.form.harga_satuan || 0);
            return 'Rp ' + total.toLocaleString('id-ID');
        },
        totalAll() {
            let total = 0;
            BULAN_KE.forEach(m => total += (Number(this.alokasi[m].volume) || 0) * (this.form.harga_satuan || 0));
            return 'Rp ' + total.toLocaleString('id-ID');
        },

        async load(id) {
            this.reset();
            this.mode = 'edit';
            this.editingId = id;
            try {
                const res = await fetch('/rkas/' + id + '/json', { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                this.editingId = d.id;
                this.form = {
                    uraian: d.uraian || '',
                    keterangan_kustom: d.keterangan_kustom || '',
                    harga_satuan: Number(d.harga_satuan) || 0,
                    harga_satuan_arkas: Number(d.harga_satuan_arkas) || 0,
                    harga_min: Number(d.harga_min) || 0,
                    harga_max: Number(d.harga_max) || 0,
                    koreksi: Number(d.koreksi) || 0,
                    kode_barang_id: d.kode_barang_id || null
                };
                if (d.kegiatan_id) { this.pickers.kegiatan.value = d.kegiatan_id; this.pickers.kegiatan.label = d.kegiatan_text; this.pickers.kegiatan.q = d.kegiatan_text; }
                if (d.rekening_id) { this.pickers.rekening.value = d.rekening_id; this.pickers.rekening.label = d.rekening_text; this.pickers.rekening.q = d.rekening_text; }
                const a = makeAlokasi();
                for (const b in d.alokasi) {
                    a[b] = { volume: Number(d.alokasi[b].volume) || 0, satuan: d.alokasi[b].satuan || '' };
                }
                this.alokasi = a;
            } catch (e) {
                this.error = 'Gagal memuat data item: ' + e.message;
            }
        },

        async submit() {
            this.error = '';
            const keg = this.pickers.kegiatan, rek = this.pickers.rekening;
            if (!keg.value) { this.error = 'Silakan pilih Kegiatan terlebih dahulu.'; return; }
            if (!rek.value) { this.error = 'Silakan pilih Kode Rekening terlebih dahulu.'; return; }
            if (!this.form.uraian.trim()) { this.error = 'Uraian wajib diisi.'; return; }

            const payload = {
                _token: CSRF,
                master_program_id: keg.value,
                master_kode_rekening_id: rek.value,
                kode_barang_id: this.form.kode_barang_id || null,
                uraian: this.form.uraian,
                keterangan_kustom: this.form.keterangan_kustom || null,
                harga_satuan: this.form.harga_satuan || 0,
                harga_satuan_arkas: this.form.harga_satuan_arkas || 0, // dikirim untuk audit, server tetap ambil dari harga_acuan KodeBarang
                koreksi: this.form.koreksi || 0,
                alokasi: {}
            };
            BULAN_KE.forEach(m => {
                payload.alokasi[m] = { volume: Number(this.alokasi[m].volume) || 0, satuan: (this.alokasi[m].satuan || '').trim() };
            });

            this.saving = true;
            try {
                const url = this.editingId ? '/rkas/' + this.editingId + '/update' : '/rkas/store';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    let msg = data.message || 'Terjadi kesalahan.';
                    if (data.errors) msg = Object.values(data.errors).flat().join('; ');
                    this.error = msg;
                    return;
                }
                window.location.reload();
            } catch (e) {
                this.error = 'Gagal menyimpan: ' + e.message;
            } finally {
                this.saving = false;
            }
        }
    };
}

function getModal() {
    const el = document.querySelector('#rkas-modal [x-data="modalForm()"]');
    if (!el) return null;
    if (el._x_dataStack && el._x_dataStack[0]) return el._x_dataStack[0];
    // Alpine v3 fallback via Alpine.$data
    try { if (window.Alpine && Alpine.$data) return Alpine.$data(el); } catch (e) {}
    return null;
}

window.__rkasModalHost = {
    openNew() {
        const m = getModal();
        if (!m) return;
        m.reset();
        m.mode = 'new';
        document.getElementById('rkas-modal').classList.remove('hidden');
    },
    openSisip(kegiatanId, kegiatanText, rekeningId = null, rekeningText = null) {
        const m = getModal();
        if (!m) return;
        m.reset();
        m.mode = 'sisip';
        const parts = [];
        if (kegiatanId) {
            m.pickers.kegiatan.value = kegiatanId;
            m.pickers.kegiatan.label = kegiatanText;
            m.pickers.kegiatan.q = kegiatanText;
            parts.push(kegiatanText);
        }
        if (rekeningId) {
            m.pickers.rekening.value = rekeningId;
            m.pickers.rekening.label = rekeningText;
            m.pickers.rekening.q = rekeningText;
            parts.push(rekeningText);
        }
        m.sisipContext = parts.join(' → ');
        document.getElementById('rkas-modal').classList.remove('hidden');
    },
    openEdit(id) {
        const m = getModal();
        if (!m) return;
        document.getElementById('rkas-modal').classList.remove('hidden');
        m.load(id);
    },
    close() {
        document.getElementById('rkas-modal').classList.add('hidden');
    }
};

document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-action');
    const id = btn.getAttribute('data-id');

    if (action === 'edit') {
        window.__rkasModalHost.openEdit(id);
    } else if (action === 'delete') {
        if (!confirm('Hapus item anggaran ini?')) return;
        fetch('/rkas/' + id + '/delete', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        }).finally(() => window.location.reload());
    } else if (action === 'sisip-kegiatan') {
        const kegId = btn.dataset.kegiatanId || btn.getAttribute('data-kegiatan-id');
        const kegText = btn.dataset.kegiatanText || btn.getAttribute('data-kegiatan-text');
        window.__rkasModalHost.openSisip(kegId, kegText);
    } else if (action === 'sisip-item') {
        const kegId = btn.dataset.kegiatanId || btn.getAttribute('data-kegiatan-id');
        const kegText = btn.dataset.kegiatanText || btn.getAttribute('data-kegiatan-text');
        const rekId = btn.dataset.rekeningId || btn.getAttribute('data-rekening-id');
        const rekText = btn.dataset.rekeningText || btn.getAttribute('data-rekening-text');
        window.__rkasModalHost.openSisip(kegId, kegText, rekId, rekText);
    }
});

document.getElementById('btn-open-modal').addEventListener('click', function () {
    window.__rkasModalHost.openNew();
});
</script>
@endsection