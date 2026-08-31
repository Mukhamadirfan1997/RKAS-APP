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

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800 dark:text-slate-100">Master Data &mdash; Kode Rekening Belanja</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelola kode rekening belanja sesuai klasifikasi Jenis Belanja ARKAS (9 jenis).</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('master.program') }}" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">Program</a>
            <a href="{{ route('master.barang') }}" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">Kode Barang</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            <!-- Tambah -->
            <div class="card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-700/60">
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Tambah Rekening</h3>
                </div>
                <form method="POST" action="{{ route('master.rekening.store') }}" class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label class="label block mb-1">Kode <span class="text-red-500">*</span></label>
                        <input type="text" name="kode" required placeholder="5.1.02.01.01.0052" class="w-full input">
                    </div>
                    <div>
                        <label class="label block mb-1">Nama <span class="text-red-500">*</span></label>
                        <input type="text" name="nama" required placeholder="Belanja Makanan dan Minuman Rapat" class="w-full input">
                    </div>
                    <div>
                        <label class="label block mb-1">Jenis Belanja</label>
                        <select name="jenis_belanja_id" class="w-full input">
                            <option value="">-- Pilih Jenis Belanja --</option>
                            @foreach($jenisBelanjas as $jb)
                                <option value="{{ $jb->id }}">{{ $jb->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-xs font-semibold shadow-md shadow-blue-600/20 transition-colors">Simpan</button>
                </form>
            </div>

            <!-- Import -->
            <div class="card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-700/60">
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Import dari Excel</h3>
                </div>
                <form method="POST" action="{{ route('master.import') }}" enctype="multipart/form-data" class="p-5 space-y-4">
                    @csrf
                    <input type="hidden" name="target" value="rekening">
                    <div>
                        <label class="label block mb-1">File .xls/.xlsx/.csv</label>
                        <input type="file" name="file" required accept=".xls,.xlsx,.csv" class="w-full text-xs">
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Kolom header: <b>kode</b> (kode_barang), <b>nama</b> (rincian_objek). Jenis belanja terisi otomatis dari prefix kode.</div>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 dark:hover:bg-emerald-500 text-white text-xs font-semibold shadow-md shadow-emerald-600/20 transition-colors">Import Data</button>
                </form>
            </div>
        </div>

        <!-- Daftar -->
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $items->count() }} Rekening Terdaftar</h3>
                <form method="GET" class="flex items-center gap-2">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode / nama..." class="input">
                    <button class="px-3 py-1.5 rounded-lg bg-slate-800 dark:bg-slate-700 text-white text-xs font-semibold hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors">Cari</button>
                </form>
            </div>
            <div class="max-h-[540px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse($items as $item)
                    <div class="px-5 py-3.5 flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-200">
                                <span class="text-blue-600 dark:text-blue-400 font-mono">{{ $item->kode }}</span> &middot; {{ $item->nama }}
                            </div>
                            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold border bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/30">
                                    {{ $item->jenisBelanja->nama ?? 'Belum diklasifikasi' }}
                                </span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('master.rekening.update', $item->id) }}" class="flex items-center gap-1.5 shrink-0" x-data="{ editing: false }">
                            @csrf
                            <template x-if="!editing">
                                <button type="button" @click="editing = true" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                            </template>
                            <template x-if="editing">
                                <div class="flex items-center gap-1.5">
                                    <input type="text" name="kode" value="{{ $item->kode }}" class="w-28 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <input type="text" name="nama" value="{{ $item->nama }}" class="w-40 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <select name="jenis_belanja_id" class="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <option value="">-- Jenis --</option>
                                        @foreach($jenisBelanjas as $jb)
                                            <option value="{{ $jb->id }}" {{ $item->jenis_belanja_id === $jb->id ? 'selected' : '' }}>{{ $jb->nama }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors">Simpan</button>
                                </div>
                            </template>
                        </form>
                        <form method="POST" action="{{ route('master.rekening.destroy', $item->id) }}" class="shrink-0" onsubmit="return confirm('Hapus rekening ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-slate-400 dark:text-slate-500">Belum ada rekening. Tambahkan atau import dari Excel.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection