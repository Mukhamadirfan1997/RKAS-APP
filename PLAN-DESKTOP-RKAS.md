# PLAN — Aplikasi Desktop RKAS 2026 (Pengganti Excel)

**Tanggal:** 30 Agustus 2026
**Sumber:** `RKAS - KERTAS KERJA 2026 master V.2.TOYANING revisi harga edit.xlsm`

---

## 1. Ringkasan

Membangun aplikasi desktop lokal yang **menggantikan pemakaian Excel** untuk penyusunan
RKAS (Rencana Kegiatan dan Anggaran Sekolah) TA 2026 dari awal — lengkap dengan
**pemetaan kepatuhan JUKNIS** — sebagai **referensi sebelum mengisi aplikasi
resmi ARKAS** (Kemendikbudristek).

Keputusan yang sudah disepakati:

| Pertanyaan             | Jawaban                                                                                           |
| ---------------------- | ------------------------------------------------------------------------------------------------- |
| Tujuan                 | Menggantikan Excel sepenuhnya                                                                     |
| Teknologi              | **Laravel 12 + Tauri 2 (Desktop Windows)** — mengikuti pola aplikasi **SmartRKAS** yang sudah ada |
| Pengguna               | 1 komputer saja (laptop/PC sekolah)                                                               |
| Sistem operasi         | Windows saja                                                                                      |
| Input RINCIAN          | Manual (ketik langsung), impor hanya untuk data pendukung                                         |
| Fitur tahap awal       | Login & profil sekolah, backup otomatis + audit log                                               |
| Hubungan dgn SmartRKAS | **Aplikasi terpisah** — TIDAK digabung, agar RKAS referensi tidak bisa dimodifikasi menyeluruh    |

> **Mengapa terpisah dari SmartRKAS:** menggabungkan modul penyusunan ke SmartRKAS
> membuka celah RKAS yang sudah disahkan/dijadikan referensi bisa diubah secara
> keseluruhan. Dengan aplikasi terpisah, RKAS hasil penyusunan tersimpan terkunci
> (audit trail penuh) dan hanya dipakai sebagai referensi pengisian ARKAS.

### Seperti apa cara pakainya nanti

1. Instal `RKAS Desktop Setup.exe` sekali (seperti aplikasi SmartRKAS).
2. Buka dari menu Start / ikon desktop; login dengan akun sekolah.
3. Aplikasi berjalan offline di komputer sendiri, tanpa perlu internet.
4. Data tersimpan otomatis di folder data aplikasi + backup otomatis harian.

---

## 2. Hasil Analisis File Excel Saat Ini

Book kerja berisi **8 worksheet + makro VBA + 4 grafik**:

| Sheet       | Isi                                                                                                                     | Peran di aplikasi baru                                  |
| ----------- | ----------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| DASHBOARD   | Total Pagu BOS, kepatuhan JUKNIS (batas Honor Negeri/Swasta, batas minimal Buku, batas maksimal Pemeliharaan), 4 grafik | Modul Dashboard + Review Kepatuhan JUKNIS               |
| RINCIAN     | **Data utama**: 800 baris × 43 kolom; baris per kegiatan/barang + alokasi bulanan Tahap I & II                          | Modul Rincian / Input Anggaran                          |
| KEGIATAN    | Daftar kode kegiatan menurut 8 Standar Nasional Pendidikan (SNP)                                                        | Master Data Kegiatan                                    |
| REKENING    | Kode rekening belanja                                                                                                   | Master Data Rekening                                    |
| KODE BARANG | **Katalog kode barang (file terbesar ±37 MB)**                                                                          | Master Data Kode Barang (dengan pencarian/autocomplete) |
| PAGU SD     | Pagu/anggaran per tahap & per sekolah                                                                                   | Master Data Pagu                                        |
| PETUNJUK    | Petunjuk penggunaan & aturan                                                                                            | Halaman Bantuan/Petunjuk                                |
| BANTUAN     | Skema bantuan (BOS Reguler, dll.)                                                                                       | Master Data Bantuan                                     |

### Struktur kolom utama sheet RINCIAN

```
NO | KODE KEGIATAN | KODE REKENING | KODE BARANG | URAIAN KEGIATAN |
VOLUME | SATUAN | HARGA SATUAN | HARGA SATUAN ARKAS | JUMLAH | (per-bulan Jan–Des) |
TAHAP I | TAHAP II | KOREKSI | JUMLAH TAHAP I | JUMLAH TAHAP II | KONTROL | KODE | KATEGORI | HONOR
```

Perhitungan inti otomatis yang harus ditiru:

- `JUMLAH = VOLUME × HARGA SATUAN`
- Total per bulan, per Tahap I, per Tahap II, dan kolom KOREKSI
- Kolom KONTROL = pengecekan bahwa JUMLAH = alokasi bulanan (BENAR/SALAH)
- KATEGORI: BARJAS / MODAL / HONOR — bahan cek kepatuhan di Dashboard

---

## 3. Arsitektur Aplikasi

**Mengikuti pola SmartRKAS** (sudah terbukti berjalan di komputer ini), yaitu:
**Laravel 12 (backend + SQLite) dibungkus Tauri 2 (jendela desktop Windows)**.

```
RKAS Desktop .exe (Tauri 2)
        |
        +--> menjalankan server Laravel otomatis + scheduler backup
                    |
                    +--> Data: SQLite  (folder data aplikasi, dibuat otomatis)
                    |
                    +--> Backup otomatis harian (Spatie laravel-backup)
                    |
                    +--> Import/Export .xlsx  & cetak/PDF
```

Struktur folder aplikasi (pola sama dengan SmartRKAS):

```
RKAS-APP/
  app/            <- backend Laravel (models, controllers, services, imports)
  config/         <- pengaturan (termasuk config/juknis.php untuk batas kepatuhan)
  database/       <- migrasi + seeder (seperti pola SmartRKAS)
  resources/      <- tampilan (Blade + Alpine.js + Tailwind, seperti SmartRKAS)
  routes/         <- rute web
  src-tauri/      <- pembungkus desktop Tauri 2 (ikon, installer NSIS/MSI)
  vendor/, node_modules/  <- dependensi (composer/npm)
```

**Mengapa pilihan ini?**

- Sudah dipakai SmartRKAS di komputer ini → toolchain & cara build sudah terbukti.
- Installer `.exe` sekali pasang → tampil seperti aplikasi desktop (bukan browser tab).
- SQLite 1 file → mudah di-copy/dipindahkan/backup.
- Fitur kunci siap pakai dari ekosistem yang sama: backup otomatis, audit log,
  login, dan **pemetaan kepatuhan JUKNIS** (pola `KategoriJuknis` + `Monitoring` SmartRKAS).

> **Perbedaan dengan SmartRKAS:** aplikasi ini **tidak punya modul realisasi/BKU**.
> Fokus tunggal: **menyusun RKAS dari awal** (rencana belanja) + pemetaan kepatuhan
> JUKNIS, sebagai referensi pengisian ke ARKAS resmi.

---

## 4. Modul / Fitur yang Dibangun

### 4.1 Dashboard (meniru sheet DASHBOARD + "Review" ARKAS)

- Ringkasan: Total Pagu BOS, NPSN, nama sekolah, tahun anggaran.
- Indikator kepatuhan JUKNIS (Permendikdasmen No. 8/2026) dengan status **SESUAI / TIDAK SESUAI**:
    - Batas Honor Sekolah Negeri (maks 20% dari total pagu)
    - Batas Honor Sekolah Swasta (maks 40% dari total pagu)
    - Batas minimal anggaran Buku (min 10% dari total pagu — SD; jenjang lain menyesuaikan)
    - Batas maksimal anggaran Sarana & Prasarana (maks 20% dari total pagu, 3 kode kegiatan tertentu)
- **Grafik Proporsi (seperti fitur Review ARKAS)**:
    - Grafik Proporsi Belanja Honor vs Total Pagu
    - Grafik Proporsi Anggaran Buku
    - Grafik Proporsi Antar Jenis Belanja (barang/jasa, modal, honor)
    - Alokasi per bulan & proporsi Tahap I / Tahap II
- 4 grafik ringkasan (total barjas, modal, honor, alokasi per bulan).

### 4.2 Rincian Anggaran & Form Input (Meniru Presisi ARKAS 4.2.18 + SmartRKAS Live Search)

- **Lokasi Proyek Disepakati**: `D:\aplikasi sekolah\New folder\RKAS-APP`
- **Input 100% manual** (ketik langsung di aplikasi, bukan import dari Excel).
- **Desain Form Modal Pop-up "Detail Anggaran Kegiatan" (ARKAS 4.2.18)**:
    1. **Kegiatan (Program 8 SNP)**:
        - **Live Search Autocomplete** ala SmartRKAS (bukan dropdown `<select>` biasa).
        - Pengguna mengetik kode/nama (misal: `03.02` atau `Peningkatan Kompetensi`) -> popup hasil instan -> klik/enter -> tombol `×` untuk bersihkan pilihan.
    2. **Rekening Belanja**:
        - **Live Search Autocomplete** ala SmartRKAS.
        - Pengguna mengetik kode/nama (misal: `5.1.02` atau `Makanan dan Minuman`) -> popup hasil instan.
    3. **Uraian & Katalog Barang**:
        - **Pencarian Katalog Barang**: Bantuan pencarian ke puluhan ribu katalog untuk auto-fill nama barang, satuan default, dan harga acuan.
        - **Uraian Bebas (Manual 100% Editable)**: Pengguna bebas mengedit teks uraian belanja (maks 500 karakter).
        - **Keterangan Khusus / Peruntukan Anggaran (Bebas Ketik)**: Field khusus untuk menandai peruntukan spesifik (misal: _Pemeliharaan Ruang Kelas 1_, _Pengecatan Plafon Ruang Guru_, _Konsumsi Rapat Evaluasi Semester 1_) sehingga pengguna dan dinas tahu pasti kegunaannya meskipun menggunakan kode rekening yang sama untuk pos belanja lain.
    4. **Harga Satuan yang Dianggarkan**:
        - Input nominal Rupiah dengan formatting otomatis ribuan.
    5. **Alokasi 12 Bulan ("Dianggarkan untuk Bulan")**:
        - Grid kartu interaktif 12 bulan (Januari s.d. Desember).
        - Setiap bulan terdapat input `Volume` dan `Satuan` (misal: `13` `dus`).
        - Subtotal per bulan terhitung secara otomatis (_live real-time_): `Volume × Harga Satuan`.
        - **Total Anggaran Keseluruhan** di header modal langsung terakumulasi secara otomatis.
- **Hitung otomatis di Sistem**:
    - `JUMLAH = Volume Total × Harga Satuan`
    - Pembagian otomatis ke **Tahap I** (Januari–Juni) dan **Tahap II** (Juli–Desember).
    - Kolom **KONTROL** otomatis cek konsistensi alokasi.
    - Penandaan jika alokasi Tahap I belum mencapai ambang batas minimal 50% JUKNIS.
- Subtotal per kegiatan (group), dan total keseluruhan.
- Cetak Rincian dalam format resmi kertas kerja (A4) ✓

### 4.3 Master Data & Pengaturan Pagu Sekolah (Acuan Utama RKAS)

- **Pengaturan Profil Sekolah**: NPSN, Nama Sekolah, Kepala Sekolah (NIP), Bendahara (NIP), Alamat & Kontak untuk kop berkas kertas kerja.
- **Pengaturan Pagu 1 Tahun & Per Tahap (Wajib sebagai Acuan Anggaran)**:
    - **Pagu Total 1 Tahun**: Batas maksimal anggaran yang menentukan perhitungan _Sudah Dianggarkan_ vs _Sisa Pagu Tersedia_ di header aplikasi.
    - **Pagu Tahap I (Januari–Juni)**: Batas alokasi tahap pertama (default otomatis 50% dari total pagu sesuai JUKNIS, dapat disesuaikan kebutuhan).
    - **Pagu Tahap II (Juli–Desember)**: Batas alokasi tahap kedua (sisa alokasi pagu tahunan).
    - **Fungsi Acuan Pagu**: Menjadi acuan validasi agar alokasi Tahap I mencapai $\ge 50\%$, mencegah over-budget, serta menjadi penyebut utama persentase kepatuhan JUKNIS (batas belanja Honor, Buku, dan Sarpras).
- **Tahun Anggaran**: Pengaktifan tahun berjalan (2026) dan pengarsipan tahun lampau.
- **Master Kegiatan/Program ARKAS**: Kode kegiatan menurut 8 SNP (level program dan sub-kegiatan).
- **Master Kode Rekening + Pemetaan Kategori JUKNIS**: Pemetaan kode rekening ke kategori BARJAS, MODAL, dan HONOR (pola `KategoriJuknis` SmartRKAS).
- **Katalog Kode Barang**: Database puluhan ribu item barang/jasa dengan satuan default dan harga acuan.
- **Daftar Bantuan / Sumber Dana**: Klasifikasi BOSP Reguler, BOS Kinerja, BOSDA, dll.

### 4.4 Import dari Excel (KHUSUS data pendukung — bukan RINCIAN)

- Import hanya untuk data pendukung: **KEGIATAN, REKENING, KODE BARANG, PAGU, BANTUAN** dari file .xlsm/.xlsx (pola import SmartRKAS + batas ukuran upload + audit log).
- **RINCIAN (baris RKAS) TIDAK diimpor** — diisi manual langsung di aplikasi (sesuai keinginan pengguna).
- Pengecekan hasil import (jumlah baris, jumlah data) sebelum disimpan.

### 4.5 Laporan / Export

- Cetak Rincian dalam format **kertas kerja (A4/PDF)** sebagai referensi pengisian ARKAS.
- Export **Excel (.xlsx)** per kegiatan / rekap per bulan & tahap.
- Export/Import **backup** penuh ke satu file (`backup-rkas-tanggal.zip`).

### 4.6 Petunjuk & Bantuan (meniru sheet PETUNJUK)

- Panduan langkah demi langkah di dalam aplikasi.

### 4.7 Review & Pemetaan Kepatuhan JUKNIS (meniru "Review" ARKAS + Monitoring JUKNIS SmartRKAS)

Tujuan proyek: menyusun RKAS dari awal sebagai **referensi sebelum mengisi ARKAS**.
Fitur ini memetakan setiap baris anggaran ke komponen JUKNIS dan menandai kepatuhannya.
Implementasi mengikuti **dua pola dari SmartRKAS** sekaligus:

- **Kategori JUKNIS dapat diatur** (pola `KategoriJuknis` + `MonitoringJuknisController`):
  pengguna membuat kategori (mis. Honor, Buku, Sarpras) dengan arah `maksimal`/`minimal`
  dan batas %, lalu memetakannya ke kode rekening. Halaman Monitoring menghitung
  proporsi terhadap Total Pagu → status `sesuai`/`melebihi`/`kurang` dengan toggle
  basis **rencana** (karena app ini khusus menyusun, tanpa realisasi).
- **Validator logika ARKAS** (pola `JuknisValidator` + `config/juknis.php`):
    - Honor: maks **20%** (negeri) / **40%** (swasta) dari total pagu
      (kode kegiatan `07.12.01.`–`07.12.04.` + kode rekening honor)
    - Buku: minimal **10%** dari total pagu (sekolah khusus ≥2026 = **5%**;
      kode kegiatan `03.02.02.`, `05.02.02.`–`05.02.05.`)
    - Sarana & Prasarana: maks **20%** dari total pagu (kode kegiatan `05.08.*`)
- **Perhatian Tahap I**: minimal **50%** pagu dianggarkan Januari–Juni (JUKNIS BOSP).
- Grafik proporsi (seperti halaman Review ARKAS) agar jelas posisi anggaran
  terhadap batas yang ditetapkan — sebelum diajukan ke Dinas.
- Daftar "belum termapping"/"tidak terkategori" sebagai checklist revisi.
- Aplikasi ini **hanya alat bantu menyusun** — pengisian/penyerahan final tetap
  dilakukan di ARKAS (Menu Penganggaran → input kegiatan → pengesahan ke Dinas).

**Referensi yang dipakai:**

- ARKAS 4.2.18 + Buku Panduan (arkas.kemendikdasmen.go.id, Mei 2026)
- Implementasi JUKNIS dalam Penggunaan Dana BOSP Reguler
  (Pusat Informasi Rumah Pendidikan, 31 Maret 2026)
- Permendikdasmen No. 8/2026 (JUKNIS Pengelolaan Dana BOSP)
- **SmartRKAS** (pola `config/juknis.php`, `JuknisValidator`, `MonitoringJuknisController`)

### 4.8 Keamanan, Profil, Backup & Audit (fitur awal — disepakati)

- **Login & akun**: login/register, ganti email/password (pola Breeze/Auth SmartRKAS).
- **Profil Sekolah**: data identitas untuk kop laporan.
- **Backup otomatis**: harian + cadangan manual, restore via file `.zip` (Spatie laravel-backup).
- **Riwayat Aktivitas (Audit Log)**: semua tambah/ubah/hapus/import dicatat siapa & kapan — bagian dari menjaga RKAS referensi tidak diubah sembarangan.
- **Data terkunci dari SmartRKAS**: tidak ada relasi/sinkronisasi dengan SmartRKAS.

---

## 5. Struktur Data (Skema Database)

Mengikuti pola penamaan SmartRKAS (Laravel migrasi + SQLite).

Tabel utama `rkAS_item` (satu baris = satu baris di sheet RINCIAN):

```
id
tahun_anggaran_id         -> tahun_anggaran
program_id                -> master_program (kode kegiatan ARKAS)
kode_rekening_id          -> master_kode_rekening
kode_barang_id            -> kode_barang (katalog)
uraian                    (maks 500 karakter, seperti ARKAS)
volume
satuan
harga_satuan
harga_satuan_arkas
jumlah                    (dihitung otomatis = Volume × Harga)
no_urut                   (penomoran baris)
```

Alokasi bulanan dipisah ke tabel `rkas_item_bulan` (pola SmartRKAS):

```
rkas_item_id
bulan        (1..12, menentukan Tahap I/II)
jumlah
```

Dengan begitu:

- `jumlah_tahap1` = Σ bulan 1–6, `jumlah_tahap2` = Σ bulan 7–12, `koreksi` = beda
  terhadap `volume × harga`, `kontrol` dihitung otomatis (BENAR/SALAH).
- KATEGORI (BARJAS/MODAL/HONOR) & label komponen JUKNIS diturunkan dari kode rekening.

Tabel penunjang (pola SmartRKAS):

- `users`, `sessions` — login & autentikasi
- `pengaturan_sekolah` — identitas (NPSN, nama, alamat) untuk kop laporan
- `tahun_anggaran` — tahun berjalan + riwayat
- `sumber_dana`, `jenis_belanja` — klasifikasi dana & belanja
- `master_program` — kode kegiatan ARKAS menurut SNP (level 2/3)
- `master_kode_rekening` — kode rekening belanja
- `kategori_juknis` — nama, arah (maksimal/minimal), batas_persen
- `kode_rekening_kategori_juknis` — pivot pemetaan rekening → kategori JUKNIS
- `kode_barang` — katalog barang (import dari Excel, ± puluhan ribu baris)
- `pagu` — pagu per sumber dana & tahap
- `bantuan` — skema bantuan
- `audit_logs` — riwayat aktivitas (tambah/ubah/hapus/import)
- backup disimpan sebagai file `.zip` (Spatie laravel-backup), bukan tabel

---

## 6. Tahapan Pengerjaan (Roadmap)

Urut dari yang paling penting dulu, biar langsung bisa dipakai bertahap:

| Fase                                     | Isi                                                                                                                                                  | Hasil di tangan pengguna                     |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| **Fase 0 — Fondasi (Laravel + Tauri)**   | Setup proyek Laravel 12 + Tauri 2 (meniru pola SmartRKAS), migrasi DB, **login & profil sekolah**, **backup otomatis + audit log**                   | Aplikasi desktop terbuka, bisa login         |
| **Fase 1 — Master Data & Import**        | Tahun Anggaran, Master Rekening/Kegiatan, **Kategori Juknis + pemetaan rekening**, import pendukung (KEGIATAN, REKENING, KODE BARANG, PAGU, BANTUAN) | Master data terisi dari Excel lama           |
| **Fase 2 — Penyusunan RKAS**             | Input manual baris RKAS (pola `rkas_item` + `rkas_item_bulan`), auto-hitung (Volume×Harga, Tahap I/II, KOREKSI, KONTROL), sisip uraian               | Bisa menyusun RKAS dari awal di aplikasi     |
| **Fase 3 — Monitoring Kepatuhan JUKNIS** | Halaman Monitoring (kategori JUKNIS + `JuknisValidator` logika ARKAS) + grafik proporsi + status SESUAI/MELEBIHI/KURANG                              | Pemetaan kepatuhan siap jadi referensi ARKAS |
| **Fase 4 — Laporan & Export**            | Cetak/PDF kertas kerja, export Excel, backup & restore manual                                                                                        | Dokumen siap menjadi acuan pengisian ARKAS   |
| **Fase 5 — Build & Peluncuran**          | Installer `.exe` (Tauri NSIS/MSI), pengujian penuh, panduan pemakaian 1 halaman                                                                      | Aplikasi siap dipakai sehari-hari            |

> Catatan: Fase 0–2 sudah bisa menggantikan fungsi utama Excel (menyusun RKAS dari awal).
> Fase 3–4 melengkapi pemetaan kepatuhan JUKNIS dan laporan.

---

## 7. Backup & Keamanan Data

- Simpan otomatis di database SQLite (folder data aplikasi, pola SmartRKAS).
- Backup otomatis harian (Spatie laravel-backup) — versi terakhir disimpan di folder
  backup aplikasi; kadaluarsa dibersihkan terjadwal.
- Tombol "Cadangkan Sekarang" (manual) + unduh `.zip`; restore dari file `.zip`.
- **Audit log**: semua tambah/ubah/hapus/import/override dicatat (siapa, kapan, apa).
- File backup dapat disalin ke flashdisk/drive lain.

---

## 8. Hasil Akhir (Definition of Done)

Aplikasi dianggap selesai bila:

1. Data RKAS 2026 **dapat dibuat penuh di aplikasi** (tanpa menyentuh Excel).
2. Total/jumlah per bulan/Tahap I-II/KOREKSI/KONTROL **akurat** (diuji = hasil Excel lama).
3. **Pemetaan kepatuhan JUKNIS** muncul per kategori + status SESUAI/MELEBIHI/KURANG
   (honor/buku/sarpras + Tahap I min 50%).
4. Laporan kertas kerja **dapat dicetak/export Excel** sebagai referensi ARKAS.
5. Login & profil sekolah berfungsi; backup otomatis + audit log berjalan.
6. **Tidak terhubung dengan SmartRKAS** — RKAS referensi tidak bisa diubah melalui app lain.
7. Berjalan sebagai aplikasi desktop (installer `.exe`) di komputer sekolah.

---

## 9. Risiko & Keputusan yang Perlu Dikonfirmasi Nanti

1. **Format input ARKAS** — struktur kolom dibuat serupa ARKAS 4.2.18 agar hasil
   draft mudah disalin ke aplikasi resmi; format export kertas kerja mengikuti
   ketentuan Dinas _(perlu contoh berkas yang sering diunduh dari Dinas setempat)._
2. **Ambang batas JUKNIS 2026** — angka (honor/buku/sarpras) dibuat **dapat diatur**
   karena sekolah bisa menggunakan sumber dana non-Reguler dengan aturan berbeda.
3. **Katalog KODE BARANG** (37 MB) — perlu dipastikan versi katalog 2026 yang benar
   saat import pertama.
4. **Batas harga** — harga satuan mengikuti ARKAS/toko; aplikasi hanya mencatat,
   tidak menentukan.
5. **Keamanan RKAS referensi** — aplikasi sengaja **terpisah dari SmartRKAS** agar
   tidak ada celah mengubah RKAS secara keseluruhan dari aplikasi lain; audit log +
   backup menjadi jaring pengaman. _(Keputusan dipakai mulai versi plan ini.)_

---

## 10. Estimasi Waktu

| Fase                                             | Estimasi               |
| ------------------------------------------------ | ---------------------- |
| Fase 0 (Laravel + Tauri + login/backup/audit)    | 2–3 hari kerja         |
| Fase 1 (master data + import + kategori JUKNIS)  | 2–3 hari kerja         |
| Fase 2 (penyusunan RKAS + auto-hitung)           | 3–4 hari kerja         |
| Fase 3 (monitoring kepatuhan JUKNIS + grafik)    | 1–2 hari kerja         |
| Fase 4–5 (laporan + build installer + pengujian) | 2–3 hari kerja         |
| **Total**                                        | **± 10–15 hari kerja** |

---

_Dokumen ini adalah rencana (plan). Detail teknis penuh akan menyusul di fase implementasi._
