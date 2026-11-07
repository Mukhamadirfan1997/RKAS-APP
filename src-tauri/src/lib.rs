#![cfg_attr(mobile, tauri::mobile_entry_point)]

use std::net::{TcpListener, TcpStream};
use std::path::PathBuf;
use std::process::{Child, Command};
use std::sync::Mutex;
use std::thread;
use std::time::{Duration, Instant};
use tauri::{Manager, WebviewUrl, WebviewWindowBuilder};
use tauri_plugin_dialog::DialogExt;
use tauri_plugin_updater::UpdaterExt;

struct PhpServerState(Mutex<Option<Child>>);

/// Progres pemasangan update yang dibaca antarmuka lewat polling `status_update`.
#[derive(Clone, serde::Serialize)]
struct ProgresUpdate {
    tahap: String,
    persen: f64,
    terunduh: u64,
    total: u64,
    versi: String,
}

impl Default for ProgresUpdate {
    fn default() -> Self {
        Self {
            tahap: "diam".to_string(),
            persen: 0.0,
            terunduh: 0,
            total: 0,
            versi: String::new(),
        }
    }
}

struct UpdateState(Mutex<ProgresUpdate>);

impl UpdateState {
    fn set(&self, tahap: &str, persen: f64, terunduh: u64, total: u64, versi: &str) {
        if let Ok(mut guard) = self.0.lock() {
            guard.tahap = tahap.to_string();
            guard.persen = persen;
            guard.terunduh = terunduh;
            guard.total = total;
            guard.versi = versi.to_string();
        }
    }
}

impl PhpServerState {
    fn shutdown(&self) {
        if let Ok(mut guard) = self.0.lock() {
            if let Some(ref mut child) = *guard {
                let _ = child.kill();
                let _ = child.wait();
            }
        }
    }
}

/// Cari port bebas agar tidak bentrok dengan server lain.
fn find_free_port() -> u16 {
    TcpListener::bind(("127.0.0.1", 0))
        .expect("cannot bind to a free port")
        .local_addr()
        .expect("cannot resolve local address")
        .port()
}

/// Path PHP: prioritaskan PHP yang dibundel di resource_dir; fallback `php` di PATH.
fn php_binary(app: &tauri::AppHandle) -> PathBuf {
    if let Ok(dir) = app.path().resource_dir() {
        let bundled = dir.join("php").join("php.exe");
        if bundled.is_file() {
            return bundled;
        }
    }
    PathBuf::from("php")
}

fn php_dir(app: &tauri::AppHandle) -> Option<PathBuf> {
    let php = php_binary(app);
    if php.components().count() > 1 {
        php.parent().map(PathBuf::from)
    } else {
        None
    }
}

/// Tambahkan direktori PHP bundle ke PATH agar sub-proses yang di-spawn PHP
/// (mis. curl, ekstensi) menemukan DLL-nya.
fn prepend_php_to_path(cmd: &mut Command, app: &tauri::AppHandle) {
    if let Some(dir) = php_dir(app) {
        let current = std::env::var("PATH").unwrap_or_default();
        cmd.env("PATH", format!("{};{}", dir.display(), current));
    }
}

/// Konversi PathBuf (yang ber-prefix `\\?\` dari resource_dir / canonicalization
/// Rust) menjadi string `C:\...` normal yang bisa dibuka PHP CLI / libcurl.
fn native_path(p: &PathBuf) -> String {
    #[cfg(windows)]
    {
        let s = p.to_string_lossy();
        if let Some(rest) = s.strip_prefix(r"\\?\UNC\") {
            format!(r"\\{rest}")
        } else if let Some(rest) = s.strip_prefix(r"\\?\") {
            rest.to_string()
        } else {
            s.to_string()
        }
    }
    #[cfg(not(windows))]
    {
        p.display().to_string()
    }
}

/// Tulis `cacert.ini` di app_data (scan dir) yang menunjuk ke CA bundle bundled,
/// lalu arahkan PHP_INI_SCAN_DIR ke sana supaya HTTPS (GitHub API) jalan.
fn cacert_scan_dir(app: &tauri::AppHandle) -> Option<PathBuf> {
    let Some(dir) = php_dir(app) else {
        return None;
    };
    let cacert = dir.join("extras").join("ssl").join("cacert.pem");
    if !cacert.is_file() {
        return None;
    }
    let data_dir = app.path().app_data_dir().ok()?;
    let scan_dir = data_dir.join("php-ini-scan");
    std::fs::create_dir_all(&scan_dir).ok()?;
    let cacert_path = native_path(&cacert).replace('\\', "\\\\");
    let contents = format!(
        "curl.cainfo=\"{cacert_path}\"\nopenssl.cafile=\"{cacert_path}\"\n",
    );
    let _ = std::fs::write(scan_dir.join("cacert.ini"), contents);
    Some(scan_dir)
}

fn apply_cacert_scan(cmd: &mut Command, app: &tauri::AppHandle) {
    if let Some(scan) = cacert_scan_dir(app) {
        cmd.env("PHP_INI_SCAN_DIR", scan);
    }
}

/// Root aplikasi Laravel. Prioritas: resource_dir (bundle produksi) lalu
/// project root saat dev.
fn app_root(app: &tauri::AppHandle) -> PathBuf {
    if let Ok(dir) = app.path().resource_dir() {
        if dir.join("artisan").is_file() {
            return dir;
        }
    }
    // Dev: cwd = direktori proyek Laravel
    std::env::current_dir().unwrap_or_else(|_| PathBuf::from("."))
}

fn db_path_for(app: &tauri::AppHandle) -> PathBuf {
    if cfg!(debug_assertions) {
        // Dev: tetap pakai database/database.sqlite di project root (mudah di-inspect)
        app_root(app).join("database/database.sqlite")
    } else {
        // Produksi: pakai app_data_dir agar writable tanpa admin (Program Files read-only)
        app.path()
            .app_data_dir()
            .unwrap_or_else(|_| app_root(app))
            .join("database.sqlite")
    }
}

/// Pastikan struktur storage (log, compiled views, backup, update) ada di
/// app_data_dir agar Laravel bisa menulis (path tersinkron dengan
/// `useStoragePath()` di bootstrap/app.php — BUKAN resource_dir/install dir).
fn ensure_storage_ready(app: &tauri::AppHandle) {
    if cfg!(debug_assertions) {
        return;
    }
    let Ok(data_dir) = app.path().app_data_dir() else {
        return;
    };
    let storage_root = data_dir.join("storage");
    let dirs = [
        "logs",
        "framework/cache/data",
        "framework/sessions",
        "framework/views",
        "app/backups",
        "app/updates",
    ];
    for d in dirs {
        let _ = std::fs::create_dir_all(storage_root.join(d));
    }
}

fn ensure_db_ready(app: &tauri::AppHandle, root: &std::path::Path) {
    let db_path = db_path_for(app);

    // Jika database sudah ada dan berisi tabel master, jangan timpa (jaga data sekolah)
    if db_path.exists() {
        if let Ok(meta) = std::fs::metadata(&db_path) {
            if meta.len() > 0 {
                log::info!("DB sudah ada: {:?} ({} bytes), skip first-run copy", db_path, meta.len());
                return;
            }
        }
    }

    // Migrasi dari instalasi lama: jika DB di app_data belum ada tapi ada di install dir
    if !cfg!(debug_assertions) {
        let legacy = root.join("database/database.sqlite");
        if legacy.exists() {
            if let Ok(meta) = std::fs::metadata(&legacy) {
                if meta.len() > 0 {
                    if let Some(parent) = db_path.parent() {
                        let _ = std::fs::create_dir_all(parent);
                    }
                    if std::fs::copy(&legacy, &db_path).is_ok() {
                        log::info!("Migrasi DB lama: {:?} -> {:?}", legacy, db_path);
                        return;
                    }
                }
            }
        }
    }

    // Cari file seed di resource_dir (bundle) atau project root
    let mut candidates: Vec<std::path::PathBuf> = Vec::new();
    if let Ok(res_dir) = app.path().resource_dir() {
        candidates.push(res_dir.join("database-seed.sqlite"));
        candidates.push(res_dir.join("resources/database-seed.sqlite"));
        candidates.push(res_dir.join("_up_/resources/database-seed.sqlite"));
    }
    candidates.push(root.join("resources/database-seed.sqlite"));
    candidates.push(root.join("../resources/database-seed.sqlite"));
    candidates.push(std::path::PathBuf::from("resources/database-seed.sqlite"));
    candidates.push(std::path::PathBuf::from("_up_/resources/database-seed.sqlite"));

    let mut seed_path: Option<std::path::PathBuf> = None;
    for c in &candidates {
        if c.exists() {
            seed_path = Some(c.clone());
            break;
        }
    }

    let seed = match seed_path {
        Some(p) => p,
        None => {
            // Jangan diam: tanpa seed, aplikasi tetap terbuka tetapi katalog
            // 91 ribu barang & master hilang → RKAS tidak bisa diisi.
            let pesan = format!(
                "PERINGATAN: berkas seed database tidak ditemukan (kandidat: {:?}). \
                 Katalog barang & master tidak tersedia sehingga RKAS tidak dapat diisi. \
                 Pasang ulang aplikasi atau hubungi pembuat.",
                candidates
            );
            log::error!("{}", pesan);
            catat_migrasi(app, &pesan);
            return;
        }
    };

    if let Some(parent) = db_path.parent() {
        let _ = std::fs::create_dir_all(parent);
    }

    match std::fs::copy(&seed, &db_path) {
        Ok(bytes) => {
            let baris = format!("First-run: copied seed DB {:?} -> {:?} ({} bytes)", seed, db_path, bytes);
            log::info!("{}", baris);
            catat_migrasi(app, &baris);
        }
        Err(e) => {
            let baris = format!("GAGAL first-run: copy seed DB {:?} -> {:?}: {}", seed, db_path, e);
            log::error!("{}", baris);
            catat_migrasi(app, &baris);
        }
    }
}

/// Tunggu sampai server siap menerima koneksi (maks 60 detik).
fn wait_ready(port: u16) -> bool {
    let deadline = Instant::now() + Duration::from_secs(60);
    while Instant::now() < deadline {
        if TcpStream::connect(("127.0.0.1", port)).is_ok() {
            return true;
        }
        thread::sleep(Duration::from_millis(300));
    }
    false
}

/// Spawn PHP built-in server langsung (bukan `artisan serve`) agar argumen
/// php.ini (cacert, dst) berlaku pada proses yang benar-benar menangani HTTP.
/// Perintah PHP dasar dengan environment aplikasi terpasang (DB di
/// app_data_dir, storage di KARSA_DATA_DIR, sertifikat HTTPS bundle).
/// Dipakai bersama oleh server web dan proses migrasi.
fn perintah_php(app: &tauri::AppHandle, root: &std::path::Path) -> Command {
    let php = php_binary(app);
    let data_dir = app.path().app_data_dir().expect("cannot resolve app data dir");

    let mut cmd = Command::new(&php);
    // Muat php.ini bundle secara eksplisit (letaknya di folder php)
    if php.components().count() > 1 {
        let ini = php.parent().map(|p| p.join("php.ini"));
        if let Some(ini) = ini.filter(|p| p.is_file()) {
            cmd.arg("-c").arg(native_path(&ini));
        }
    }

    cmd.current_dir(root)
        .env("DB_DATABASE", db_path_for(app))
        .env("KARSA_DATA_DIR", &data_dir)
        .env("APP_ENV", "production")
        .env("APP_VERSION", env!("CARGO_PKG_VERSION"));

    prepend_php_to_path(&mut cmd, app);
    apply_cacert_scan(&mut cmd, app);

    #[cfg(windows)]
    {
        use std::os::windows::process::CommandExt;
        const CREATE_NO_WINDOW: u32 = 0x0800_0000;
        cmd.creation_flags(CREATE_NO_WINDOW);
    }

    cmd
}

/// Timestamp UTC ringkas (YYYY-MM-DD HH:MM:SS) tanpa menambah dependensi baru.
fn waktu_utc() -> String {
    let detik = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .map(|d| d.as_secs())
        .unwrap_or(0);

    // Konversi epoch -> tanggal sipil (algoritma Howard Hinnant).
    let hari = (detik / 86_400) as i64;
    let sisa = detik % 86_400;
    let z = hari + 719_468;
    let era = z.div_euclid(146_097);
    let doe = z.rem_euclid(146_097);
    let yoe = (doe - doe / 1_460 + doe / 36_524 - doe / 146_096) / 365;
    let tahun = yoe + era * 400;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let tanggal = doy - (153 * mp + 2) / 5 + 1;
    let bulan = if mp < 10 { mp + 3 } else { mp - 9 };
    let tahun = if bulan <= 2 { tahun + 1 } else { tahun };

    format!(
        "{:04}-{:02}-{:02} {:02}:{:02}:{:02} UTC",
        tahun,
        bulan,
        tanggal,
        sisa / 3_600,
        (sisa % 3_600) / 60,
        sisa % 60
    )
}

/// Catat hasil migrasi ke `storage/logs/migrate.log` di folder data aplikasi.
///
/// Pada aplikasi terpasang (build release) `tauri_plugin_log` tidak menulis ke
/// berkas, sehingga tanpa ini kegagalan migrasi tidak meninggalkan jejak yang
/// bisa diperiksa teknisi. Berkas dibatasi ±512 KB (dirotasi ke `.1`).
fn catat_migrasi(app: &tauri::AppHandle, baris: &str) {
    let Ok(data_dir) = app.path().app_data_dir() else {
        return;
    };
    let dir = data_dir.join("storage").join("logs");
    if std::fs::create_dir_all(&dir).is_err() {
        return;
    }
    let berkas = dir.join("migrate.log");

    if let Ok(meta) = std::fs::metadata(&berkas) {
        if meta.len() > 512 * 1024 {
            let arsip = dir.join("migrate.log.1");
            let _ = std::fs::remove_file(&arsip);
            let _ = std::fs::rename(&berkas, &arsip);
        }
    }

    if let Ok(mut f) = std::fs::OpenOptions::new()
        .create(true)
        .append(true)
        .open(&berkas)
    {
        use std::io::Write;
        let _ = writeln!(f, "[{}] {}", waktu_utc(), baris);
    }
}

/// Catat aktivitas unduhan (Simpan Berkas) ke `storage/logs/unduh.log`.
///
/// Tujuan: bila pengguna melapor "berkas tidak tersimpan", teknisi bisa melihat
/// nama berkas, tujuan, dan pesan galat tanpa perlu membuka WebView.
fn catat_unduh(app: &tauri::AppHandle, baris: &str) {
    let Ok(data_dir) = app.path().app_data_dir() else {
        return;
    };
    let dir = data_dir.join("storage").join("logs");
    if std::fs::create_dir_all(&dir).is_err() {
        return;
    }
    let berkas = dir.join("unduh.log");

    if let Ok(meta) = std::fs::metadata(&berkas) {
        if meta.len() > 512 * 1024 {
            let arsip = dir.join("unduh.log.1");
            let _ = std::fs::remove_file(&arsip);
            let _ = std::fs::rename(&berkas, &arsip);
        }
    }

    if let Ok(mut f) = std::fs::OpenOptions::new()
        .create(true)
        .append(true)
        .open(&berkas)
    {
        use std::io::Write;
        let _ = writeln!(f, "[{}] {}", waktu_utc(), baris);
    }
}

/// Jalankan `php artisan migrate --force` di latar belakang. Setiap migrasi
/// pada proyek ini bersifat menambah (tidak menghapus data sekolah), jadi
/// aplikasi boleh langsung dipakai; katalog/kolom baru muncul setelah selesai.
/// Kegagalan hanya dicatat di log — aplikasi tetap berjalan.
fn jalankan_migrasi(app: &tauri::AppHandle, root: &std::path::Path) {
    let mut cmd = perintah_php(app, root);
    cmd.arg("artisan")
        .arg("migrate")
        .arg("--force")
        .arg("--no-interaction")
        .stdout(std::process::Stdio::piped())
        .stderr(std::process::Stdio::piped());

    log::info!("Menjalankan migrasi database di {:?}", root);
    catat_migrasi(
        app,
        &format!("mulai migrasi database di {}", root.display()),
    );

    match cmd.output() {
        Ok(out) => {
            let mut teks = String::from_utf8_lossy(&out.stdout).to_string();
            teks.push_str(&String::from_utf8_lossy(&out.stderr));
            if out.status.success() {
                log::info!("Migrasi database selesai ({}):\n{}", out.status, teks.trim());
                catat_migrasi(
                    app,
                    &format!("selesai ({}) — {}", out.status, teks.trim()),
                );
            } else {
                log::error!("Migrasi database gagal ({}):\n{}", out.status, teks.trim());
                catat_migrasi(app, &format!("GAGAL ({}) — {}", out.status, teks.trim()));
            }
        }
        Err(e) => {
            log::error!("Migrasi database tidak bisa dijalankan: {e}");
            catat_migrasi(app, &format!("GAGAL menjalankan php artisan: {e}"));
        }
    }
}

/// Daftarkan proses anak (server PHP) ke Windows Job Object dengan
/// `JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE`.
///
/// Tujuan: bila KARSA berhenti mendadak (crash, di-kill dari Task Manager, atau
/// dimatikan installer saat memperbarui), kernel Windows ikut menghentikan
/// `php.exe`. Tanpa ini server PHP bisa tertinggal sebagai proses yatim dan
/// mengunci `php.exe`/DLL di folder instalasi sehingga pembaruan gagal dengan
/// pesan "Error opening file for writing".
#[cfg(windows)]
fn daftarkan_ke_job_object(pid: u32) {
    use std::sync::OnceLock;
    use windows_sys::Win32::Foundation::CloseHandle;
    use windows_sys::Win32::System::JobObjects::{
        AssignProcessToJobObject, CreateJobObjectW, JobObjectExtendedLimitInformation,
        SetInformationJobObject, JOBOBJECT_EXTENDED_LIMIT_INFORMATION,
        JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE,
    };
    use windows_sys::Win32::System::Threading::{OpenProcess, PROCESS_SET_QUOTA, PROCESS_TERMINATE};

    // Handle job sengaja disimpan (tidak ditutup) selama aplikasi hidup. Saat
    // proses berakhir, Windows menutup semua handle → job ditutup → seluruh
    // proses di dalamnya dihentikan.
    static JOB: OnceLock<usize> = OnceLock::new();

    let job = *JOB.get_or_init(|| unsafe {
        let handle = CreateJobObjectW(std::ptr::null(), std::ptr::null());
        if handle.is_null() {
            eprintln!("KARSA: gagal membuat job object: {}", std::io::Error::last_os_error());
            return 0;
        }

        let mut info: JOBOBJECT_EXTENDED_LIMIT_INFORMATION = std::mem::zeroed();
        info.BasicLimitInformation.LimitFlags = JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE;

        let ok = SetInformationJobObject(
            handle,
            JobObjectExtendedLimitInformation,
            &info as *const _ as *const core::ffi::c_void,
            std::mem::size_of::<JOBOBJECT_EXTENDED_LIMIT_INFORMATION>() as u32,
        );
        if ok == 0 {
            eprintln!(
                "KARSA: gagal mengatur job object: {}",
                std::io::Error::last_os_error()
            );
            let _ = CloseHandle(handle);
            return 0;
        }

        handle as usize
    });

    if job == 0 {
        return;
    }

    unsafe {
        let proses = OpenProcess(PROCESS_SET_QUOTA | PROCESS_TERMINATE, 0, pid);
        if proses.is_null() {
            eprintln!(
                "KARSA: gagal membuka proses PHP ({pid}) untuk job object: {}",
                std::io::Error::last_os_error()
            );
            return;
        }
        if AssignProcessToJobObject(job as _, proses) == 0 {
            eprintln!(
                "KARSA: gagal memasukkan proses PHP ke job object: {}",
                std::io::Error::last_os_error()
            );
        }
        let _ = CloseHandle(proses);
    }
}

fn spawn_php_server(app: &tauri::AppHandle, root: &std::path::Path, port: u16) -> Child {
    let data_dir = app.path().app_data_dir().expect("cannot resolve app data dir");

    let server_router = root
        .join("vendor")
        .join("laravel")
        .join("framework")
        .join("src")
        .join("Illuminate")
        .join("Foundation")
        .join("resources")
        .join("server.php");

    let mut cmd = perintah_php(app, root);
    cmd.arg("-d")
        .arg("display_errors=0")
        .arg("-d")
        .arg(format!("error_log={}", data_dir.join("storage").join("logs").join("php-server-error.log").display()))
        .arg("-d")
        .arg("log_errors=1")
        .arg("-S")
        .arg(format!("127.0.0.1:{port}"))
        .arg(native_path(&server_router))
        .current_dir(root.join("public"))
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null());

    let child = cmd.spawn().unwrap_or_else(|e| {
        panic!("Gagal menjalankan PHP server ({e}). Pastikan PHP bisa dieksekusi.")
    });

    // Server PHP harus ikut mati saat KARSA berhenti (lihat daftarkan_ke_job_object).
    #[cfg(windows)]
    daftarkan_ke_job_object(child.id());

    child
}

/// Decode percent-encoding (untuk `filename*=UTF-8''...` pada Content-Disposition).
fn persen_decode(teks: &str) -> String {
    let bytes = teks.as_bytes();
    let mut hasil: Vec<u8> = Vec::with_capacity(bytes.len());
    let mut i = 0;
    while i < bytes.len() {
        if bytes[i] == b'%' {
            if let Some(hex) = teks.get(i + 1..i + 3) {
                if let Ok(nilai) = u8::from_str_radix(hex, 16) {
                    hasil.push(nilai);
                    i += 3;
                    continue;
                }
            }
        }
        hasil.push(bytes[i]);
        i += 1;
    }
    String::from_utf8_lossy(&hasil).into_owned()
}

/// Ambil nama berkas dari header Content-Disposition.
fn nama_berkas_dari_header(headers: &reqwest::header::HeaderMap) -> Option<String> {
    let nilai = headers
        .get(reqwest::header::CONTENT_DISPOSITION)?
        .to_str()
        .ok()?;
    let lower = nilai.to_ascii_lowercase();

    if let Some(pos) = lower.find("filename*=") {
        let sisa = &nilai[pos + 10..];
        let bagian = sisa.split(';').next().unwrap_or(sisa).trim().trim_matches('"');
        let nama = match bagian.find("''") {
            Some(p) => persen_decode(&bagian[p + 2..]),
            None => persen_decode(bagian),
        };
        if !nama.trim().is_empty() {
            return Some(nama);
        }
    }

    if let Some(pos) = lower.find("filename=") {
        let sisa = &nilai[pos + 9..];
        let bagian = sisa.split(';').next().unwrap_or(sisa).trim().trim_matches('"');
        if !bagian.trim().is_empty() {
            return Some(persen_decode(bagian));
        }
    }

    None
}

/// Unduh berkas dari server lokal memakai cookie sesi WebView, lalu tampilkan
/// dialog "Simpan sebagai" dengan pilihan Simpan / Batal.
///
/// Mengembalikan path tujuan bila disimpan, atau `None` bila dibatalkan.
#[tauri::command]
async fn simpan_unduhan(window: tauri::WebviewWindow, url: String) -> Result<Option<String>, String> {
    // Jaring pengaman: bila terjadi panic di dalam proses unduh (mis. kegagalan
    // internal klien HTTP), kembalikan pesan error agar antarmuka tidak
    // menggantung menunggu hasil yang tidak pernah datang.
    use futures_util::FutureExt;
    let proses = std::panic::AssertUnwindSafe(unduh_lalu_simpan(window, url)).catch_unwind();
    match proses.await {
        Ok(hasil) => hasil,
        Err(_) => Err(
            "Proses simpan berkas gagal karena kesalahan internal. Silakan coba lagi."
                .to_string(),
        ),
    }
}

/// Bagian inti `simpan_unduhan`: ambil berkas dari server lokal, tampilkan
/// dialog simpan, lalu tulis ke lokasi pilihan pengguna.
async fn unduh_lalu_simpan(
    window: tauri::WebviewWindow,
    url: String,
) -> Result<Option<String>, String> {
    let cookies = window
        .cookies()
        .map_err(|e| format!("Gagal membaca sesi aplikasi: {e}"))?;
    let cookie_header = cookies
        .iter()
        .map(|c| format!("{}={}", c.name(), c.value()))
        .collect::<Vec<_>>()
        .join("; ");

    let client = reqwest::Client::builder()
        .build()
        .map_err(|e| format!("Gagal menyiapkan unduhan: {e}"))?;

    let mut permintaan = client
        .get(&url)
        .header(reqwest::header::USER_AGENT, "KARSA-Desktop");
    if !cookie_header.is_empty() {
        permintaan = permintaan.header(reqwest::header::COOKIE, cookie_header);
    }

    let respons = permintaan
        .send()
        .await
        .map_err(|e| format!("Gagal menghubungi server lokal: {e}"))?;

    if !respons.status().is_success() {
        return Err(format!(
            "Gagal mengunduh berkas (HTTP {}). Muat ulang halaman lalu coba lagi.",
            respons.status()
        ));
    }

    // Server yang mengalihkan ke halaman login (sesi kedaluwarsa) mengembalikan
    // HTML 200; jangan sampai file HTML disimpan dengan nama PDF/Excel.
    let tipe = respons
        .headers()
        .get(reqwest::header::CONTENT_TYPE)
        .and_then(|v| v.to_str().ok())
        .unwrap_or("");
    if tipe.starts_with("text/html") {
        return Err(
            "Sesi login berakhir atau berkas tidak tersedia. Muat ulang halaman lalu coba lagi."
                .to_string(),
        );
    }

    let nama = nama_berkas_dari_header(respons.headers())
        .unwrap_or_else(|| "unduhan-karsa".to_string());

    // Dialog "Simpan sebagai" memakai plugin resmi Tauri (tauri-plugin-dialog).
    //
    // Catatan penting (sama seperti SmartRKAS):
    // - versi NON-blocking (`save_file` + callback) dipakai agar tidak memblokir
    //   event loop / thread async;
    // - `set_parent(&window)` WAJIB di Windows: tanpa parent, IFileDialog bisa
    //   tidak muncul atau muncul di belakang jendela aplikasi secara senyap;
    // - hasil dialog ditunggu dengan channel + batas waktu 120 detik agar
    //   command tidak pernah menggantung selamanya bila dialog gagal.
    let builder = window
        .dialog()
        .file()
        .set_title("Simpan Berkas KARSA")
        .set_file_name(&nama)
        .set_parent(&window);

    let (tx, rx) = std::sync::mpsc::channel();
    builder.save_file(move |pilihan| {
        let _ = tx.send(pilihan);
    });

    let pilihan = tauri::async_runtime::spawn_blocking(move || {
        rx.recv_timeout(std::time::Duration::from_secs(120))
    })
    .await
    .map_err(|e| format!("Dialog simpan gagal dibuka: {e}"))?
    .map_err(|_| "Dialog simpan tidak merespons dalam 120 detik. Silakan coba lagi.".to_string())?;

    let Some(bekas) = pilihan else {
        catat_unduh(window.app_handle(), &format!("dibatalkan: {nama}"));
        return Ok(None); // pengguna menekan Batal
    };

    let tujuan = bekas
        .into_path()
        .map_err(|e| format!("Lokasi penyimpanan tidak valid: {e}"))?;

    use futures_util::StreamExt;
    let hasil = async {
        let mut berkas = std::fs::File::create(&tujuan)
            .map_err(|e| format!("Gagal membuat berkas tujuan: {e}"))?;
        let mut aliran = respons.bytes_stream();
        let mut total: u64 = 0;
        while let Some(potongan) = aliran.next().await {
            let potongan = potongan.map_err(|e| format!("Unduhan terputus: {e}"))?;
            total += potongan.len() as u64;
            std::io::Write::write_all(&mut berkas, &potongan)
                .map_err(|e| format!("Gagal menyimpan berkas: {e}"))?;
        }
        Ok::<u64, String>(total)
    }
    .await;

    match hasil {
        Ok(byte) => {
            catat_unduh(
                window.app_handle(),
                &format!("tersimpan: {} ({} byte)", tujuan.display(), byte),
            );
            Ok(Some(tujuan.display().to_string()))
        }
        Err(e) => {
            // Jangan tinggalkan berkas setengah jadi di folder pengguna.
            let _ = std::fs::remove_file(&tujuan);
            catat_unduh(
                window.app_handle(),
                &format!("gagal: {} -> {}", nama, e),
            );
            Err(e)
        }
    }
}

/// Progres terkini pemasangan update (dipanggil berulang oleh antarmuka).
#[tauri::command]
fn status_update(state: tauri::State<'_, UpdateState>) -> ProgresUpdate {
    state
        .0
        .lock()
        .map(|guard| guard.clone())
        .unwrap_or_default()
}

/// Unduh, verifikasi tanda tangan, lalu pasang pembaruan; aplikasi menutup dan
/// terbuka kembali dengan versi baru (Windows).
#[tauri::command]
async fn perbarui_sekarang(app: tauri::AppHandle) -> Result<String, String> {
    let progres = app.state::<UpdateState>();
    progres.set("cek", 0.0, 0, 0, "");

    // Hook `on_before_exit` berjalan setelah paket berhasil diekstrak, tepat
    // sebelum installer dijalankan. Di sini server PHP dimatikan agar berkas di
    // folder instalasi (php.exe, vendor) tidak terkunci saat diganti.
    let handle_exit = app.clone();
    let updater = app
        .updater_builder()
        .on_before_exit(move || {
            if let Some(server) = handle_exit.try_state::<PhpServerState>() {
                server.shutdown();
            }
            handle_exit.cleanup_before_exit();
        })
        .build()
        .map_err(|e| format!("Pengaturan pembaruan tidak valid: {e}"))?;

    let update = updater
        .check()
        .await
        .map_err(|e| format!("Gagal memeriksa pembaruan: {e}"))?;

    let Some(update) = update else {
        return Ok("terbaru".to_string());
    };

    let versi = update.version.clone();
    progres.set("unduh", 0.0, 0, 0, &versi);

    let handle_progress = app.clone();
    let versi_progress = versi.clone();
    let bytes = update
        .download(
            move |potongan, total| {
                if let Ok(mut guard) = handle_progress.state::<UpdateState>().0.lock() {
                    guard.tahap = "unduh".to_string();
                    guard.versi = versi_progress.clone();
                    guard.terunduh = guard.terunduh.saturating_add(potongan as u64);
                    guard.total = total.unwrap_or(0);
                    guard.persen = match total {
                        Some(t) if t > 0 => (guard.terunduh as f64 / t as f64) * 100.0,
                        _ => 0.0,
                    };
                }
            },
            || {},
        )
        .await
        .map_err(|e| format!("Gagal mengunduh pembaruan: {e}"))?;

    progres.set("pasang", 100.0, 0, 0, &versi);

    // Windows: installer berjalan (mode senyap) lalu proses ini keluar sendiri.
    update
        .install(bytes)
        .map_err(|e| format!("Gagal memasang pembaruan: {e}"))?;

    Ok("dipasang".to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    // reqwest 0.13 di proyek ini memakai fitur `rustls-no-provider` (diaktifkan
    // bersama oleh crate lain), sehingga `Client::build()` akan panic bila tidak
    // ada provider kripto terpasang. Tanpa ini, tombol unduh tidak pernah
    // menampilkan dialog simpan karena perintahnya gagal di latar belakang.
    let _ = rustls::crypto::ring::default_provider().install_default();

    let app = tauri::Builder::default()
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_updater::Builder::new().build())
        .manage(UpdateState(Mutex::new(ProgresUpdate::default())))
        .invoke_handler(tauri::generate_handler![
            simpan_unduhan,
            status_update,
            perbarui_sekarang
        ])
        .setup(|app| {
            let handle = app.handle().clone();

            #[cfg(debug_assertions)]
            {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            let root = app_root(&handle);
            ensure_db_ready(&handle, &root);
            ensure_storage_ready(&handle);

            // Mode terpasang: terapkan migrasi database di latar belakang agar
            // fitur/katalog baru (impor referensi dinas) aktif tanpa menahan
            // jendela. Migrasi proyek ini hanya menambah, tidak menghapus data.
            if !cfg!(debug_assertions) {
                let handle_migrasi = handle.clone();
                let root_migrasi = root.clone();
                std::thread::spawn(move || jalankan_migrasi(&handle_migrasi, &root_migrasi));
            }

            let port = if cfg!(debug_assertions) {
                // Dev: sebelumDevCommand sudah menjalankan artisan serve di 9200
                9200
            } else {
                let port = find_free_port();
                let child = spawn_php_server(&handle, &root, port);
                app.manage(PhpServerState(Mutex::new(Some(child))));
                if !wait_ready(port) {
                    eprintln!("KARSA web server did not start in time (port {port})");
                }
                port
            };

            let url = format!("http://127.0.0.1:{port}/");
            let mut pembangun = WebviewWindowBuilder::new(
                app,
                "main",
                WebviewUrl::External(url.parse().expect("URL server PHP tidak valid")),
            )
            .title("KARSA — Kertas Kerja RKAS")
            .inner_size(1440.0, 900.0)
            .min_inner_size(1024.0, 700.0)
            .center();

            // Diagnostik opsional (tidak aktif secara bawaan): memungkinkan
            // pemeriksaan antarmuka lewat Chrome DevTools Protocol tanpa
            // mengubah build, contoh: KARSA_WEBVIEW_ARGS="--remote-debugging-port=9333".
            if let Ok(args) = std::env::var("KARSA_WEBVIEW_ARGS") {
                if !args.trim().is_empty() {
                    pembangun = pembangun.additional_browser_args(&args);
                }
            }
            // Diagnostik: pakai folder data WebView2 terpisah agar build dev bisa
            // berjalan berdampingan dengan aplikasi terpasang (yang memakai
            // folder data bawaan) tanpa bentrok.
            if let Ok(dir) = std::env::var("KARSA_WEBVIEW_DATA_DIR") {
                if !dir.trim().is_empty() {
                    pembangun = pembangun.data_directory(std::path::PathBuf::from(dir));
                }
            }

            let _window = pembangun.build()?;

            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("Gagal membangun aplikasi Tauri");

    app.run(|app_handle, event| {
        if let tauri::RunEvent::Exit = event {
            let state = app_handle.state::<PhpServerState>();
            state.shutdown();
        }
    });
}