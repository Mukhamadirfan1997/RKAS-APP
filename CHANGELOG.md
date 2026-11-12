# Changelog KARSA — Kertas Anggaran Sekolah

Semua perubahan penting aplikasi dicatat di sini. Versi terbaru di paling atas.

## [1.7.3] - 2026-11-09

### Diperbaiki
- **Kesiapan RKAS tidak lagi memberi nilai lulus semu** — pada instalasi baru atau tahun anggaran baru (pagu masih Rp 0, belum ada uraian), skor kini **0%** dan syarat yang datanya belum ada ditandai *belum bisa dinilai*, bukan dicentang hijau.
- Badge kepatuhan JUKNIS di Dashboard menampilkan **BELUM DINILAI** saat pagu atau uraian anggaran belum diisi (sebelumnya "SESUAI" padahal belum ada yang dinilai).

## [1.7.2] - 2026-11-09

### Diperbaiki
- **Halaman login, registrasi, dan pulihkan sandi responsif** — logo dan kartu menyesuaikan layar kecil, halaman bisa digulir, latar tidak lagi terpotong.
- **Istilah lembar kerja dibenahi**: *Program / Standar*, *Uraian Barang / Jasa*, *Total Anggaran*, *Harga Acuan*, serta kartu **Alokasi Tahap I (Jatah 50%)** dengan peringatan nominal kelebihan dan kartu Sisa Pagu tiga kondisi.

## [1.7.1] - 2026-11-08

### Ditambahkan
- **Jatah 50% per tahap** — Tahap I harus *tepat* 50% dan Tahap II *maksimal* 50% dari pagu 1 tahun (sebelumnya hanya batas minimal), ikut dinilai di Kesiapan RKAS dan Monitoring JUKNIS.
- **Pagu tahap otomatis 50% : 50%** saat Pagu 1 Tahun diisi — tetap bisa diubah manual, dengan tombol "Bagi ulang otomatis".

### Diperbaiki
- Dashboard dirapikan: kartu Tahap I memberi peringatan nominal kelebihan, Sisa Pagu punya bar progres, dan checklist kesiapan hanya menampilkan syarat yang belum terpenuhi.

## [1.7.0] - 2026-11-08

### Ditambahkan
- **Kesiapan RKAS** (12 syarat, 13 bila target RKA Gelondongan diisi) + halaman `/kesiapan` yang bisa diklik langsung menuju item yang bermasalah.
- **Gerbang pengesahan** — RKAS yang belum siap tidak bisa disahkan, kecuali operator menuliskan alasan (tercatat di audit log beserta daftar syarat yang belum terpenuhi).

### Diperbaiki
- **Pembaruan aplikasi tidak lagi terhalang** proses yang mengunci folder: hook installer menghentikan KARSA/php.exe sebelum menimpa berkas, dan job object Windows memastikan php.exe ikut berhenti bila aplikasi ditutup atau crash.
- `composer.json` kembali ikut bundel sehingga **migrasi otomatis** berjalan lagi di aplikasi terpasang.

## [1.6.1] - 2026-11-07

### Ditambahkan
- **Pencarian data terinput** di Lembar Kerja (uraian, keterangan khusus, kegiatan, kode rekening).

### Diperbaiki
- Sidebar disederhanakan — hanya Dashboard dan Lembar Kerja, agar tidak dobel dengan menu pada profil.

## [1.6.0] - 2026-11-06

### Ditambahkan
- **Registrasi akun saat pertama dibuka** (tidak ada akun bawaan) dan **Pulihkan dari backup** tanpa perlu registrasi lebih dulu.
- Tombol **tampilkan sandi** pada login, lupa, dan reset sandi.
- **Dialog "Simpan Sebagai"** bawaan Windows untuk setiap unduhan PDF/Excel/backup.
- Dropdown saran langsung tampil saat kolom diklik dan memuat lebih banyak saat digulir.

### Diperbaiki
- Pengaturan dipecah menjadi 4 tab berurutan (Profil → Tahun Anggaran → Pagu & Status → Akun); sidebar kembali tampil di Lembar Kerja.
- Aplikasi lebih ringan: OPcache aktif (waktu muat 1,1 s → ±0,1 s), font di-host sendiri, installer 62 MB → 35 MB.

## [1.5.1] - 2026-11-05

### Diperbaiki
- **Dialog "Simpan Berkas KARSA" muncul kembali** saat mengunduh (perbaikan penyedia kripto rustls) dan tidak lagi menggantung bila terjadi kegagalan.
- Hasil migrasi otomatis kini dicatat ke `storage/logs/migrate.log` pada aplikasi terpasang.

## [1.5.0] - 2026-11-04

### Ditambahkan
- **Katalog barang 81.062 → 91.157** dengan mengimpor Referensi Barang Jasa & Barang Modal dari Dinas (10.095 kode baru, pemetaan rekening otomatis).
- **Migrasi database otomatis** saat aplikasi terpasang dijalankan (sebelumnya hanya lewat perintah manual).

### Diperbaiki
- Penyimpanan ditolak bila seluruh volume bulan masih 0 — tidak ada lagi baris Rp 0 yang menggelembungkan kertas kerja.
- Harga acuan referensi Dinas diterapkan dengan pencocokan nama + rekening (sebelumnya klaim "1.052 harga diperbarui" ternyata 0 yang kena).

## [1.4.1] - 2026-11-03

### Diperbaiki
- **Honor hanya dihitung dari kegiatan `07.12.*`** — honor ekstrakurikuler tidak lagi masuk persentase honor.
- Kartu **Total Bulan** muncul saat filter bulan aktif (default "Semua").
- **Uraian bisa 100% diketik bebas** dan dilepas dari katalog tanpa kehilangan teks/harga.
- Tombol unduh memakai dialog simpan bawaan Windows; nomor urut internal tidak lagi ditampilkan di lembar kerja.

## [1.4.0] - 2026-10-31

### Ditambahkan
- **Referensi Dinas terbaru**: 23 kegiatan baru, 3 rekening modal (compressor, genset, pompa), dan 1.052 harga acuan.
- **133 entri katalog manual sekolah** dilengkapi kode rekeningnya — tidak ada lagi kode rekening `.....` yang tidak bisa dipilih.

### Diperbaiki
- Baris katalog berkode `.....` terbukti entri manual sekolah (buku, laptop, drumband, dll) sehingga **tidak dihapus**, hanya dirapikan rekeningnya.

## [1.3.0] - 2026-09-24

### Ditambahkan
- **Cek & unduh katalog barang terbaru secara online** dengan fallback offline (paket zip + manifest + checksum SHA256); barang kustom sekolah tidak pernah terhapus.
- Perintah `php artisan katalog:build-update` untuk membuat paket pembaruan katalog.

## [1.2.0] - 2026-09-16

### Ditambahkan
- **Arsip otomatis audit log lebih dari 90 hari** ke tabel `audit_log_archives` agar aplikasi tetap ringan; arsip bisa dilihat di halaman Audit Log.

### Diperbaiki
- Pemeriksaan integritas database berkala (30 hari) dengan banner peringatan permanen bila terdeteksi kerusakan.

## [1.1.0] - 2026-09-16

### Ditambahkan
- **Mode WAL** pada SQLite (`synchronous = NORMAL`) + `PRAGMA integrity_check` setiap 30 hari.
- Backup memakai `VACUUM INTO` sehingga salinan tetap konsisten meski aplikasi sedang dipakai.

## [1.0.1] - 2026-09-09

### Ditambahkan
- Bundel PHP + Laravel mandiri (tanpa XAMPP) dan ikon brand KARSA.

### Diperbaiki
- Penanda tur panduan disimpan di database, bukan `localStorage` — tur tidak muncul berulang walau port aplikasi berubah.
- Banner versi tidak lagi salah menampilkan `v0.0.0` di aplikasi terpasang.

## [1.0.0] - 2026-09-07

### Ditambahkan
- **Cek & unduh pembaruan aplikasi** langsung dari GitHub Releases, termasuk progres unduhan.
- Lembar kerja dipaginasi 50 baris, rekomendasi backup berkala, dan pengingat istirahat 60 menit yang bisa diatur.
- **Import master data** lebih toleran terhadap variasi judul kolom Excel (BOM, spasi, alias kolom).

## [0.2.1] - 2026-09-06

### Diperbaiki & Ditambahkan
- **Pengingat Istirahat** — reset timer saat log out (`layouts/app.blade.php` & `topbar.blade.php` `onsubmit` set `karsa_break_last`) + grace 3 menit setelah load (`app.js:pageLoadAt`) — tidak lagi muncul detik pertama setelah login.
- **Dashboard — Tombol Tur Panduan** — `dashboard/index.blade.php` tombol amber `Tur Panduan` yang panggil `window.__karsaStartDashboardTour()` untuk trigger ulang tur Dashboard.
- **Tentang & Panduan full width** — `tentang/index.blade.php` & `panduan/index.blade.php` pindah ke `layouts/focus` (`w-full max-w-none`) + tombol `Kembali ke Dashboard`, memanfaatkan lebar penuh monitor (sebelumnya `max-w-4xl` sempit).
- **Tur berantai** — `tours.js` Dashboard `Lanjut ke Lembar Kerja →` → confirm pindah `/rkas` → Lembar Kerja `Lanjut ke Monitoring →` → `/monitoring/juknis` → `Selesai ✓`; done text kontekstual & hint "Tur berikutnya" — user tidak kira tur selesai di Dashboard.

## [0.2.0] - 2026-09-06

### Ditambahkan
- **Tentang Aplikasi & Panduan** — halaman `/tentang` (versi dinamis dari `tauri.conf.json`, identitas sekolah otomatis, kontak pengembang terpusat, changelog) + `/panduan` (10 section accordion + cetak); grup sidebar "Bantuan".
- **Tur Interaktif offline** — `driver.js` dibundle lokal (tanpa CDN), 3 tur otomatis sekali (Dashboard 4 langkah, Lembar Kerja 5 langkah, Monitoring 3 langkah, localStorage `karsa_tour_*_seen`), tombol "Mulai Tur Ulang" di Tentang.
- **Sistem Lisensi & Trial 30 Hari offline** — kecamatan mengandung "rejoso" gratis selamanya (bypass total, tanpa banner); sekolah lain trial 30 hari sejak `lisensi.installed_at` → lewat 30 hari tanpa aktivasi masuk Mode Lihat-Saja (blokir tulis POST/DELETE + ekspor PDF/Excel). Kode aktivasi HMAC-SHA256 per tahun (`config/lisensi.php:checksum_seed`, format `XXXX-XXXX-XXXX-XXXX` tanpa 0/O/1/I, `hash_equals`), `php artisan lisensi:buat-kode {device} {tahun}`, banner kuning ≤7 hari & merah read-only, halaman `/aktivasi` (salin device_code, sisa hari, form aktivasi, kontak terpusat).

## [0.1.0] - 2026-09-05

Versi awal KARSA 2026 — aplikasi desktop offline pengganti Excel untuk menyusun RKAS.

### Ditambahkan
- **First-run bundling** — database pre-seeded `database-seed.sqlite` (81.062 barang + master) disalin otomatis ke folder aplikasi saat pertama dijalankan, tanpa perlu seeding manual.
- **Update katalog offline** — paket `.zip` berisi `katalog.csv` + `manifest.json` (checksum SHA256) untuk memperbarui katalog barang tanpa internet; barang kustom sekolah tetap aman (upsert, tidak hapus).
- **Pengesahan RKAS & kunci data** — status `Draft / Disahkan / Pergeseran`; saat Disahkan, Lembar Kerja dan Pagu terkunci (guard server + banner UI), snapshot backup otomatis `rkas-pengesahan-*.zip`; Buka Kembali untuk revisi.
- **4 varian ekspor** — PDF Lengkap (1 Tahun), PDF Per Tahap (Tahap I/II breakdown 6 bulan), PDF Per Bulan (1 bulan), dan Excel Lengkap (1 Tahun); flat ARKAS `Kegiatan > Rekening > Item` tanpa duplikasi Tahap I/II; kolom Kontrol & Validasi; kertas A3 landscape.
- **Redesain UI premium** — sidebar + topbar + dark mode global, layout focus full-width untuk Lembar Kerja, tabel 9 kolom lega, modal Detail Anggaran 2-step (Pilih Kegiatan & Rekening → Detail Uraian & Alokasi 12 bulan), pill filter bulan, dropdown Cetak/Unduh yang jelas (Ringkas disembunyikan).
- **RKA Gelondongan** — rekap 3 kategori (Barang Jasa / Modal Mesin / Modal Aset Lainnya) dengan target dari Dinas; ditampilkan di Dashboard dan Monitoring; ikut menentukan Skor Kesiapan RKAS (6 item bila target diisi).
- **Monitoring JUKNIS & Dashboard** — validasi Honor/Buku/Sarpras/Tahap I terhadap pagu, grafik alokasi per bulan & proporsi 9 Jenis Belanja resmi ARKAS, ringkasan kepatuhan dan RKA Gelondongan.
