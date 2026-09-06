# Changelog KARSA — Kertas Anggaran Sekolah

Semua perubahan penting aplikasi dicatat di sini.

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
