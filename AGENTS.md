## SOP Wajib — Baca Sebelum Mulai Kerja

1. **Prinsip Bukti Nyata (Ground Truth)**:
   - Jangan pernah mempercayai klaim "berhasil", "view sudah tampil", atau "test hijau" tanpa verifikasi langsung (cek isi file, uji query, cek log error, dan jalankan perintah test).
   - Untuk route dan view Blade: selalu verifikasi dengan `php artisan route:list` dan uji render/request nyata.

2. **Single Source of Truth**:
   - Berkas `PLAN-DESKTOP-RKAS.md` dan `AGENTS.md` adalah dokumen acuan tunggal (*Single Source of Truth*) arsitektur dan aturan bisnis aplikasi ini.
   - Semua asisten AI atau developer yang melanjutkan pekerjaan WAJIB membaca file ini terlebih dahulu sebelum mengubah kode.

3. **Integritas Format Data & ARKAS 4.2.18**:
   - Desain tampilan utama dan form input modal **wajib meniru secara presisi antarmuka ARKAS 4.2.18**.
   - Input pencarian kegiatan, rekening, dan uraian barang **menggunakan Live Search Autocomplete (ala SmartRKAS)**, BUKAN dropdown `<select>` biasa.
   - Field `uraian` dan `keterangan_kustom` **100% wajib dapat diedit dan diketik bebas secara manual** oleh pengguna (untuk membedakan peruntukan belanja seperti *Pemeliharaan Ruang Kelas 1* walau kode rekeningnya sama).

4. **Isolasi Perubahan & Validasi Bertahap**:
   - Buat perubahan per modul secara terisolasi.
   - Setelah membuat view atau controller baru, segera uji routing, responsivitas Alpine.js, dan penyimpanan ke database SQLite.

---

# RKAS 2026 — Catatan Pengembangan & SOP Proyek

Aplikasi desktop lokal pengganti Excel untuk menyusun RKAS (Rencana Kegiatan dan Anggaran Sekolah) TA 2026 dari awal secara mandiri, lengkap dengan pemetaan kepatuhan JUKNIS BOSP (Permendikdasmen No. 8/2026), sebagai referensi akurat sebelum mengisi aplikasi resmi ARKAS Kemendikbudristek.

## 1. Identitas Proyek & Lokasi
- **Lokasi Folder Proyek**: `D:\aplikasi sekolah\New folder\RKAS-APP`
- **Tech Stack**: Laravel 12 + SQLite + Tailwind CSS v4 + Alpine.js 3 + Tauri 2 (Desktop Windows)
- **Basis Pengguna**: 1 Komputer / Sekolah (Offline Desktop, tanpa dependensi server eksternal)

## 2. Struktur Database (SQLite)
Skema database yang telah aktif dan dimigrasikan:

1. **`pengaturan_sekolah`**:
   - `npsn`, `nama_sekolah`, `nama_kepala_sekolah`, `nip_kepala_sekolah`, `nama_bendahara`, `nip_bendahara`, `alamat`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`.
2. **`tahun_anggaran`**:
   - `tahun` (2026), `sumber_dana` (BOSP REGULER), `pagu_total`, `pagu_tahap1`, `pagu_tahap2`, `is_active`, `status_pengesahan` (Draft/Disahkan/Pergeseran).
   - **Aturan Pagu**: `pagu_total` menjadi penyebut utama kepatuhan JUKNIS; `pagu_tahap1` default 50% untuk cek batas minimal serapan tahap 1; `pagu_tahap2` sisa pagu.
3. **`master_program`**:
   - `kode` (misal `03.02.01`), `nama` (*Peningkatan Kompetensi Guru*), `standar_snp` (*Standar Pendidik dan Tenaga Kependidikan*).
4. **`master_kode_rekening`**:
   - `kode` (misal `5.1.02.01.01.0052`), `nama` (*Belanja Makanan dan Minuman Rapat*), `kategori_belanja` (*BARJAS / MODAL / HONOR*).
5. **`kode_barang`**:
   - `kode` (misal `KB-001`), `nama` (*Nasi Dus & Lauk Pauk (biasa)-Hidangan rapat/tamu*), `satuan_default` (*dus*), `harga_acuan` (*30.000*).
6. **`rkas_item`**:
   - `id`, `tahun_anggaran_id`, `master_program_id`, `master_kode_rekening_id`, `kode_barang_id`, `uraian` (maks 500 karakter), `keterangan_kustom` (keterangan manual spesifik), `volume`, `satuan`, `harga_satuan`, `harga_satuan_arkas`, `jumlah`, `no_urut`.
7. **`rkas_item_bulan`**:
   - `rkas_item_id`, `bulan` (1..12), `volume`, `satuan`, `jumlah` (`volume * harga_satuan`). Unique: `[rkas_item_id, bulan]`.

## 3. Fitur Form Input Modal "Detail Anggaran Kegiatan" (ARKAS 4.2.18)
- **Bagian Atas**:
  - Live Search Picker Kegiatan (autocomplete pencarian 8 SNP).
  - Live Search Picker Rekening Belanja.
- **Bagian Tengah**:
  - Helper pencarian Katalog Barang untuk auto-fill nama barang, satuan, dan estimasi harga.
  - Field `Uraian` (teks editable bebas, maks 500 karakter).
  - Field `Keterangan Khusus / Peruntukan Anggaran` (ketik bebas untuk membedakan pos belanja, misal: *Pemeliharaan Ruang Kelas 1*).
  - Field `Harga Satuan yang Dianggarkan` (format Rupiah dinamis).
- **Bagian Alokasi Bulanan ("Dianggarkan untuk Bulan")**:
  - Header: Subtitle kiri `Dianggarkan untuk Bulan`, header kanan `Total Anggaran: Rp [Kalkulasi Otomatis]`.
  - Grid 12 Kartu Bulan (Januari s.d. Desember) dengan input `Volume` dan `Satuan`.
  - Live subtotal per bulan = `Volume * Harga Satuan`.
  - Total Anggaran Keseluruhan terhitung real-time via computed Alpine.js.
- **Bagian Footer**:
  - Tombol `Tutup` dan `Simpan ke Anggaran`.

## 4. Daftar Endpoint API & Route
- `GET /` & `GET /rkas` : Halaman lembar kerja utama RKAS 2026.
- `POST /rkas/store` : Simpan item anggaran baru beserta alokasi 12 bulan.
- `GET /rkas/{id}/json` : Ambil data item anggaran dan alokasi 12 bulan untuk modal edit.
- `POST /rkas/{id}/update` : Perbarui item anggaran dan alokasi 12 bulan.
- `DELETE /rkas/{id}/delete` : Hapus item anggaran.
- `GET /api/search/kegiatan` : API autocomplete live search kegiatan.
- `GET /api/search/rekening` : API autocomplete live search kode rekening.
- `GET /api/search/barang` : API autocomplete live search katalog barang.
- `GET /pengaturan` : Halaman pengaturan profil sekolah & pagu anggaran.
- `POST /pengaturan/update` : Simpan data profil sekolah dan pagu 1 tahun / tahap.

---

# Log Pengembangan & Sesi Kerja

## Sesi 30 Agu 2026 — Inisialisasi Proyek, Database, Live Search API & Desain Modal ARKAS
- **Tujuan**:
  1. Menyiapkan fondasi Laravel 12 di folder `D:\aplikasi sekolah\New folder\RKAS-APP`.
  2. Membangun skema database lengkap untuk pengelolaan RKAS 2026, Profil Sekolah, Pagu 1 Tahun & Tahap I-II, serta Master SNP/Rekening/Barang.
  3. Membangun controller Live Search Autocomplete ala SmartRKAS (`RkasSearchController`).
  4. Merancang dan mendokumentasikan form input modal ARKAS 4.2.18 dengan fleksibilitas uraian manual bebas.
- **Pencapaian**:
  - Proyek Laravel 12 berhasil diinstal, SQLite database termigrasi dan terseed.
  - Dependensi Excel (`maatwebsite/excel`), PDF (`barryvdh/laravel-dompdf`), Tailwind CSS v4, dan Alpine.js 3 terpasang.
  - Controller `RkasSearchController`, `RkasController`, dan Model-Model Eloquent selesai dibuat.
  - Aturan Pagu 1 Tahun & Per Tahap serta form Uraian Manual tercatat resmi di `PLAN-DESKTOP-RKAS.md`.

## Sesi 30 Agu 2026 (Lanjutan) — Dashboard, Monitoring JUKNIS, Login, Laporan PDF & Tauri 2
- **Pencapaian**:
  - `PengaturanController` + halaman `pengaturan/index` (profil sekolah & pagu), migrasi `audit_logs`, `kategori_juknis`, `kode_rekening_kategori_juknis`; `AuditLog`, `KategoriJuknis`, `KodeRekeningKategoriJuknis` model; trait `LogsActivity` terpasang di model utama.
  - `config/juknis.php` (Honor ≤20%, Buku ≥10%, Sarpras ≤20%, Tahap I minimal 50%) + `JuknisValidator` + `JuknisSeeder`.
  - `DashboardController` + grafik; `MonitoringJuknisController` + halaman monitoring; `MasterDataController` + CRUD master + import Excel; export PDF kertas kerja (`barryvdh/laravel-dompdf`).
  - Auth manual: `AuthController` + `auth/login.blade.php`, semua route di belakang middleware `auth`, audit log untuk login.
  - Semua route terverifikasi `php artisan route:list`; test hijau: **12 tests / 52 assertions** (PageRenderTest + RkasFlowTest); `pint` dijalankan.
  - **Tauri 2**: `npx tauri init`, `lib.rs` menspawn `php artisan serve --port=9200` (mode produksi), window memuat `http://127.0.0.1:9200`. `cargo check` hijau. Jalankan via `npm run tauri:dev`.
  - **Git**: repo diinisialisasi, commit awal `163c748` dibuat.