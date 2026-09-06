@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Panduan Penggunaan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Langkah demi langkah memakai KARSA — bahasa sederhana untuk Bendahara & Operator.</p>
        </div>
        <button type="button" onclick="window.print()" class="hidden lg:inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Cetak Panduan Ini
        </button>
    </div>
    <div class="card p-8 text-center text-sm text-slate-500 dark:text-slate-400">
        Panduan lengkap sedang disiapkan. Silakan buka kembali setelah update berikutnya.
        <div class="mt-4">
            <a href="{{ route('tentang.index') }}" class="text-blue-600 dark:text-blue-400 underline text-xs">← Kembali ke Tentang Aplikasi</a>
        </div>
    </div>
</div>
@endsection
