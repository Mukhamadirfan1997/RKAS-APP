@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="barangPage()">
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
            <h1 class="text-lg md:text-xl font-bold text-slate-800 dark:text-slate-100">Master Data — Katalog Kode Barang</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">81.062 katalog ARKAS · {{ $items->total() }} tampil · SSH min/max + harga acuan</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('master.program') }}" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">Program</a>
            <a href="{{ route('master.rekening') }}" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">Rekening</a>
            <button @click="openAdd()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-md shadow-blue-600/20">+ Tambah Barang</button>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <div class="card px-4 py-3 flex items-center gap-6 text-xs">
            <span><span class="text-slate-500">Ditampilkan</span> <b>{{ $items->total() }}</b></span>
            <span><span class="text-slate-500">Kategori</span> <b>{{ $kategoriList->count() }}</b></span>
            <span class="text-[11px] text-slate-400">SSH min/max dari impor ARKAS</span>
        </div>
        <details class="card overflow-hidden">
            <summary class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer select-none">Import Katalog</summary>
            <form method="POST" action="{{ route('master.import') }}" enctype="multipart/form-data" class="px-5 pb-4 pt-2 flex items-center gap-3">
                @csrf
                <input type="hidden" name="target" value="barang">
                <span class="text-[11px] text-slate-400 hidden sm:inline">Header: <b>kode</b>, <b>nama</b>, <b>satuan</b>, <b>harga</b></span>
                <input type="file" name="file" required accept=".xls,.xlsx,.csv" class="text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white file:text-xs">
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold whitespace-nowrap">Import</button>
            </form>
        </details>
    </div>

    <div class="card overflow-hidden">
            <form method="GET" class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row gap-2 sm:items-center">
                <div class="flex-1 flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode / nama / kode rekening…" class="input flex-1">
                    <select name="kategori" class="input !w-auto max-w-[180px]">
                        <option value="">Semua Kategori</option>
                        @foreach($kategoriList as $kat)
                            <option value="{{ $kat }}" {{ $kategori === $kat ? 'selected' : '' }}>{{ $kat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 py-2 rounded-xl bg-slate-800 dark:bg-slate-700 text-white text-xs font-semibold">Cari</button>
                    @if($q !== '' || $kategori !== '')<a href="{{ route('master.barang') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-red-500">Reset</a>@endif
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                            <th class="px-4 py-3 text-center w-12">No</th>
                            <th class="px-4 py-3 text-left min-w-[100px]">Kode</th>
                            <th class="px-4 py-3 text-left min-w-[300px]">Nama Barang / Jasa</th>
                            <th class="px-4 py-3 text-left min-w-[90px]">Satuan</th>
                            <th class="px-4 py-3 text-right min-w-[140px]">Harga Acuan</th>
                            <th class="px-4 py-3 text-right min-w-[160px]">SSH</th>
                            <th class="px-4 py-3 text-center w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @forelse($items as $idx => $item)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-center text-slate-400">{{ $items->firstItem() + $idx }}</td>
                                <td class="px-4 py-3 font-mono text-blue-600 dark:text-blue-400 font-bold text-xs">{{ $item->kode ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-700 dark:text-slate-200">{{ $item->nama }}</div>
                                    @if($item->kode_rekening || $item->kategori)<div class="text-[11px] text-slate-400">{{ $item->kode_rekening ?? '' }} @if($item->kategori) · {{ $item->kategori }} @endif</div>@endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $item->satuan_default ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-200">Rp {{ number_format((float)$item->harga_acuan, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-[11px]">
                                    @if((float)$item->harga_min > 0 || (float)$item->harga_max > 0)
                                        <div class="text-slate-600 dark:text-slate-300">Rp {{ number_format((float)$item->harga_min, 0, ',', '.') }} – Rp {{ number_format((float)$item->harga_max, 0, ',', '.') }}</div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openEdit({{ $item->id }}, @js($item->kode), @js($item->nama), @js($item->satuan_default), '{{ (float)$item->harga_acuan }}')" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10" title="Ubah"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                        <form method="POST" action="{{ route('master.barang.destroy', $item->id) }}" onsubmit="return confirm('Hapus barang ini?')">
                                            @csrf @method('DELETE')
                                            <button class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" title="Hapus"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-xs text-slate-400">Belum ada barang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-700/60">
                {{ $items->links() }}
            </div>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.6);backdrop-filter:blur(3px)">
        <div @click.outside="showModal=false" class="bg-white dark:bg-slate-800 w-full max-w-lg rounded-2xl shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700/60 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-slate-800 dark:text-white" x-text="form.id ? 'Ubah Barang' : 'Tambah Barang'"></h3>
                <button @click="showModal=false" class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form :action="form.id ? '{{ url('/master/barang') }}/' + form.id : '{{ route('master.barang.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <div><label class="label">Kode Barang</label><input type="text" name="kode" x-model="form.kode" placeholder="KB-001 atau id_barang_arkas" class="input w-full font-mono"></div>
                <div><label class="label">Nama Barang/Jasa <span class="text-red-500">*</span></label><input type="text" name="nama" x-model="form.nama" required placeholder="Nasi Dus & Lauk Pauk" class="input w-full"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Satuan Default</label><input type="text" name="satuan_default" x-model="form.satuan" placeholder="dus" class="input w-full"></div>
                    <div><label class="label">Harga Acuan</label><input type="number" name="harga_acuan" x-model="form.harga" min="0" step="any" placeholder="30000" class="input w-full"></div>
                </div>
                <div class="flex justify-end gap-2 pt-2"><button type="button" @click="showModal=false" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold">Batal</button><button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold">Simpan</button></div>
            </form>
        </div>
    </div>
</div>
<script>
function barangPage(){
    return {
        showModal:false,
        form:{id:null,kode:'',nama:'',satuan:'',harga:''},
        openAdd(){ this.form={id:null,kode:'',nama:'',satuan:'',harga:''}; this.showModal=true; },
        openEdit(id,kode,nama,satuan,harga){ this.form={id,kode:kode||'',nama,satuan:satuan||'',harga:harga||''}; this.showModal=true; }
    }
}
</script>
@endsection
