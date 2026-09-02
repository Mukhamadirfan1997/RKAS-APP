# Dokumentasi KARSA 2026 — Struktur Database & Fitur

> KARSA 2026 (Kertas Anggaran Sekolah) — Aplikasi desktop offline pengganti Excel untuk penyusunan RKAS TA 2026.
> Tech stack: Laravel 12 + SQLite + Tailwind CSS v4 + Alpine.js 3 + Tauri 2 (Windows).

**Lokasi proyek:** `D:\aplikasi sekolah\New folder\RKAS-APP`  
**Database:** `database/database.sqlite` (SQLite 3.39.2)  
**Tanggal dokumen:** 02 Sep 2026

---

## Daftar Isi
1. [Ringkasan Arsitektur](#1-ringkasan-arsitektur)
2. [Struktur Database (20 Tabel)](#2-struktur-database-20-tabel)
3. [Diagram Relasi (ERD Teks)](#3-diagram-relasi-erd-teks)
4. [Detail Per Tabel](#4-detail-per-tabel)
5. [Semantik KOREKSI & KONTROL](#5-semantik-koreksi--kontrol)
6. [Logika JUKNIS (Permendikdasmen No. 8/2026)](#6-logika-juknis-permendikdasmen-no-82026)
7. [Fitur Aplikasi](#7-fitur-aplikasi)
8. [Daftar Route & Endpoint](#8-daftar-route--endpoint)
9. [Alur Data Utama](#9-alur-data-utama)
10. [Referensi File Sumber](#10-referensi-file-sumber)

---

## 1. Ringkasan Arsitektur

```
KARSA Desktop .exe (Tauri 2)  — src-tauri/lib.rs:75 spawns php artisan serve --port=9200
        |
        +--> Laravel 12 (routes/web.php)
                |
                +--> SQLite 1-file (database/database.sqlite)
                +--> Backup .zip via VACUUM INTO (BackupController.php)
                +--> Import/Export .xlsx (maatwebsite/excel) & PDF (barryvdh/laravel-dompdf)
                +--> Alpine.js modal + Tailwind v4 (resources/views/*)
```

- **Offline 1 komputer/sekolah**, tanpa server eksternal.
- Auth manual (cookie), semua route di belakang `middleware('auth')` kecuali `/login`.
- Audit trail di `audit_logs` via trait `LogsActivity`.

---

## 2. Struktur Database (20 Tabel)

Hasil `php artisan migrate:status` (9 migrasi, Batch 1-7) + `php artisan db:show` (20 tabel):

| # | Tabel | Sumber Migrasi | Fungsi |
|---|-------|----------------|--------|
| 1 | `users` | `0001_01_01_000000` | Akun login |
| 2 | `password_reset_tokens` | `0001_01_01_000000` | Token reset password |
| 3 | `sessions` | `0001_01_01_000000` | Sesi login |
| 4 | `cache` / `cache_locks` | `0001_01_01_000001` | Cache framework |
| 5 | `jobs` / `job_batches` / `failed_jobs` | `0001_01_01_000002` | Queue |
| 6 | `migrations` | sistem | Riwayat migrasi |
| 7 | `pengaturan_sekolah` | `2026_08_30_000001` + `2026_08_31_143738` | Profil sekolah + status negeri/swasta |
| 8 | `tahun_anggaran` | `2026_08_30_000001` | Pagu & tahun aktif |
| 9 | `master_program` | `2026_08_30_000001` + `2026_08_30_150836` | Kegiatan 8 SNP (hierarki) |
| 10 | `master_kode_rekening` | `2026_08_30_000001` + `2026_08_30_150836` | Rekening belanja + jenis_belanja |
| 11 | `kode_barang` | `2026_08_30_000001` + `2026_08_31_000001` | Katalog 81.062 barang ARKAS |
| 12 | `jenis_belanja` | `2026_08_30_150836` | 9 jenis resmi ARKAS |
| 13 | `rkas_item` | `2026_08_30_000001` + `2026_08_30_000002` | Baris anggaran induk |
| 14 | `rkas_item_bulan` | `2026_08_30_000001` | Alokasi 12 bulan per item |
| 15 | `kategori_juknis` | `2026_08_30_000002` | Kategori JUKNIS custom |
| 16 | `kode_rekening_kategori_juknis` | `2026_08_30_000002` | Pivot rekening → kategori JUKNIS |
| 17 | `audit_logs` | `2026_08_30_000002` | Riwayat aktivitas |

---

## 3. Diagram Relasi (ERD Teks)

```
pengaturan_sekolah (1) ──< status_sekolah menentukan batas honor (JuknisValidator)

tahun_anggaran (1) ──< rkas_item (N)
    |
    +-- pagu_total / pagu_tahap1 / pagu_tahap2  (penyebut JUKNIS)

jenis_belanja (1) ──< master_kode_rekening (N)
                            |
master_program (1) ──< rkas_item (N) >── master_kode_rekening (1)
        |                    |
        | self parent_id     +── kode_barang (1)  (nullable, nullOnDelete)
        |                    |
        |                    +── rkas_item_bulan (12)  unique[rkas_item_id, bulan]
        |
        +-- program / sub_program / level / parent_id (hierarki teks, parent_id null saat ini)

kategori_juknis (1) ──< kode_rekening_kategori_juknis (N) >── master_kode_rekening (1)

users (1) ──< audit_logs (N)  (polymorphic auditable_type/id)

rkas_item ── computed: jumlah (=volume*harga), koreksi, jumlah_koreksi (=jumlah+koreksi),
             kontrol (=OK/SELISIH), tahap1/tahap2 (sum alokasiBulan)
```

---

## 4. Detail Per Tabel

### 4.1 `pengaturan_sekolah` — `database/migrations/2026_08_30_000001_create_rkas_system_tables.php:15`
Satu baris identitas sekolah untuk kop laporan.

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `npsn` | varchar(20) nullable |  |
| `nama_sekolah` | varchar(150) default `SD NEGERI TOYANING 1` |  |
| `nama_kepala_sekolah` | varchar(150) nullable |  |
| `nip_kepala_sekolah` | varchar(50) nullable |  |
| `nama_bendahara` | varchar(150) nullable |  |
| `status_sekolah` | varchar(20) default `negeri` | `negeri`/`swasta` — penentu batas honor (`2026_08_31_143738`) |
| `nip_bendahara` | varchar(50) nullable |  |
| `alamat` | varchar(255) nullable |  |
| `desa_kelurahan` | varchar(100) nullable |  |
| `kecamatan` | varchar(100) nullable |  |
| `kabupaten_kota` | varchar(100) nullable |  |
| `provinsi` | varchar(100) nullable |  |
| `created_at`/`updated_at` | timestamps |  |

Model: `app/Models/PengaturanSekolah.php:8` (trait `LogsActivity`).

### 4.2 `tahun_anggaran` — `database/migrations/2026_08_30_000001_create_rkas_system_tables.php:32`
| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `tahun` | year default 2026 |  |
| `sumber_dana` | varchar(50) default `BOSP REGULER` |  |
| `pagu_total` | decimal(15,2) default 180320000 | Penyebut JUKNIS |
| `pagu_tahap1` | decimal(15,2) default 90160000 | Default 50% total |
| `pagu_tahap2` | decimal(15,2) default 90160000 | Sisa |
| `is_active` | boolean default true | Tahun aktif |
| `status_pengesahan` | varchar(50) default `Draft` | Draft/Disahkan/Pergeseran |
| `timestamps` |  |  |

### 4.3 `master_program` — `...+150836_align_master_with_smartrkas.php:37`
Kegiatan 8 SNP. Fixture `database/seeders/data/smartrkas-master.json` = 139 baris level 4.

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `kode` | varchar(50) indexed | mis. `03.02.01` |
| `program` | varchar(150) nullable | Teks program (SmartRKAS) |
| `sub_program` | varchar(150) nullable | Teks sub-program |
| `parent_id` | FK nullable → `master_program.id` nullOnDelete | Hierarki (saat ini null) |
| `level` | tinyint default 1 |  |
| `nama` | varchar(255) | Nama kegiatan |
| `standar_snp` | varchar(150) nullable |  |
| `timestamps` |  |  |

Model: `app/Models/MasterProgram.php`.

### 4.4 `jenis_belanja` — `2026_08_30_150836:18`
9 jenis resmi ARKAS (pengganti BARJAS/MODAL/HONOR kasar).

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `nama` | varchar(100) unique | mis. `Belanja Jasa`, `Belanja Modal Buku`, `Belanja Jasa Pemeliharaan`, dll. (9) |
| `timestamps` |  |  |

Model: `app/Models/JenisBelanja.php`. Resolver: `app/Services/JenisBelanjaResolver.php`.

### 4.5 `master_kode_rekening` — `...+000001:54` + `+150836:25`
276 rekening di fixture.

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `kode` | varchar(50) indexed | mis. `5.1.02.01.01.0052` |
| `nama` | varchar(255) |  |
| `kategori_belanja` | varchar(50) default `BARJAS` | Legacy (BARJAS/MODAL/HONOR) |
| `jenis_belanja_id` | FK nullable → `jenis_belanja.id` nullOnDelete | Klasifikasi resmi |
| `timestamps` |  |  |

Relasi: `MasterKodeRekening belongsTo JenisBelanja`.

### 4.6 `kode_barang` — `...+000001:63` + `2026_08_31_000001:11`
Katalog resmi ARKAS. Produksi 81.062 baris (via `ArkasKodeBarangSeeder` manual), test 9 baris.

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `kode` | varchar(100) nullable indexed | Kode lama KB-001 |
| `nama` | varchar(255) indexed | Nama barang |
| `satuan_default` | varchar(50) nullable | dus/set/dll |
| `harga_acuan` | decimal(15,2) default 0 | Harga acuan ARKAS |
| `id_barang_arkas` | varchar(50) nullable indexed | ID ARKAS (unik sumber) |
| `kode_rekening` | varchar(50) nullable indexed | Kode rekening sumber |
| `harga_min` | decimal(15,2) default 0 | SSH batas bawah |
| `harga_max` | decimal(15,2) default 0 | SSH batas atas |
| `kode_belanja` | varchar(20) nullable |  |
| `kategori` | varchar(100) nullable |  |
| `timestamps` |  |  |

Model: `app/Models/KodeBarang.php`.

### 4.7 `rkas_item` — `...+000001:73` + `...+000002:15`
Satu baris = satu baris RINCIAN Excel. Penomoran `no_urut` global (max+1).

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `tahun_anggaran_id` | FK → `tahun_anggaran.id` cascade |  |
| `master_program_id` | FK nullable → `master_program.id` nullOnDelete | Kegiatan |
| `master_kode_rekening_id` | FK nullable → `master_kode_rekening.id` nullOnDelete | Rekening |
| `kode_barang_id` | FK nullable → `kode_barang.id` nullOnDelete | Katalog (helper) |
| `uraian` | varchar(500) | **Editable bebas** |
| `keterangan_kustom` | varchar(255) nullable | Badge amber 📌, bebas ketik |
| `volume` | decimal(12,2) default 0 | Volume total (Σ bulan) |
| `satuan` | varchar(50) default `bulan` |  |
| `harga_satuan` | decimal(15,2) default 0 | Harga dianggarkan |
| `harga_satuan_arkas` | decimal(15,2) default 0 | Harga acuan ARKAS |
| `jumlah` | decimal(15,2) default 0 | `= volume × harga_satuan` (auto) |
| `koreksi` | decimal(15,2) default 0 | Penyesuaian manual ± (`2026_08_30_000002`) |
| `no_urut` | int default 1 | Global, tampil hybrid `#no_urut` |
| `timestamps` |  |  |

Accessor Model `app/Models/RkasItem.php:40`:
- `tahap1` / `tahap2` — sum `alokasiBulan` Jan-Jun / Jul-Des
- `jumlah_koreksi` — `round(jumlah + koreksi, 2)` — angka terpakai
- `selisih_harga` — `harga_satuan - harga_satuan_arkas`
- `kontrol` — `SELISIH` bila `abs(selisih) > 0.009` else `OK`
- Relasi: `tahunAnggaran`, `program`, `kodeRekening`, `barang`, `alokasiBulan (ordered by bulan)`.

### 4.8 `rkas_item_bulan` — `...+000001:93`
Alokasi per bulan (Tahap I = 1-6 biru, Tahap II = 7-12 hijau).

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `rkas_item_id` | FK → `rkas_item.id` cascade |  |
| `bulan` | tinyint 1..12 |  |
| `volume` | decimal(12,2) default 0 |  |
| `satuan` | varchar(50) nullable |  |
| `jumlah` | decimal(15,2) default 0 | `= volume × harga_satuan` |
| `timestamps` |  |  |
| Unique | `[rkas_item_id, bulan]` |  |

Model: `app/Models/RkasItemBulan.php`.

### 4.9 `kategori_juknis` — `2026_08_30_000002:27`
Kategori JUKNIS yang dapat diatur pengguna (pola SmartRKAS).

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `nama` | varchar(100) | mis. Honor, Buku, Sarpras |
| `arah` | varchar(20) default `maksimal` | `maksimal` / `minimal` |
| `batas_persen` | decimal(5,2) default 0 |  |
| `berlaku_untuk` | varchar(30) nullable | SmartRKAS compat (`+150836`) |
| `keterangan` | text nullable |  |
| `timestamps` |  |  |

Model: `app/Models/KategoriJuknis.php`.

### 4.10 `kode_rekening_kategori_juknis` — `2026_08_30_000002:36`
Pivot pemetaan rekening → kategori JUKNIS.

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `kategori_juknis_id` | FK → `kategori_juknis.id` cascade |  |
| `master_kode_rekening_id` | FK → `master_kode_rekening.id` cascade |  |
| `timestamps` |  |  |
| Unique | `[kategori_juknis_id, master_kode_rekening_id]` |  |

### 4.11 `audit_logs` — `2026_08_30_000002:11`

| Kolom | Tipe | Ket |
|-------|------|-----|
| `id` | bigint PK |  |
| `user_id` | FK nullable → `users.id` nullOnDelete |  |
| `action` | varchar(50) | mis. `create`, `update`, `delete`, `backup.create` |
| `auditable_type` | varchar(100) | Nama model |
| `auditable_id` | bigint nullable |  |
| `description` | text nullable |  |
| `old_values` | json nullable |  |
| `new_values` | json nullable |  |
| `ip_address` | varchar(45) nullable |  |
| `timestamps` |  | indexed `created_at`, `[auditable_type, auditable_id]` |

Model: `app/Models/AuditLog.php`, Trait: `app/Traits/LogsActivity.php`.

### 4.12 Tabel Framework
`users | password_reset_tokens | sessions | cache | cache_locks | jobs | job_batches | failed_jobs | migrations` — bawaan Laravel, tidak dimodifikasi.

Seeder:
- `DatabaseSeeder` — ringan untuk test (9 barang KB-001..009)
- `SmartRkasMasterSeeder` — 9 jenis + 139 program + 276 rekening (idempotent)
- `ArkasKodeBarangSeeder` — 81.062 barang via ZipArchive custom (manual, tidak auto di test)
- `JuknisSeeder` — kategori JUKNIS default

---

## 5. Semantik KOREKSI & KONTROL

| Istilah | Sumber | Rumus | Ket |
|---------|--------|-------|-----|
| **Jumlah Kontrol** | `rkas_item.jumlah` | `volume × harga_satuan` | Auto-hitung |
| **KOREKSI** | `rkas_item.koreksi` | Input manual ± rupiah | Default 0, di `<details>` collapsible |
| **Jumlah +Koreksi** | accessor `jumlah_koreksi` | `jumlah + koreksi` | **Angka terpakai** di PDF/Excel |
| **KONTROL** | accessor `kontrol` | `OK` bila `harga_satuan == harga_satuan_arkas` else `SELISIH` | Badge dot hijau/amber + tooltip |

---

## 6. Logika JUKNIS (Permendikdasmen No. 8/2026)

File: `config/juknis.php:1` + `app/Services/JuknisValidator.php:1`

**Prinsip klasifikasi presisi KARSA:** satu item masuk komponen HANYA bila **kombinasi AND** `kode_program` **dan** (`jenis_belanja` **atau** prefix `rekening` **atau** `keywords` uraian) keduanya cocok. Prioritas eksklusif: `honor > buku > sarpras > null`.

| Komponen | Batas | Kode Program | Jenis Belanja | Prefix Rekening | Keywords | Status |
|----------|-------|--------------|---------------|-----------------|----------|--------|
| **Honor** | Negeri ≤20%, Swasta ≤40% dari pagu total | `07.12.01`–`07.12.04` | `Belanja Jasa` | `5.1.02.02.01` | honor, honorarium | `sesuai` bila ≤ batas else `melebihi` |
| **Buku** | ≥10% dari pagu total | `03.02.02`, `05.02.02`–`05.02.05` | `Belanja Modal Buku` | `5.2.05` | buku | `sesuai` bila ≥ batas else `kurang` |
| **Sarpras** | ≤20% dari pagu total | `05.08.01`, `05.08.03`, `05.08.05`, `05.08.06`, `05.08.10`, `05.08.12` | `Belanja Jasa Pemeliharaan` | `5.1.02.03` | — | `sesuai` bila ≤ batas else `melebihi` |
| **Tahap I** | ≥50% pagu di Jan–Jun | — | — | — | — | `sesuai` bila pct ≥50% else `kurang` |

- `status_sekolah` diambil dari `pengaturan_sekolah.status_sekolah` (`JuknisValidator:30`).
- `summary()` mengembalikan `pagu_total`, `sudah_dianggarkan`, `status_sekolah`, `honor/buku/sarpras/tahap1 {total, persen, batas_persen, batas_nominal, status, sisa}`.

---

## 7. Fitur Aplikasi

### 7.1 Lembar Kerja RKAS — Terstruktur per Kegiatan (Grouped)
- **Grouped per KEGIATAN** — header `bg-slate-100` berisi badge kode biru, nama, sub_program, tombol `+ Sisip Uraian`, count item, subtotal `Sudah Dianggarkan` / `Bulan X: Rp … · 1 Thn: Rp …`. Urut `SORT_NATURAL` kode SNP.
- **Tabel 8 Kolom Lega:** `No | Uraian & Keterangan Khusus | Kode Rekening & Jenis Belanja | Volume | Harga Satuan | Total Anggaran | Bulan Pelaksanaan | Aksi` (dari 13 kolom).
- **No Hybrid:** besar = urutan kegiatan, kecil abu `#no_urut` global.
- **Volume presisi:** `rtrim(rtrim(number_format(...,2),'0'),',')` — `10 Meter` bukan `10,00 Meter`.
- **Filter Bulan Pill:** Semua + Jan–Des 3 huruf (aktif biru); saat bulan terpilih tampil `volume_bulan` & `jumlah_bulan` spesifik + hint `1 Thn:`.
- **Aksi:** Sisip (prefill kegiatan+rekening), Edit, Hapus.
- **Kartu Statistik:** Pagu Total / Sudah Dianggarkan / Sisa Pagu (hint `harus Rp 0`) / Total Tahap aktif.

### 7.2 Modal "Detail Anggaran Kegiatan" — 2-Step (max-w-5xl, max-h-[92vh])
- **Step 1 — Pilih Kegiatan & Rekening:** Live Search Autocomplete (≥2 huruf) untuk 8 SNP & rekening; validasi sebelum Lanjut; indicator dot.
- **Step 2 — Detail Uraian & Alokasi:**
  - **Uraian Autocomplete:** ketik ≥2 huruf → dropdown katalog 81.062; pilih → auto-isi `uraian`, `kode_barang_id`, `harga_min/max`, `harga_satuan_arkas`, satuan ke bulan kosong, `harga_satuan` bila 0.
  - **Uraian & Keterangan Kustom 100% editable bebas** (maks 500 karakter).
  - **Harga Satuan Rupiah dinamis + SSH Card live:** badge `harga_min`/`harga_max`, warning merah `> harga_max`, kuning `< harga_min`, hijau dalam rentang; badge KONTROL OK/SELISIH.
  - **KOREKSI collapsible** `<details>` *⚙️ Penyesuaian Khusus* — auto-open bila `koreksi ≠ 0`, rumus `Σ Vol × Harga + Koreksi`.
  - **Grid 12 Bulan:** kartu `border-blue` Tahap I (1-6) & `border-emerald` Tahap II (7-12) + badge; input Volume & Satuan; tombol *📋 Salin Volume Jan ke Semua Bulan* (`copyJanToAll()`).
  - Footer `Tutup | Lanjut →` vs `← Kembali | Simpan ke Anggaran`.
- **Sisip Kontekstual:** `window.__rkasModalHost.openSisip(kegiatanId, text, rekeningId?, text?)` dari header kegiatan & baris item.

### 7.3 Dashboard
- 3 kartu premium (Pagu/Sudah/Sisa) + progress + hint.
- Grafik proporsi 9 Jenis Belanja resmi ARKAS (palette `crc32`), subtitle `9 Jenis resmi ARKAS`.
- Grafik Honor/Buku/Sarpras/Tahap I dengan marker batas + sisa.
- Badge `Status: Negeri/Swasta & Honor maks X%`.

### 7.4 Monitoring Kepatuhan JUKNIS
- Tab **Ringkasan** — bar `h-2` + marker batas + sisa per komponen.
- Tab **Pemetaan** — checklist 100 baris + pemetaan centang per Jenis Belanja (group `rekeningByJenis`, `details` collapsible auto-open bila terpilih, `Pilih terlihat`).
- Route `POST /monitoring/juknis/mapping` untuk simpan pemetaan `kategori_juknis`.

### 7.5 Master Data
- **Program:** paginasi 20 + filter SNP, modal Tambah/Ubah, import Excel.
- **Rekening:** paginasi 30 + filter Jenis (9 + Tanpa), tampil `jenisBelanja`.
- **Barang:** paginasi 50 + filter Kategori + kode_rekening, sebaran total DB (bukan per halaman), modal Tambah/Ubah.
- Import via `POST /master/import` (Excel .xlsx/.xlsm, batas upload, audit log).

### 7.6 Pengaturan
- Profil sekolah (NPSN, nama, kepala/bendahara + NIP, alamat lengkap, **status_sekolah** negeri/swasta).
- Pagu 1 tahun & per Tahap (total, tahap1, tahap2) — validasi `pagu_tahap1 + pagu_tahap2 = pagu_total` (hint 50%).
- `GET /pengaturan` + `POST /pengaturan/sekolah` + `POST /pengaturan/pagu`.

### 7.7 Backup & Restore
- `GET /backup` — daftar file `.zip` + info ukuran/tanggal.
- `POST /backup/create` — `VACUUM INTO` snapshot konsisten (fallback copy file bila dalam transaksi test).
- `POST /backup/restore` (upload) & `POST /backup/{file}/restore` (terdaftar) — backup keselamatan otomatis sebelum restore.
- `GET /backup/{file}/download` & `DELETE /backup/{file}/delete`.
- Audit `backup.create`/`backup.restore`.

### 7.8 Laporan & Export
- `GET /rkas/pdf` — kertas kerja PDF (dompdf) termasuk kolom KOREKSI & Jumlah +Koreksi.
- `GET /rkas/export` — Excel `.xlsx` (FromView `rkas/excel`) kolom KOREKSI & KONTROL.
- Kertas kerja grouped per kegiatan (export grouped menyusul).

### 7.9 Keamanan & Lainnya
- **Auth manual** — `AuthController`, `auth/login.blade.php`, middleware `auth` di semua route, `POST /logout` di topbar.
- **Audit Log** — `GET /audit-log`, trait `LogsActivity` di model utama.
- **Tema Global Dark Mode** — toggle `localStorage`, `html.dark`, variabel CSS, `app.css:130` kontras 7:1.
- **Sidebar Premium** — logo K, collapse, active state, backdrop, mobile drawer (`layouts/app.blade.php`).
- **Tauri 2 Desktop** — `src-tauri/tauri.conf.json` identifier `id.karsa.rkas2026`, `lib.rs` spawn server port 9200.

---

## 8. Daftar Route & Endpoint

| Method | URI | Controller | Nama | Ket |
|--------|-----|------------|------|-----|
| GET | `/` , `/rkas` | `RkasController@index` | `rkas.index` | Lembar kerja grouped |
| POST | `/rkas/store` | `RkasController@store` | `rkas.store` | Simpan item + 12 bulan |
| GET | `/rkas/{id}/json` | `RkasController@showJson` | `rkas.json` | JSON untuk modal edit (+harga_min/max) |
| POST | `/rkas/{id}/update` | `RkasController@update` | `rkas.update` | Update item + alokasi |
| DELETE | `/rkas/{id}/delete` | `RkasController@destroy` | `rkas.destroy` | Hapus |
| GET | `/rkas/pdf` | `RkasController@pdf` | `rkas.pdf` | PDF kertas kerja |
| GET | `/rkas/export` | `RkasController@export` | `rkas.export` | Excel .xlsx |
| GET | `/api/search/kegiatan?q=` | `RkasSearchController@searchKegiatan` | `api.search.kegiatan` | Autocomplete SNP |
| GET | `/api/search/rekening?q=` | `RkasSearchController@searchRekening` | `api.search.rekening` | Autocomplete rekening (+jenisBelanja) |
| GET | `/api/search/barang?q=` | `RkasSearchController@searchBarang` | `api.search.barang` | Autocomplete katalog (+harga_min/max, subtext SSH) |
| GET | `/pengaturan` | `PengaturanController@index` | `pengaturan.index` |  |
| POST | `/pengaturan/sekolah` | `PengaturanController@updateSekolah` | `pengaturan.update-sekolah` |  |
| POST | `/pengaturan/pagu` | `PengaturanController@updatePagu` | `pengaturan.update-pagu` |  |
| GET | `/backup` | `BackupController@index` | `backup.index` |  |
| POST | `/backup/create` | `BackupController@create` | `backup.create` |  |
| POST | `/backup/restore` | `BackupController@restore` | `backup.restore` | Upload .zip |
| POST | `/backup/{filename}/restore` | `BackupController@restoreExisting` | `backup.restore-file` |  |
| GET | `/backup/{filename}/download` | `BackupController@download` | `backup.download` |  |
| DELETE | `/backup/{filename}/delete` | `BackupController@destroy` | `backup.destroy` |  |
| GET | `/dashboard` | `DashboardController@index` | `dashboard.index` |  |
| GET | `/monitoring/juknis` | `MonitoringJuknisController@index` | `monitoring.juknis` |  |
| POST | `/monitoring/juknis/mapping` | `MonitoringJuknisController@mapping` | `monitoring.juknis.mapping` |  |
| GET | `/audit-log` | `AuditLogController@index` | `audit.index` |  |
| GET | `/master/program` | `MasterDataController@program` | `master.program` | + store/update/destroy |
| GET | `/master/rekening` | `MasterDataController@rekening` | `master.rekening` | + store/update/destroy |
| GET | `/master/barang` | `MasterDataController@barang` | `master.barang` | + store/update/destroy |
| POST | `/master/import` | `MasterDataController@import` | `master.import` |  |
| GET | `/login` | `AuthController@showLogin` | `login` | guest |
| POST | `/login` | `AuthController@login` | `login.attempt` |  |
| POST | `/logout` | `AuthController@logout` | `logout` | auth |

Semua route `auth` kecuali `/login` — verifikasi `php artisan route:list`.

---

## 9. Alur Data Utama

```
[Master Program + Rekening + Barang] ──Live Search──> [Modal 2-Step]
        |                                                |
        +--> pilihan kegiatan/rekening/uraian ───────────> [POST /rkas/store]
                                                             |
                                                     rkas_item (1) + rkas_item_bulan (12)
                                                             |
                                +-- Dashboard (JuknisValidator) <-- pagu_total
                                +-- Monitoring JUKNIS
                                +-- Lembar Kerja Grouped (RkasController@index)
                                +-- PDF / Excel Export
                                +-- Backup .zip
```

- `jumlah` & `jumlah` per bulan dihitung `volume × harga_satuan` di controller & Alpine computed.
- `koreksi` ditambah manual, `jumlah_koreksi` dipakai laporan.
- `kontrol` badge per baris, tooltip harga acuan.

---

## 10. Referensi File Sumber

| Area | File |
|------|------|
| Migrasi | `database/migrations/2026_08_30_000001_create_rkas_system_tables.php:1`, `..._add_koreksi:1`, `..._audit_and_juknis:1`, `..._align_master:1`, `..._add_arkas_fields:1`, `..._add_status_sekolah:1` |
| Model | `app/Models/RkasItem.php:1`, `RkasItemBulan.php`, `MasterProgram.php`, `MasterKodeRekening.php`, `KodeBarang.php`, `JenisBelanja.php`, `PengaturanSekolah.php`, `TahunAnggaran.php`, `KategoriJuknis.php`, `AuditLog.php` |
| Controller | `app/Http/Controllers/RkasController.php`, `RkasSearchController.php`, `DashboardController.php`, `MonitoringJuknisController.php`, `MasterDataController.php`, `PengaturanController.php`, `BackupController.php`, `AuthController.php` |
| Service | `app/Services/JuknisValidator.php:1`, `JenisBelanjaResolver.php` |
| Config | `config/juknis.php:1` |
| View | `resources/views/rkas/index.blade.php` (modal 2-step), `dashboard/index.blade.php`, `monitoring/index.blade.php`, `master/*.blade.php`, `layouts/app.blade.php` |
| Route | `routes/web.php:1` |
| Tauri | `src-tauri/src/lib.rs:75`, `src-tauri/tauri.conf.json:3` |

---

*Dokumen ini digenerate dari ground truth migrasi, model, service, dan route pada 02 Sep 2026. Untuk perubahan skema selanjutnya, jalankan `php artisan migrate:status` dan `php artisan db:show` sebagai verifikasi.*
