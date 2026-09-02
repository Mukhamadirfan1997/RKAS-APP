@extends('layouts.app')
@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-400">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Pengaturan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Profil sekolah, pagu anggaran, dan konfigurasi sistem.</p>
        </div>
        <a href="{{ route('rkas.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Lembar Kerja
        </a>
    </div>
    @include('pengaturan._nav')
    <div class="card overflow-hidden" x-data="{ showSahkan:false, showBuka:false }">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Status RKAS — TA {{ $tahunAnggaran->tahun }}</h2>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Pengesahan &amp; kunci data — cegah edit setelah disahkan.</p>
            </div>
            @php $status = $tahunAnggaran->status_pengesahan ?? 'Draft'; @endphp
            <span class="px-3 py-1 rounded-full text-xs font-bold border
                @if($status === 'Disahkan') bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30
                @elseif($status === 'Pergeseran') bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30
                @else bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600
                @endif">{{ $status }}</span>
        </div>
        <div class="p-6 space-y-4">
            @if($status === 'Draft')
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    RKAS masih <b>Draft</b> — data masih bisa ditambah/diedit/dihapus. Sahkan jika sudah final; sistem akan membuat backup otomatis <code class="text-xs bg-slate-200 dark:bg-slate-700 px-1 py-0.5 rounded">rkas-pengesahan-{{ $tahunAnggaran->tahun }}-*.zip</code>.
                </div>
                <button type="button" @click="showSahkan = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-md shadow-emerald-600/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sahkan RKAS TA {{ $tahunAnggaran->tahun }}
                </button>
            @elseif($status === 'Pergeseran')
                <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                    RKAS dalam <b>Pergeseran</b> (dibuka kembali dari Disahkan untuk revisi). Data masih bisa diedit; sahkan kembali jika revisi selesai — backup baru akan dibuat.
                </div>
                <button type="button" @click="showSahkan = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-md shadow-emerald-600/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sahkan Kembali RKAS TA {{ $tahunAnggaran->tahun }}
                </button>
            @else
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
                    RKAS sudah <b>Disahkan</b> — data terkunci. Backup resmi telah dibuat di menu Backup. Buka kembali jika perlu revisi darurat.
                </div>
                <button type="button" @click="showBuka = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow-md shadow-amber-600/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Buka Kembali untuk Revisi
                </button>
            @endif
        </div>
        <div x-show="showSahkan" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(15,23,42,.6); backdrop-filter: blur(3px);" @click.self="showSahkan = false">
            <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl p-6 space-y-4 ring-1 ring-slate-200 dark:ring-slate-700/60">
                <h3 class="text-base font-extrabold text-slate-800 dark:text-white">Yakin ingin mengesahkan RKAS TA {{ $tahunAnggaran->tahun }}?</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">Sistem akan membuat backup otomatis <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded">rkas-pengesahan-{{ $tahunAnggaran->tahun }}-*.zip</span>, dan data tidak bisa diedit lagi setelah ini.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showSahkan = false" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300">Batal</button>
                    <form method="POST" action="{{ route('pengaturan.pengesahan.sahkan') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahunAnggaran->tahun }}">
                        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Ya, Sahkan</button>
                    </form>
                </div>
            </div>
        </div>
        <div x-show="showBuka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(15,23,42,.6); backdrop-filter: blur(3px);" @click.self="showBuka = false">
            <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl p-6 space-y-4 ring-1 ring-slate-200 dark:ring-slate-700/60">
                <h3 class="text-base font-extrabold text-slate-800 dark:text-white">Yakin ingin membuka kembali untuk revisi?</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">Status akan berubah dari <b>Disahkan</b> menjadi <b>Pergeseran</b>. Ini akan dicatat di audit log dan data kembali bisa diedit.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showBuka = false" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300">Batal</button>
                    <form method="POST" action="{{ route('pengaturan.pengesahan.buka-kembali') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahunAnggaran->tahun }}">
                        <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">Ya, Buka Kembali</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
