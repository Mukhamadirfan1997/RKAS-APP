@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="programPage()">
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
            <h1 class="text-lg md:text-xl font-bold text-slate-800 dark:text-slate-100">Master Data — Program / Kegiatan (8 SNP)</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelola kode kegiatan ARKAS — {{ $items->total() }} kegiatan terdaftar · {{ $snpList->count() }} SNP</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('master.rekening') }}" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">Rekening</a>
            <a href="{{ route('master.barang') }}" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">Kode Barang</a>
            <button @click="openAdd()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-md shadow-blue-600/20">+ Tambah Program</button>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <div class="card px-4 py-3 flex items-center gap-6 text-xs">
            <span><span class="text-slate-500">Total</span> <b class="text-slate-800 dark:text-white">{{ $items->total() }}</b></span>
            <span><span class="text-slate-500">SNP</span> <b class="text-indigo-600 dark:text-indigo-400">{{ $snpList->count() }}</b></span>
        </div>
        <details class="card overflow-hidden">
            <summary class="px-5 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer select-none">Import dari Excel</summary>
            <form method="POST" action="{{ route('master.import') }}" enctype="multipart/form-data" class="px-5 pb-4 pt-2 flex items-center gap-3">
                @csrf
                <input type="hidden" name="target" value="program">
                <span class="text-[11px] text-slate-400 hidden sm:inline">Header: <b>kode</b>, <b>nama</b>, <b>program</b>, <b>sub_program</b></span>
                <input type="file" name="file" required accept=".xls,.xlsx,.csv" class="text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white file:text-xs">
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold whitespace-nowrap">Import</button>
            </form>
        </details>
    </div>

    <div class="card overflow-hidden">
            <form method="GET" class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row gap-2 sm:items-center">
                <div class="flex-1 flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode / nama / SNP…" class="input flex-1">
                    <select name="snp" class="input !w-auto max-w-[220px]">
                        <option value="">Semua SNP</option>
                        @foreach($snpList as $snpOpt)
                            <option value="{{ $snpOpt }}" {{ $snp === $snpOpt ? 'selected' : '' }}>{{ $snpOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 py-2 rounded-xl bg-slate-800 dark:bg-slate-700 text-white text-xs font-semibold hover:bg-slate-700">Cari</button>
                    @if($q !== '' || $snp !== '')<a href="{{ route('master.program') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-red-500">Reset</a>@endif
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                            <th class="px-4 py-3 text-center w-12">No</th>
                            <th class="px-4 py-3 text-left min-w-[110px]">Kode</th>
                            <th class="px-4 py-3 text-left min-w-[280px]">Nama Kegiatan</th>
                            <th class="px-4 py-3 text-left min-w-[180px]">Program SNP</th>
                            <th class="px-4 py-3 text-center w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @forelse($items as $idx => $item)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-center text-slate-400">{{ $items->firstItem() + $idx }}</td>
                                <td class="px-4 py-3 font-mono text-blue-600 dark:text-blue-400 font-bold">{{ $item->kode }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-700 dark:text-slate-200">{{ $item->nama }}</div>
                                    @if($item->sub_program)<div class="text-[11px] text-slate-400">{{ $item->sub_program }}</div>@endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($item->program)
                                        <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/30">{{ $item->program }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button @click="openEdit({{ $item->id }}, @js($item->kode), @js($item->nama), @js($item->program), @js($item->sub_program))" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10" title="Ubah">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('master.program.destroy', $item->id) }}" onsubmit="return confirm('Hapus program ini?')">
                                            @csrf @method('DELETE')
                                            <button class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" title="Hapus"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-xs text-slate-400">Belum ada program. Tambahkan atau import dari Excel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-700/60">
                {{ $items->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.6);backdrop-filter:blur(3px)">
        <div @click.outside="showModal=false" class="bg-white dark:bg-slate-800 w-full max-w-lg rounded-2xl shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700/60 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-slate-800 dark:text-white" x-text="form.id ? 'Ubah Program' : 'Tambah Program'"></h3>
                <button @click="showModal=false" class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form :action="form.id ? '{{ url('/master/program') }}/' + form.id : '{{ route('master.program.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <div><label class="label">Kode <span class="text-red-500">*</span></label><input type="text" name="kode" x-model="form.kode" required placeholder="03.02.01" class="input w-full"></div>
                <div><label class="label">Nama <span class="text-red-500">*</span></label><input type="text" name="nama" x-model="form.nama" required placeholder="Peningkatan Kompetensi Guru" class="input w-full"></div>
                <div><label class="label">Program (8 SNP)</label><input type="text" name="program" x-model="form.program" placeholder="Standar Pendidik dan Tenaga Kependidikan" class="input w-full"></div>
                <div><label class="label">Sub Program</label><input type="text" name="sub_program" x-model="form.sub_program" placeholder="Pengembangan Profesi" class="input w-full"></div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showModal=false" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function programPage(){
    return {
        showModal:false,
        form:{id:null,kode:'',nama:'',program:'',sub_program:''},
        openAdd(){ this.form={id:null,kode:'',nama:'',program:'',sub_program:''}; this.showModal=true; },
        openEdit(id,kode,nama,program,sub){ this.form={id,kode,nama,program:program||'',sub_program:sub||''}; this.showModal=true; }
    }
}
</script>
@endsection
