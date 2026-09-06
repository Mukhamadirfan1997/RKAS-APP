# Changelog KARSA — Kertas Anggaran Sekolah

Semua perubahan penting aplikasi dicatat di sini.

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
