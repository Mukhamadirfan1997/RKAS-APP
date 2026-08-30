@extends('layouts.app')

@section('content')
<div class="p-5 md:p-6 max-w-[1200px] mx-auto">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800">Backup &amp; Restore Data</h1>
            <p class="text-xs text-slate-500 mt-0.5">Cadangkan database SQLite sebagai .zip, lalu pulihkan kapan saja.</p>
        </div>
        <span class="text-xs text-slate-400">{{ $jumlahBackup }} backup &middot; total {{ round($totalUkuran / 1048576, 2) }} MB</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <h2 class="text-sm font-bold text-slate-700">Cadangkan Sekarang</h2>
            </div>
            <p class="text-[11px] text-slate-500 mb-4">Simpan snapshot database saat ini ke folder backup.</p>
            <form method="POST" action="{{ route('backup.create') }}">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-sm">
                    Buat Backup Baru
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <h2 class="text-sm font-bold text-slate-700">Pulihkan dari File (.zip)</h2>
            </div>
            <p class="text-[11px] text-slate-500 mb-4">Unggah file backup .zip dari flashdisk / folder lain.</p>
            <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data" onsubmit="return confirm('Yakin restore? Data saat ini akan ditimpa (backup keselamatan otomatis dibuat).')">
                @csrf
                <input type="file" name="file" accept=".zip" required class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-amber-700 file:font-semibold hover:file:bg-amber-100 mb-3">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 text-sm font-semibold">
                    Restore Sekarang
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <h2 class="text-sm font-bold text-slate-700">Tips Keamanan</h2>
            </div>
            <p class="text-[11px] text-slate-500 mb-2 leading-relaxed">
                Buat backup rutin sebelum menyusun anggaran besar atau mengubah pagu. Salin file .zip ke flashdisk / akun penyimpanan lain secara berkala.
            </p>
            <p class="text-[10px] text-slate-400">Auto-cadangan keselamatan dibuat otomatis sebelum setiap restore.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200">
            <h2 class="text-sm font-bold text-slate-700">Daftar Backup</h2>
            <p class="text-[11px] text-slate-400">Pilih salah satu untuk unduh, pulihkan, atau hapus.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-2.5 text-left font-semibold">File Backup</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Ukuran</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Dibuat</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($files as $f)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $f['nama'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ round($f['ukuran'] / 1048576, 2) }} MB</td>
                            <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::createFromTimestamp($f['waktu'])->format('d M Y H:i:s') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('backup.download', $f['nama']) }}" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-blue-600 hover:bg-blue-50">Unduh</a>
                                    <form method="POST" action="{{ route('backup.restore-file', $f['nama']) }}" onsubmit="return confirm('Pulihkan data dari backup ini?')">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-amber-600 hover:bg-amber-50">Pulihkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('backup.destroy', $f['nama']) }}" onsubmit="return confirm('Hapus file backup ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-slate-400">
                                <div class="text-sm font-semibold">Belum ada backup</div>
                                <div class="text-xs mt-1">Klik &ldquo;Buat Backup Baru&rdquo; untuk mulai.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection