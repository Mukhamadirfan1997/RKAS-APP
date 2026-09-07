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
/// app_data_dir agar Laravel bisa menulis.
fn ensure_storage_ready(_app: &tauri::AppHandle, root: &std::path::Path) {
    if cfg!(debug_assertions) {
        return;
    }
    let data_dir = root.join("storage");
    let dirs = [
        "logs",
        "framework/cache/data",
        "framework/sessions",
        "framework/views",
        "app/backups",
        "app/updates",
    ];
    for d in dirs {
        let _ = std::fs::create_dir_all(data_dir.join(d));
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

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app = tauri::Builder::default()
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
            ensure_storage_ready(&handle, &root);

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