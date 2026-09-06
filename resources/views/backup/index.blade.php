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

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Backup &amp; Restore Data</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Cadangkan database SQLite sebagai .zip, lalu pulihkan kapan saja.</p>
        </div>
        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $jumlahBackup }} backup &middot; total {{ round($totalUkuran / 1048576, 2) }} MB &middot; retensi: auto 7, backup 10, pre 10, pengesahan 20</span>
    </div>

    @isset($rekomendasi)
    <div class="card p-5 border-2 border-emerald-300 dark:border-emerald-500/40 bg-emerald-50/40 dark:bg-emerald-500/5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5-5 5 5"/></svg>
                </div>
                <div>
                    <div class="text-[11px] font-bold tracking-widest uppercase text-emerald-600 dark:text-emerald-400">Yang perlu diambil — untuk flashdisk</div>
                    <div class="text-sm font-extrabold text-slate-800 dark:text-white mt-1 font-mono">{{ $rekomendasi['nama'] }}</div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 mt-1"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $rekomendasi['badge'] }}">{{ $rekomendasi['jenis'] }}</span> <span class="ml-1.5">{{ $rekomendasi['desc'] }}</span> — {{ round($rekomendasi['ukuran']/1048576,2) }} MB · {{ \Illuminate\Support\Carbon::createFromTimestamp($rekomendasi['waktu'])->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y H:i:s') }} WIB</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-500 mt-2">Jika bingung, ambil file ini saja. Yang lain hanya arsip otomatis/safety.</div>
                    <details class="mt-3">
                        <summary class="text-xs font-bold text-emerald-700 dark:text-emerald-400 cursor-pointer hover:underline">Apa saja isi file ini? — klik untuk lihat</summary>
                        <div class="mt-2 rounded-lg bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-500/30 p-3 text-xs leading-relaxed">
                            <p class="font-semibold text-slate-700 dark:text-slate-200">File ini adalah snapshot LENGKAP 1 database — mencakup semuanya, bukan per tabel:</p>
                            <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5 list-disc pl-5 text-slate-600 dark:text-slate-300">
                                <li><span class="font-semibold">Profil sekolah &amp; pagu</span> (NPSN, kepala, bendahara, pagu total/tahap)</li>
                                <li><span class="font-semibold">Tahun anggaran</span> &amp; status pengesahan (Draft/Disahkan/Pergeseran)</li>
                                <li><span class="font-semibold">Master</span> — 139 program, 276 rekening, 9 jenis belanja, 81.062 barang + harga SSH</li>
                                <li><span class="font-semibold">RKAS lengkap</span> — semua rincian uraian, volume, harga, alokasi 12 bulan (Jan–Des) &amp; koreksi</li>
                                <li><span class="font-semibold">Audit log &amp; katalog</span> — riwayat ubah/hapus, versi katalog</li>
                                <li><span class="font-semibold">Akun &amp; lisensi</span> — user login &amp; aktivasi perangkat</li>
                            </ul>
                            <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Teknis: 1 file <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-mono">database/database.sqlite</code> (±38 MB, 5,4 MB setelah di-zip) dibuat via <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-mono">VACUUM INTO</code> agar konsisten. Restore di komputer lain akan pulihkan <span class="font-semibold">semua data di atas sekaligus</span> — tidak perlu backup per bagian.</p>
                            <p class="mt-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Cukup ambil 1 file terbaru ini untuk flashdisk — tidak perlu ambil 5 kategori.</p>
                        </div>
                    </details>
                </div>
            </div>
            <a href="{{ route('backup.download', $rekomendasi['nama']) }}" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-md shadow-emerald-600/20 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Unduh File Ini
            </a>
        </div>
    </div>
    @endisset

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="card p-6">
            <div class="flex items-center gap-2.5 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                </div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Cadangkan Sekarang</h2>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4">Simpan snapshot database saat ini ke folder backup.</p>
            <form method="POST" action="{{ route('backup.create') }}">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors">
                    Buat Backup Baru
                </button>
            </form>
        </div>

        <div class="card p-6">
            <div class="flex items-center gap-2.5 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Pulihkan dari File (.zip)</h2>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4">Unggah file backup .zip dari flashdisk / folder lain.</p>
            <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data" onsubmit="return confirm('Yakin restore? Data saat ini akan ditimpa (backup keselamatan otomatis dibuat).')">
                @csrf
                <input type="file" name="file" accept=".zip" required class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-amber-700 file:font-semibold hover:file:bg-amber-100 dark:file:bg-amber-500/10 dark:file:text-amber-400 mb-3">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-amber-300 dark:border-amber-500/40 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-sm font-semibold transition-colors">
                    Restore Sekarang
                </button>
            </form>
        </div>

        <div class="card p-6">
            <div class="flex items-center gap-2.5 mb-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Tips Keamanan</h2>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-2 leading-relaxed">
                <span class="font-bold text-emerald-700 dark:text-emerald-400">Ambil yang Manual</span> untuk flashdisk. Otomatis & Safety hanya jaga-jaga; Pengesahan adalah arsip resmi.
            </p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">Jika bingung, unduh file berlabel <span class="px-1 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">AMBIL INI</span> di daftar bawah.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Daftar Backup</h2>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Menampilkan {{ $files->count() }} dari {{ $files->total() }} file (20/halaman) — Pengurutan terbaru di atas. Retensi per kategori: auto 7, backup 10, pre 10, pengesahan 20 (terbaru dipertahankan).</p>
            </div>
            <span class="text-[11px] font-mono text-slate-400 hidden sm:inline">hal {{ $files->currentPage() }}/{{ $files->lastPage() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                        <th class="px-5 py-3 text-left font-semibold">File Backup</th>
                        <th class="px-5 py-3 text-left font-semibold">Jenis</th>
                        <th class="px-5 py-3 text-right font-semibold">Ukuran</th>
                        <th class="px-5 py-3 text-left font-semibold">Dibuat</th>
                        <th class="px-5 py-3 text-center font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse($files as $f)
                        @php $rowIsRekom = isset($rekomendasi) && $f['nama'] === $rekomendasi['nama']; @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 {{ $rowIsRekom ? 'bg-emerald-50/40 dark:bg-emerald-500/5' : '' }}">
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-700 dark:text-slate-300">
                                <div class="flex items-center gap-2">
                                    <span>{{ $f['nama'] }}</span>
                                    @if($rowIsRekom) <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-emerald-500 text-white tracking-wide">AMBIL INI</span> @endif
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 font-sans mt-0.5">{{ $f['desc'] }}</div>
                            </td>
                            <td class="px-5 py-3.5"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $f['badge'] }}">{{ $f['jenis'] }}</span></td>
                            <td class="px-5 py-3.5 text-right text-slate-500 dark:text-slate-400">{{ round($f['ukuran'] / 1048576, 2) }} MB</td>
                            <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($f['waktu'])->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y H:i:s') }} WIB</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('backup.download', $f['nama']) }}" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors">Unduh</a>
                                    <form method="POST" action="{{ route('backup.restore-file', $f['nama']) }}" onsubmit="return confirm('Pulihkan data dari backup ini?')">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors">Pulihkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('backup.destroy', $f['nama']) }}" onsubmit="return confirm('Hapus file backup ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="text-sm font-semibold">Belum ada backup</div>
                                <div class="text-xs mt-1">Klik &ldquo;Buat Backup Baru&rdquo; untuk mulai.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($files->hasPages())
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700/60">
            {{ $files->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
