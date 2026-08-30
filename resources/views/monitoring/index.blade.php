@extends('layouts.app')

@section('content')
@php
    $statusBadge = [
        'sesuai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'melebihi' => 'bg-red-50 text-red-700 border-red-200',
        'kurang' => 'bg-amber-50 text-amber-700 border-amber-200',
    ];
    $statusLabel = ['sesuai' => 'SESUAI', 'melebihi' => 'MELEBIHI', 'kurang' => 'KURANG'];
@endphp

<div class="p-5 md:p-6 max-w-[1440px] mx-auto">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800">Review &amp; Monitoring Kepatuhan JUKNIS</h1>
            <p class="text-xs text-slate-500 mt-0.5">Permendikdasmen No. 8/2026 &mdash; referensi sebelum pengisian ARKAS resmi.</p>
        </div>
        <a href="{{ route('dashboard.index') }}" class="text-xs text-blue-600 hover:underline">Kembali ke Dashboard</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Honor Belanja Pegawai</div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['honor']['total'], 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $summary['honor']['persen'] }}% &mdash; batas maksimal {{ $summary['honor']['batas_persen'] }}% pagu</div>
            <div class="mt-2">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['honor']['status']] }}">{{ $statusLabel[$summary['honor']['status']] }}</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Anggaran Buku</div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['buku']['total'], 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $summary['buku']['persen'] }}% &mdash; batas minimal {{ $summary['buku']['batas_persen'] }}% pagu</div>
            <div class="mt-2">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['buku']['status']] }}">{{ $statusLabel[$summary['buku']['status']] }}</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Sarana &amp; Prasarana</div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['sarpras']['total'], 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $summary['sarpras']['persen'] }}% &mdash; batas maksimal {{ $summary['sarpras']['batas_persen'] }}% pagu</div>
            <div class="mt-2">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['sarpras']['status']] }}">{{ $statusLabel[$summary['sarpras']['status']] }}</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Alokasi Tahap I</div>
            <div class="text-lg font-bold text-slate-800 mt-1">Rp {{ number_format($summary['tahap1']['total'], 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $summary['tahap1']['persen'] }}% &mdash; minimal {{ $summary['tahap1']['batas_persen'] }}% pagu</div>
            <div class="mt-2">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge[$summary['tahap1']['status']] }}">{{ $statusLabel[$summary['tahap1']['status']] }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Daftar Kategori & Pemetaan -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-700">Kategori JUKNIS &amp; Pemetaan Kode Rekening</h3>
                <p class="text-[11px] text-slate-400">Konfigurasi arah &amp; batas persen, lalu petakan kode rekening belanja.</p>
            </div>
            <div class="p-5 space-y-6">
                @forelse($kategoriList as $kategori)
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-bold text-slate-700">{{ $kategori->nama }}</div>
                                <div class="text-[11px] text-slate-400">Arah: <b>{{ ucfirst($kategori->arah) }}</b> &middot; Batas: <b>{{ $kategori->batas_persen }}%</b> &middot; {{ $kategori->rekenings->count() }} kode rekening terpetakan</div>
                            </div>
                            <span class="text-[10px] font-mono text-slate-400">ID: {{ $kategori->id }}</span>
                        </div>
                        <form method="POST" action="{{ route('monitoring.juknis.mapping') }}" class="p-4">
                            @csrf
                            <input type="hidden" name="kategori_juknis_id" value="{{ $kategori->id }}">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex-1">
                                    <select name="kode_rekening[]" multiple size="4" class="w-full rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        @foreach(\App\Models\MasterKodeRekening::orderBy('kode')->get() as $rekening)
                                            <option value="{{ $rekening->id }}" {{ in_array($rekening->id, $kategori->rekenings->pluck('id')->all()) ? 'selected' : '' }}>[{{ $rekening->kode }}] {{ $rekening->nama }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-[10px] text-slate-400 mt-1">Tahan Ctrl/Cmd untuk pilih beberapa. Kosongkan untuk menghapus pemetaan.</div>
                                </div>
                                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold whitespace-nowrap">Simpan Pemetaan</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="text-xs text-slate-400 text-center py-8">Belum ada kategori JUKNIS. Jalankan seeder JuknisSeeder terlebih dahulu.</div>
                @endforelse
            </div>
        </div>

        <!-- Item belum termapping -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-700">Checklist Revisi</h3>
                <p class="text-[11px] text-slate-400">Item belanja yang belum terpetakan ke kategori JUKNIS.</p>
            </div>
            <div class="max-h-[420px] overflow-y-auto divide-y divide-slate-100">
                @forelse($unmapped as $item)
                    <div class="px-4 py-3">
                        <div class="text-xs font-semibold text-slate-700">{{ $item->uraian }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">{{ $item->program->nama ?? '-' }} &middot; {{ $item->kodeRekening->kode ?? 'tanpa rekening' }}</div>
                        <div class="text-[11px] font-bold text-slate-500 mt-1">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-emerald-600">
                        Semua item terpetakan dengan baik.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection