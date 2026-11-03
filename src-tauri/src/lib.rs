#![cfg_attr(mobile, tauri::mobile_entry_point)]

use std::net::{TcpListener, TcpStream};
use std::path::PathBuf;
use std::process::{Child, Command};
use std::sync::Mutex;
use std::thread;
use std::time::{Duration, Instant};
use tauri::{Manager, WebviewUrl, WebviewWindowBuilder};

struct PhpServerState(Mutex<Option<Child>>);

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
            log::warn!("First-run: seed file tidak ditemukan di kandidat {:?}, lewati copy", candidates);
            return;
        }
    };

    if let Some(parent) = db_path.parent() {
        let _ = std::fs::create_dir_all(parent);
    }

    match std::fs::copy(&seed, &db_path) {
        Ok(bytes) => log::info!("First-run: copied seed DB {:?} -> {:?} ({} bytes)", seed, db_path, bytes),
        Err(e) => log::error!("First-run: gagal copy seed DB {:?} -> {:?}: {}", seed, db_path, e),
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
fn spawn_php_server(app: &tauri::AppHandle, root: &std::path::Path, port: u16) -> Child {
    let php = php_binary(app);
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

    let mut cmd = Command::new(&php);
    // Muat php.ini bundle secara eksplisit (letaknya di folder php)
    if php.components().count() > 1 {
        let ini = php.parent().map(|p| p.join("php.ini"));
        if let Some(ini) = ini.filter(|p| p.is_file()) {
            cmd.arg("-c").arg(native_path(&ini));
        }
    }
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
        .env("DB_DATABASE", db_path_for(app))
        .env("KARSA_DATA_DIR", &data_dir)
        .env("APP_ENV", "production")
        .env("APP_VERSION", env!("CARGO_PKG_VERSION"))
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null());
    prepend_php_to_path(&mut cmd, app);
    apply_cacert_scan(&mut cmd, app);

    #[cfg(windows)]
    {
        use std::os::windows::process::CommandExt;
        const CREATE_NO_WINDOW: u32 = 0x0800_0000;
        cmd.creation_flags(CREATE_NO_WINDOW);
    }

    cmd.spawn()
        .unwrap_or_else(|e| panic!("Gagal menjalankan PHP server ({e}). Pastikan PHP bisa dieksekusi."))
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

    // Dialog OS dijalankan di thread non-UI agar WebView2 tidak membeku.
    let pilihan = tauri::async_runtime::spawn_blocking(move || {
        rfd::FileDialog::new()
            .set_title("Simpan Berkas KARSA")
            .set_file_name(nama)
            .save_file()
    })
    .await
    .map_err(|e| format!("Dialog simpan gagal dibuka: {e}"))?;

    let Some(tujuan) = pilihan else {
        return Ok(None); // pengguna menekan Batal
    };

    use futures_util::StreamExt;
    let mut berkas = std::fs::File::create(&tujuan)
        .map_err(|e| format!("Gagal membuat berkas tujuan: {e}"))?;
    let mut aliran = respons.bytes_stream();
    while let Some(potongan) = aliran.next().await {
        let potongan = potongan.map_err(|e| format!("Unduhan terputus: {e}"))?;
        std::io::Write::write_all(&mut berkas, &potongan)
            .map_err(|e| format!("Gagal menyimpan berkas: {e}"))?;
    }

    Ok(Some(tujuan.display().to_string()))
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app = tauri::Builder::default()
        .invoke_handler(tauri::generate_handler![simpan_unduhan])
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
            let _window = WebviewWindowBuilder::new(
                app,
                "main",
                WebviewUrl::External(url.parse().expect("URL server PHP tidak valid")),
            )
            .title("KARSA — Kertas Kerja RKAS")
            .inner_size(1440.0, 900.0)
            .min_inner_size(1024.0, 700.0)
            .center()
            .build()?;

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