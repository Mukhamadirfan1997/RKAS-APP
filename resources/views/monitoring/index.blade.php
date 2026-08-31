@extends('layouts.app')

@section('content')
@php
    $statusBadge = [
        'sesuai' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30',
        'melebihi' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
        'kurang' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
    ];
    $statusLabel = ['sesuai' => 'SESUAI', 'melebihi' => 'MELEBIHI', 'kurang' => 'KURANG'];
@endphp

<div class="space-y-6">
    @if(session('success'))
        <div class="mb-0 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Review &amp; Monitoring Kepatuhan JUKNIS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Permendikdasmen No. 8/2026 &mdash; referensi sebelum pengisian ARKAS resmi.</p>
            <div class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 rounded-full text-[11px] font-bold border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                Status Sekolah: {{ ucfirst($summary['status_sekolah'] ?? 'negeri') }} &middot; batas honor {{ $summary['honor']['batas_persen'] ?? 20 }}%
            </div>
        </div>
        <a href="{{ route('dashboard.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">Kembali ke Dashboard</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Honor Belanja Pegawai</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['honor']['status']] }}">{{ $statusLabel[$summary['honor']['status']] }}</span>
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($summary['honor']['total'], 0, ',', '.') }}</div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $summary['honor']['persen'] }}% &mdash; batas maksimal {{ $summary['honor']['batas_persen'] }}% pagu</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Anggaran Buku</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['buku']['status']] }}">{{ $statusLabel[$summary['buku']['status']] }}</span>
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($summary['buku']['total'], 0, ',', '.') }}</div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $summary['buku']['persen'] }}% &mdash; batas minimal {{ $summary['buku']['batas_persen'] }}% pagu</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sarana &amp; Prasarana</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['sarpras']['status']] }}">{{ $statusLabel[$summary['sarpras']['status']] }}</span>
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($summary['sarpras']['total'], 0, ',', '.') }}</div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $summary['sarpras']['persen'] }}% &mdash; batas maksimal {{ $summary['sarpras']['batas_persen'] }}% pagu</div>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Alokasi Tahap I</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['tahap1']['status']] }}">{{ $statusLabel[$summary['tahap1']['status']] }}</span>
            </div>
            <div class="text-xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($summary['tahap1']['total'], 0, ',', '.') }}</div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $summary['tahap1']['persen'] }}% &mdash; minimal {{ $summary['tahap1']['batas_persen'] }}% pagu</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Daftar Kategori & Pemetaan -->
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Kategori JUKNIS &amp; Pemetaan Kode Rekening</h3>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Konfigurasi arah &amp; batas persen, lalu petakan kode rekening belanja.</p>
            </div>
            <div class="p-5 space-y-6">
                @forelse($kategoriList as $kategori)
                    <div class="border border-slate-200 dark:border-slate-700/60 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-700/30 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $kategori->nama }}</div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500">Arah: <b>{{ ucfirst($kategori->arah) }}</b> &middot; Batas: <b>{{ $kategori->batas_persen }}%</b> &middot; {{ $kategori->rekenings->count() }} kode rekening terpetakan</div>
                            </div>
                            <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">ID: {{ $kategori->id }}</span>
                        </div>
                        <form method="POST" action="{{ route('monitoring.juknis.mapping') }}" class="p-4">
                            @csrf
                            <input type="hidden" name="kategori_juknis_id" value="{{ $kategori->id }}">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex-1">
                                    <select name="kode_rekening[]" multiple size="4" class="input">
                                        @foreach(\App\Models\MasterKodeRekening::orderBy('kode')->get() as $rekening)
                                            <option value="{{ $rekening->id }}" {{ in_array($rekening->id, $kategori->rekenings->pluck('id')->all()) ? 'selected' : '' }}>[{{ $rekening->kode }}] {{ $rekening->nama }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Tahan Ctrl/Cmd untuk pilih beberapa. Kosongkan untuk menghapus pemetaan.</div>
                                </div>
                                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-xs font-semibold whitespace-nowrap shadow-md shadow-blue-600/20 transition-colors">Simpan Pemetaan</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 dark:text-slate-500 text-center py-8">Belum ada kategori JUKNIS. Jalankan seeder JuknisSeeder terlebih dahulu.</div>
                @endforelse
            </div>
        </div>

        <!-- Item belum termapping -->
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Checklist Revisi</h3>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Item belanja yang belum terpetakan ke kategori JUKNIS.</p>
            </div>
            <div class="max-h-[420px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($unmapped as $item)
                    <div class="px-4 py-3">
                        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $item->uraian }}</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ $item->program->nama ?? '-' }} &middot; {{ $item->kodeRekening->kode ?? 'tanpa rekening' }}</div>
                        <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 mt-1">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-emerald-600 dark:text-emerald-400">
                        Semua item terpetakan dengan baik.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
