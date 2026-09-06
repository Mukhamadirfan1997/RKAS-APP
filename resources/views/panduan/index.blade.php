@extends('layouts.app')

@section('content')
<style>
@media print {
    aside, header, nav, #btn-cetak, #toc-card .no-print { display: none !important; }
    main { padding: 0 !important; }
    body { background: white !important; }
    .card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
    details { open: true; }
}
</style>
<div class="max-w-4xl mx-auto space-y-6" x-data="panduanData()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Panduan Penggunaan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Bahasa sederhana untuk Bendahara & Operator — klik judul untuk buka.</p>
        </div>
        <button id="btn-cetak" type="button" @click="printAll()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors no-print shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Cetak Panduan Ini
        </button>
    </div>

    <!-- Daftar Isi -->
    <div id="toc-card" class="card p-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Daftar Isi</h2>
            <div class="flex gap-1.5 no-print">
                <button type="button" @click="openAll()" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200">Buka semua</button>
                <button type="button" @click="closeAll()" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50">Tutup semua</button>
            </div>
        </div>
        <ol class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-sm">
            <template x-for="s in sections" :key="s.id">
                <li>
                    <a :href="'#' + s.id" @click.prevent="goTo(s.id)" class="flex items-center gap-2 px-2.5 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                        <span class="w-6 h-6 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 flex items-center justify-center text-[11px] font-bold text-blue-700 dark:text-blue-300 shrink-0" x-text="s.no"></span>
                        <span class="text-xs font-semibold leading-tight" x-text="s.title"></span>
                    </a>
                </li>
            </template>
        </ol>
    </div>

    <!-- Sections -->
    <div class="space-y-3">
        <!-- 1 -->
        <section :id="sections[0].id" class="card overflow-hidden" :class="open[1] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(1)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-xs font-extrabold">1</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Persiapan Awal — Profil, Pagu & Tahun Anggaran</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[1] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[1]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p><strong class="text-slate-800 dark:text-white">Urutan yang benar:</strong> buat/aktifkan <em>Tahun Anggaran</em> dulu, baru isi Pagu.</p>
                    <ol class="list-decimal pl-5 space-y-1.5">
                        <li>Buka <strong>Pengaturan → Tahun Anggaran</strong>. Klik <em>Tambah Tahun</em> (mis. 2026), isi sumber dana BOSP REGULER, lalu klik <em>Aktifkan</em> pada tahun yang akan dipakai.</li>
                        <li>Masih di Pengaturan, buka <strong>Pengaturan → Profil Sekolah</strong>. Isi NPSN, nama sekolah, kepala sekolah, bendahara, alamat lengkap, dan pilih <em>Status Sekolah</em> (Negeri/Swasta) — ini menentukan batas honor 20% atau 40%.</li>
                        <li>Buka <strong>Pengaturan → Pagu</strong>. Isi Pagu Total 1 tahun, lalu Pagu Tahap I (biasanya 50%) dan Tahap II otomatis sisa. Klik Simpan.</li>
                    </ol>
                    <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-lg px-3 py-2.5 text-xs">
                        <strong>Tips:</strong> Kalau Pagu atau Tahun masih kosong, hitungan di Dashboard akan 0. Isi dulu sebelum menyusun RKAS.
                    </div>
                </div>
            </div>
        </section>

        <!-- 2 -->
        <section :id="sections[1].id" class="card overflow-hidden" :class="open[2] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(2)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs font-extrabold">2</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Menyusun RKAS — Tambah Anggaran</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[2] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[2]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <ol class="list-decimal pl-5 space-y-1.5">
                        <li>Buka <strong>Lembar Kerja</strong>, klik tombol <em>+ Tambah Anggaran</em>.</li>
                        <li><strong>Langkah 1 — Pilih Kegiatan & Rekening:</strong> ketik nama kegiatan (mis. “Ekstrakurikuler”), pilih dari daftar. Lalu ketik rekening belanja (mis. “ATK”), pilih yang sesuai.</li>
                        <li><strong>Langkah 2 — Detail:</strong> isi <em>Uraian</em> (mis. “Kertas HVS A4 untuk lomba”). Saat mengetik, akan muncul saran katalog — klik untuk isi otomatis harga & satuan. Isi <em>Volume</em>, <em>Satuan</em> (paket/buah/rim), dan <em>Harga Satuan</em>.</li>
                        <li><strong>Alokasi per Bulan:</strong> isi volume di kartu bulan yang dipakai (Jan–Des). Tahap I biru (Jan–Jun), Tahap II hijau (Jul–Des). Tombol <em>Salin Jan ke semua bulan</em> untuk menyalin cepat.</li>
                        <li>Klik <em>Simpan</em>. Data langsung muncul di tabel.</li>
                    </ol>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Harga Satuan menampilkan info SSH (batas bawah/atas) jika ada di katalog — warna hijau = sesuai, kuning/merah = di luar rentang.</p>
                </div>
            </div>
        </section>

        <!-- 3 -->
        <section :id="sections[2].id" class="card overflow-hidden" :class="open[3] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(3)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-violet-600 text-white flex items-center justify-center text-xs font-extrabold">3</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Memahami Badge Kontrol & Validasi</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[3] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[3]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">OK</span> = harga yang Anda isi <strong>sama</strong> dengan harga acuan katalog. <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border bg-red-50 text-red-700 border-red-200">SELISIH</span> = harga berbeda dari acuan. Bukan error — hanya pengingat untuk cek lagi.</p>
                    <p><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">BENAR</span> = total rupiah sama dengan jumlah alokasi Tahap I + Tahap II. <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border bg-red-50 text-red-700 border-red-200">SALAH</span> = ada selisih — cek volume per bulan, pastikan jumlahnya pas.</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Badge ini muncul di tabel Lembar Kerja dan di file Excel/PDF.</p>
                </div>
            </div>
        </section>

        <!-- 4 -->
        <section :id="sections[3].id" class="card overflow-hidden" :class="open[4] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(4)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xs font-extrabold">4</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Sisip & Edit Item</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[4] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[4]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p><strong>Sisip Serupa:</strong> di tabel, klik tombol <em>⋯</em> pada baris, pilih <em>Sisip Serupa</em>. Form akan terbuka dengan Kegiatan & Rekening sudah terisi — tinggal ganti uraian/volume/harga.</p>
                    <p><strong>Edit:</strong> klik <em>⋯ → Edit</em> untuk ubah item yang sudah ada. Ganti angka, lalu Simpan.</p>
                    <p><strong>Lihat Detail:</strong> klik <em>⋯ → Lihat Detail</em> untuk melihat rincian tanpa bisa mengubah (mode baca).</p>
                </div>
            </div>
        </section>

        <!-- 5 -->
        <section :id="sections[4].id" class="card overflow-hidden" :class="open[5] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(5)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-amber-600 text-white flex items-center justify-center text-xs font-extrabold">5</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Mengesahkan RKAS</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[5] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[5]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p>Setelah semua anggaran diisi dan Sisa Pagu = Rp 0, buka <strong>Pengaturan → Status RKAS</strong>, klik <em>Sahkan</em>.</p>
                    <p>Saat disahkan: Lembar Kerja terkunci (tidak bisa tambah/edit/hapus), Pagu terkunci, dan sistem otomatis membuat backup snapshot. Label di cetakan berubah jadi <strong>DISAHKAN</strong>.</p>
                    <p>Kalau perlu revisi, klik <em>Buka Kembali</em> — status jadi <strong>Pergeseran</strong>, data bisa diedit lagi. Setelah selesai, sahkan ulang.</p>
                </div>
            </div>
        </section>

        <!-- 6 -->
        <section :id="sections[5].id" class="card overflow-hidden" :class="open[6] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(6)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-rose-600 text-white flex items-center justify-center text-xs font-extrabold">6</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Mengekspor Laporan — 4 Pilihan</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[6] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[6]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p>Di Lembar Kerja, klik <strong>Cetak/Unduh</strong>:</p>
                    <ul class="list-disc pl-5 space-y-2">
                        <li><strong>PDF Lengkap (1 Tahun)</strong> — semua item 12 bulan, untuk arsip & tanda tangan.</li>
                        <li><strong>PDF Per Tahap (Tahap I / Tahap II)</strong> — hanya 6 bulan (Jan–Jun atau Jul–Des) dengan rincian volume per bulan, untuk laporan per tahap.</li>
                        <li><strong>PDF Per Bulan</strong> — 1 bulan saja (mis. Februari), untuk cek rencana bulan itu.</li>
                        <li><strong>Excel Lengkap (1 Tahun)</strong> — file .xlsx, semua item + 12 bulan + Kontrol/Validasi, bisa diedit lanjut di Excel.</li>
                    </ul>
                    <p class="text-xs text-slate-500">Nama file otomatis: <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-700 rounded">2026-RKAS-Lengkap-Disahkan-SDN-Toyaning-1.pdf</code> — berisi tahun, varian, status, dan nama sekolah.</p>
                </div>
            </div>
        </section>

        <!-- 7 -->
        <section :id="sections[6].id" class="card overflow-hidden" :class="open[7] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(7)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-cyan-600 text-white flex items-center justify-center text-xs font-extrabold">7</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Monitoring Kepatuhan — JUKNIS & RKA Gelondongan</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[7] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[7]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p>Buka <strong>Monitoring JUKNIS</strong> untuk cek kepatuhan:</p>
                    <ul class="list-disc pl-5 space-y-1.5">
                        <li><strong>JUKNIS (persentase dari pagu):</strong> Honor ≤20% (negeri) / ≤40% (swasta), Buku ≥10%, Sarpras ≤20%, Tahap I ≥50%. Warna hijau = sesuai, merah/kuning = perlu sesuaikan.</li>
                        <li><strong>RKA Gelondongan (nominal per kategori):</strong> 3 kategori — Barang Jasa, Modal Mesin, Modal Aset. Isi <em>Target</em> di <strong>Pengaturan → Pagu</strong> sesuai file PAK/RKA dari Dinas. Monitoring menampilkan Target vs Realisasi & selisih.</li>
                    </ul>
                    <p>Kalau ada yang belum sesuai, kembali ke Lembar Kerja dan sesuaikan volume/harga, lalu cek lagi di Monitoring.</p>
                </div>
            </div>
        </section>

        <!-- 8 -->
        <section :id="sections[7].id" class="card overflow-hidden" :class="open[8] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(8)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-slate-600 text-white flex items-center justify-center text-xs font-extrabold">8</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Update Katalog</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[8] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[8]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p>Katalog berisi 81.000+ barang dengan harga acuan dari ARKAS. Update kalau ada katalog baru dari pengembang.</p>
                    <ol class="list-decimal pl-5 space-y-1.5">
                        <li>Buka <strong>Pengaturan → Update Katalog</strong>.</li>
                        <li>Siapkan file <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-700 rounded">.zip</code> dari pengembang (berisi <code>katalog.csv</code> + <code>manifest.json</code>).</li>
                        <li>Upload file, tunggu proses (10–15 detik). Barang kustom sekolah tetap aman — tidak akan terhapus.</li>
                    </ol>
                    <p class="text-xs text-slate-500">Tips: backup dulu sebelum update.</p>
                </div>
            </div>
        </section>

        <!-- 9 -->
        <section :id="sections[8].id" class="card overflow-hidden" :class="open[9] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(9)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-teal-600 text-white flex items-center justify-center text-xs font-extrabold">9</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Backup & Restore</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[9] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[9]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <p><strong>Backup rutin sangat penting.</strong> Buka <strong>Backup</strong>, klik <em>Buat Backup</em> — file <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-700 rounded">.zip</code> tersimpan di komputer.</p>
                    <p><strong>Restore kalau ada masalah:</strong> upload file backup atau klik <em>Restore</em> pada daftar backup. Sistem akan membuat backup pengaman dulu sebelum restore.</p>
                    <p class="text-xs text-slate-500">Simpan backup di flashdisk juga — jaga-jaga kalau komputer rusak.</p>
                </div>
            </div>
        </section>

        <!-- 10 -->
        <section :id="sections[9].id" class="card overflow-hidden" :class="open[10] ? 'ring-1 ring-blue-200 dark:ring-blue-500/30' : ''">
            <button type="button" @click="toggle(10)" class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-orange-600 text-white flex items-center justify-center text-xs font-extrabold">10</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">Pertanyaan Umum (FAQ)</span>
                </span>
                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="open[10] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open[10]" x-collapse x-cloak class="px-5 pb-5 border-t border-slate-100 dark:border-slate-700/60">
                <div class="pt-4 space-y-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Kenapa ada badge SELISIH?</p>
                        <p class="mt-1">Harga yang Anda isi beda dari harga acuan katalog. Cek lagi — kalau memang sengaja beda (harga lokal), abaikan saja.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Kenapa RKAS tidak bisa diedit setelah disahkan?</p>
                        <p class="mt-1">Itu sengaja dikunci agar data resmi tidak berubah. Klik <em>Buka Kembali</em> di Pengaturan → Status RKAS untuk revisi.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Kenapa nomor barang/kode rekening panjang sekali?</p>
                        <p class="mt-1">Itu kode resmi ARKAS (mis. 5.1.02.01...). Cukup ketik kata kunci (mis. “ATK”) — sistem akan mencarikan.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Sisa Pagu harus berapa?</p>
                        <p class="mt-1">Harus <strong>Rp 0</strong> sebelum pengesahan. Kalau masih lebih/kurang, tambah atau kurangi anggaran.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Harga Satuan harus ikut katalog?</p>
                        <p class="mt-1">Tidak wajib. Katalog hanya acuan. Anda boleh isi harga sesuai toko lokal — sistem hanya memberi peringatan jika di luar rentang SSH.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Apa itu SSH / batas bawah & atas?</p>
                        <p class="mt-1">Standar Satuan Harga — rentang wajar harga barang. Kalau harga di luar rentang, kartu SSH di form akan warna kuning/merah.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Data hilang setelah update aplikasi?</p>
                        <p class="mt-1">Tidak. Data RKAS aman — aplikasi mendeteksi database lama dan tidak menimpanya. Jika ragu, backup dulu.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white">Q: Excel yang diunduh bisa diedit?</p>
                        <p class="mt-1">Bisa. File Excel Lengkap bisa dibuka dan diedit di Microsoft Excel / WPS seperti biasa.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
function panduanData() {
    return {
        sections: [
            { no: '1', id: 'sec-persiapan', title: 'Persiapan Awal' },
            { no: '2', id: 'sec-menyusun', title: 'Menyusun RKAS' },
            { no: '3', id: 'sec-kontrol', title: 'Badge Kontrol & Validasi' },
            { no: '4', id: 'sec-sisip', title: 'Sisip & Edit' },
            { no: '5', id: 'sec-sahkan', title: 'Mengesahkan RKAS' },
            { no: '6', id: 'sec-ekspor', title: 'Mengekspor Laporan' },
            { no: '7', id: 'sec-monitoring', title: 'Monitoring Kepatuhan' },
            { no: '8', id: 'sec-katalog', title: 'Update Katalog' },
            { no: '9', id: 'sec-backup', title: 'Backup & Restore' },
            { no: '10', id: 'sec-faq', title: 'FAQ' },
        ],
        open: {1:false,2:false,3:false,4:false,5:false,6:false,7:false,8:false,9:false,10:false},
        toggle(n) { this.open[n] = !this.open[n]; },
        openAll() { Object.keys(this.open).forEach(k=>this.open[k]=true); },
        closeAll() { Object.keys(this.open).forEach(k=>this.open[k]=false); },
        goTo(id) {
            const n = this.sections.findIndex(s=>s.id===id)+1;
            if (n>0) this.open[n]=true;
            this.$nextTick(()=>document.getElementById(id)?.scrollIntoView({behavior:'smooth', block:'start'}));
        },
        printAll() {
            this.openAll();
            this.$nextTick(()=>setTimeout(()=>window.print(), 300));
        }
    }
}
</script>
@endsection
