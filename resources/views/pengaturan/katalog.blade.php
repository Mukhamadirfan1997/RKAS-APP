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
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Update Katalog</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Perbarui data master kode barang (81rb baris) via paket .zip offline — tanpa install ulang.</p>
        </div>
        <a href="{{ route('pengaturan.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            ← Kembali ke Pengaturan
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Info katalog terpasang -->
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Katalog Terpasang</h2>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Versi, tanggal, dan jumlah barang aktif.</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 p-4">
                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase">Versi</div>
                        <div class="text-lg font-extrabold text-slate-800 dark:text-white mt-1">{{ $meta->versi ?? '-' }}</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Checksum: <span class="font-mono text-[10px] break-all">{{ $meta->checksum ? substr($meta->checksum,0,16).'…' : '-' }}</span></div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 p-4">
                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase">Jumlah Barang</div>
                        <div class="text-lg font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($jumlahSaatIni,0,',','.') }}</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Meta: {{ number_format($meta->jumlah_barang ?? 0,0,',','.') }} • {{ $meta->tanggal_update ? $meta->tanggal_update->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
                <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 p-3 text-xs text-slate-600 dark:text-slate-300">
                    <div><b>Sumber:</b> {{ $meta->source_file ?? '-' }}</div>
                    <div class="text-[11px] text-slate-400 dark:text-slate-500">Update terakhir: {{ $meta->tanggal_update ? $meta->tanggal_update->format('d/m/Y H:i:s') : '-' }} • ID #{{ $meta->id }}</div>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700/60 pt-3">
                    <p class="font-semibold text-slate-700 dark:text-slate-200">Catatan:</p>
                    <ul class="list-disc pl-4 mt-1 space-y-1">
                        <li>Paket update hanya <b>update/insert</b> berdasar <code>kode / id_barang_arkas</code>, <b>tidak hapus</b> barang custom sekolah.</li>
                        <li>Backup otomatis dibuat sebelum proses (cek menu Backup).</li>
                        <li>Proses 81rb baris ± 3-6 detik (chunk 1000, transaksi).</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Form upload -->
        <div class="card overflow-hidden" x-data="{ uploading: false }">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Upload Paket Update (.zip)</h2>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">File berisi <code>katalog.csv</code> + <code>manifest.json</code> (dari <code>php artisan katalog:build-update</code>).</p>
            </div>
            <form method="POST" action="{{ route('pengaturan.katalog.update') }}" enctype="multipart/form-data" class="p-6 space-y-5" @submit="uploading = true">
                @csrf
                <div>
                    <label class="label">Pilih file .zip <span class="text-red-500">*</span></label>
                    <input type="file" name="file" accept=".zip" required class="block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-700 dark:file:text-slate-200">
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">Maks 50 MB. Dapatkan file dari developer via USB/WA.</p>
                </div>

                <div x-show="uploading" x-cloak class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 animate-spin text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <div>
                        <div class="text-sm font-bold text-amber-800 dark:text-amber-300">Memproses 81 ribu baris...</div>
                        <div class="text-xs text-amber-700 dark:text-amber-400">Jangan tutup halaman. Backup otomatis + upsert chunk 1000 sedang berjalan (transaksi).</div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="uploading" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                        <svg x-show="!uploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6h.1a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <svg x-show="uploading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span x-text="uploading ? 'Memproses...' : 'Upload & Proses'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Cara developer membuat paket (untuk referensi operator)</h3>
        <pre class="mt-3 p-4 rounded-xl bg-slate-900 text-slate-100 text-xs overflow-x-auto"><code>php artisan katalog:build-update --source="RKAS - KERTAS KERJA 2026 master V.2.TOYANING revisi harga edit.xlsm" --version=2026.09 --output=katalog-update-2026.09.zip
# → hasil: katalog-update-2026.09.zip ( berisi katalog.csv + manifest.json )
# → sebarkan via USB/WA ke sekolah → sekolah upload di halaman ini</code></pre>
    </div>
</div>
@endsection
