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
             <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Perbarui data master kode barang (81rb baris) — cek online kalau ada WiFi, atau upload file .zip offline via USB/WA.</p>
         </div>
         <a href="{{ route('pengaturan.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
             ← Kembali ke Pengaturan
         </a>
     </div>

     <!-- Panel Cek Terbaru Online (konsep: foto terbaru) -->
     <div class="card overflow-hidden" id="katalog-online-panel" x-data="katalogOnline()">
         <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
             <div>
                 <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Cek Katalog Terbaru (Online)</h2>
                 <p class="text-[11px] text-slate-400 dark:text-slate-500">Kalau sekolah ada internet, biar KARSA ambil foto terbaru langsung — tanpa nunggu file WA.</p>
             </div>
             <span class="text-[11px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">Versi terpasang: <b class="text-slate-700 dark:text-slate-200">{{ $meta->versi ?? '-' }}</b></span>
         </div>
         <div class="p-6 space-y-4">
             <div class="flex flex-wrap items-center gap-3">
                 <button type="button" @click="cek()" :disabled="loading" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow disabled:opacity-50">
                     <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                     <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                     <span x-text="loading ? 'Mengecek...' : 'Cek Terbaru'"></span>
                 </button>
                 <span class="text-xs text-slate-400 dark:text-slate-500">Butuh internet • Timeout 5 detik • Kalau offline tetap bisa upload manual di bawah</span>
             </div>

             <!-- status -->
             <div x-show="pesan" x-cloak class="rounded-xl border p-3 text-sm" :class="pesanClass" x-text="pesan"></div>

             <!-- info update tersedia -->
             <div x-show="info && info.ada_katalog_online" x-cloak class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 p-4 space-y-3">
                 <div class="flex items-start justify-between gap-4">
                     <div>
                         <div class="text-sm font-bold text-emerald-800 dark:text-emerald-300" x-text="info?.ada_update ? 'Ada versi baru!' : 'Sudah versi terbaru'"></div>
                         <div class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">
                             Terpasang: <b x-text="info?.versi_sekarang"></b> → Terbaru: <b x-text="info?.versi_baru"></b>
                             <span x-show="info?.ukuran_mb"> • <span x-text="info?.ukuran_mb"></span> MB • <span x-text="info?.nama_file"></span></span>
                         </div>
                     </div>
                     <span class="shrink-0 text-[11px] px-2.5 py-1 rounded-full font-bold" :class="info && info.ada_update ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'" x-text="info && info.ada_update ? 'Update tersedia' : 'Up to date'"></span>
                 </div>
                 <form x-show="info && info.ada_update" method="POST" action="{{ route('pengaturan.katalog.unduh') }}" x-ref="formUnduh" @submit.prevent="unduh()">
                     @csrf
                     <input type="hidden" name="url_unduh" :value="info?.url_unduh">
                     <button type="submit" :disabled="unduhLoading" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow disabled:opacity-50">
                         <svg x-show="!unduhLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6h.1a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                         <svg x-show="unduhLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                         <span x-text="unduhLoading ? 'Memproses...' : 'Unduh & Pasang Sekarang'"></span>
                     </button>
                     <span class="text-[11px] text-emerald-600 dark:text-emerald-400 ml-2">± <span x-text="info?.ukuran_mb"></span> MB • Backup otomatis dulu • Jangan tutup halaman</span>
                 </form>
                 <!-- Notifikasi unduh: tampil segera, tidak menunggu halaman pindah -->
                 <div x-show="unduhLoading || unduhPesan" x-cloak class="rounded-xl border p-3 text-sm" :class="unduhClass">
                     <div class="flex items-start gap-2">
                         <svg x-show="unduhLoading" class="w-4 h-4 animate-spin shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                         <span x-text="unduhLoading ? unduhStatus : unduhPesan"></span>
                     </div>
                 </div>
             </div>
         </div>
     </div>

     <script>
     function katalogOnline(){
         return {
             loading:false, unduhLoading:false, info:null, pesan:'', pesanClass:'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300',
             unduhPesan:'', unduhStatus:'Mengunduh & menerapkan paket katalog…', unduhClass:'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300',
             async unduh(){
                 if(!this.info || !this.info.url_unduh){ this.pesan='URL unduhan tidak tersedia — coba Cek Terbaru lagi.'; return; }
                 this.unduhLoading=true;
                 this.unduhPesan='';
                 this.unduhStatus='Mengunduh & menerapkan paket katalog… (± beberapa menit, jangan tutup halaman)';
                 this.unduhClass='bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300';
                 try{
                     const r = await fetch(this.$refs.formUnduh.action, {
                         method:'POST',
                         headers:{'Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                         body: new FormData(this.$refs.formUnduh),
                     });
                     const j = await r.json().catch(()=>({success:false, message:'Respons server tidak valid.'}));
                     if(r.ok && j.success){
                         this.unduhPesan = j.message || 'Katalog berhasil diperbarui.';
                         this.unduhStatus = '';
                         this.unduhClass = 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300';
                         if(window.karsaToast) window.karsaToast(this.unduhPesan, 'success', 9000);
                         setTimeout(()=>location.reload(), 1800);
                     } else {
                         this.unduhPesan = j.message || 'Gagal menerapkan paket katalog.';
                         this.unduhStatus = '';
                         this.unduhClass = 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-800 dark:text-red-300';
                         if(window.karsaToast) window.karsaToast(this.unduhPesan, 'error', 12000);
                     }
                 }catch(e){
                     this.unduhPesan = 'Gagal: ' + e.message;
                     this.unduhStatus = '';
                     this.unduhClass = 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-800 dark:text-red-300';
                     if(window.karsaToast) window.karsaToast(this.unduhPesan, 'error', 12000);
                 }finally{
                     this.unduhLoading=false;
                 }
             },
             async cek(){
                 this.loading=true; this.pesan='Mengecek ke GitHub...'; this.pesanClass='bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30 text-blue-700 dark:text-blue-300';
                 this.info=null;
                 try{
                     const r = await fetch('{{ route('pengaturan.katalog.cek') }}', {headers:{'Accept':'application/json'}});
                     const j = await r.json();
                     if(j.offline){ this.pesan=j.message||'Offline — tetap bisa upload manual di bawah.'; this.pesanClass='bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300'; }
                     else if(j.ada_katalog_online===false){ this.pesan=j.message||'Belum ada paket katalog online.'; this.pesanClass='bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'; }
                     else { this.info=j; this.pesan=j.ada_update ? 'Versi baru ditemukan — klik Unduh & Pasang di bawah.' : 'Sudah pakai versi terbaru — tidak perlu update.'; this.pesanClass=j.ada_update ? 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'; }
                 }catch(e){ this.pesan='Gagal cek (offline?) — tetap bisa upload file .zip manual di bawah.'; this.pesanClass='bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300'; }
                 finally{ this.loading=false; }
             }
         }
     }
     </script>

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
                        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Meta: {{ number_format($meta->jumlah_barang ?? 0,0,',','.') }} • {{ $meta->tanggal_update ? $meta->tanggal_update->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y H:i') . ' WIB' : '-' }}</div>
                    </div>
                </div>
                <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 p-3 text-xs text-slate-600 dark:text-slate-300">
                    <div><b>Sumber:</b> {{ $meta->source_file ?? '-' }}</div>
                    <div class="text-[11px] text-slate-400 dark:text-slate-500">Update terakhir: {{ $meta->tanggal_update ? $meta->tanggal_update->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d/m/Y H:i:s') . ' WIB' : '-' }} • ID #{{ $meta->id }}</div>
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
