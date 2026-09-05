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
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Kelola Tahun Anggaran — Arsip & Ganti Aktif</h2>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Buat TA baru, lihat arsip, atau ganti tahun aktif (1 saja, ala ARKAS tahun_aktif).</p>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $daftarTahun->count() }} tahun</span>
        </div>
        <div class="p-6 space-y-4">
            <div class="px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 text-xs text-blue-700 dark:text-blue-300">💡 Buat/aktifkan tahun anggaran di sini dulu, baru isi nominal pagu di tab <a href="{{ route('pengaturan.pagu', ['tahun'=>$tahunAnggaran->tahun]) }}" class="underline font-semibold">Pagu</a>.</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                            <th class="px-3 py-2 text-left">Tahun</th>
                            <th class="px-3 py-2 text-right">Pagu Total</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-center">Aktif</th>
                            <th class="px-3 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($daftarTahun as $taRow)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 @if($taRow->tahun == $tahunAnggaran->tahun) bg-blue-50/40 dark:bg-blue-500/10 @endif">
                            <td class="px-3 py-2 font-bold text-slate-800 dark:text-white">{{ $taRow->tahun }} @if($taRow->is_active)<span class="ml-2 px-1.5 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Aktif</span>@endif</td>
                            <td class="px-3 py-2 text-right">Rp {{ number_format($taRow->pagu_total,0,',','.') }}</td>
                            <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold border @if($taRow->status_pengesahan==='Disahkan') bg-emerald-50 text-emerald-700 border-emerald-200 @elseif($taRow->status_pengesahan==='Pergeseran') bg-amber-50 text-amber-700 border-amber-200 @else bg-slate-100 text-slate-600 border-slate-200 @endif">{{ $taRow->status_pengesahan }}</span></td>
                            <td class="px-3 py-2 text-center">@if($taRow->is_active) ✓ @else — @endif</td>
                            <td class="px-3 py-2 text-center flex items-center justify-center gap-1">
                                <a href="{{ route('pengaturan.tahun.index', ['tahun'=>$taRow->tahun]) }}" class="px-2 py-1 rounded-lg text-xs font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10">Lihat</a>
                                <a href="{{ route('rkas.index', ['tahun'=>$taRow->tahun]) }}" class="px-2 py-1 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700/50">Lembar Kerja</a>
                                @if(!$taRow->is_active)
                                <form method="POST" action="{{ route('pengaturan.tahun.aktifkan', $taRow->id) }}" onsubmit="return confirm('Aktifkan TA {{ $taRow->tahun }} sebagai tahun aktif?')">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 rounded-lg text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10">Aktifkan</button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('pengaturan.tahun.destroy', $taRow->id) }}" onsubmit="return confirm('Hapus TA {{ $taRow->tahun }}? Pastikan tidak aktif dan tidak ada item RKAS.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('pengaturan.tahun.store') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 pt-4 border-t border-slate-200 dark:border-slate-700/60">
                @csrf
                <div>
                    <label class="label">Tahun Baru *</label>
                    <input type="number" name="tahun" value="{{ old('tahun', date('Y')+1) }}" min="2020" max="2100" required class="input" placeholder="2027">
                </div>
                <div>
                    <label class="label">Pagu Total</label>
                    <input type="number" name="pagu_total" value="{{ old('pagu_total') }}" placeholder="180320000" class="input">
                </div>
                <div>
                    <label class="label">Copy dari</label>
                    <select name="copy_from" class="input">
                        <option value="">— Kosong —</option>
                        @foreach($daftarTahun as $opt)<option value="{{ $opt->tahun }}">{{ $opt->tahun }} ({{ $opt->status_pengesahan }})</option>@endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-semibold">Buat TA</button>
                </div>
                <div class="flex items-end">
                    <a href="{{ route('rkas.index') }}" class="w-full text-center px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300">Buka Lembar Kerja Aktif ({{ $daftarTahun->firstWhere('is_active',true)->tahun ?? $tahunAnggaran->tahun }})</a>
                </div>
            </form>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Tip: <b>Lihat</b> untuk buka arsip read-only via <code>?tahun=2026</code>, <b>Aktifkan</b> untuk jadikan tahun aktif (1 saja, ala ARKAS <code>tahun_aktif</code>), <b>Copy dari</b> untuk duplikat item lama ke tahun baru.</p>
        </div>
    </div>
</div>
@endsection
