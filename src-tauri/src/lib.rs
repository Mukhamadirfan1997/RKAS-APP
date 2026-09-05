#![cfg_attr(mobile, tauri::mobile_entry_point)]

use std::process::{Child, Command};
use std::sync::Mutex;
use tauri::{Manager, WebviewUrl, WebviewWindowBuilder};

const PHP_URL: &str = "http://127.0.0.1:9200";

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

fn app_root() -> std::path::PathBuf {
    // Saat dev/debug: cwd = direktori proyek Laravel.
    // Saat produksi: cari folder Laravel di samping executable hasil build.
    let exe = std::env::current_exe().unwrap_or_default();
    if exe.to_string_lossy().contains("target") {
        std::env::current_dir().unwrap_or_else(|_| std::path::PathBuf::from("."))
    } else {
        exe.parent()
            .map(|p| p.to_path_buf())
            .unwrap_or_else(|| std::path::PathBuf::from("."))
    }
}

fn db_path_for(app: &tauri::AppHandle) -> std::path::PathBuf {
    if cfg!(debug_assertions) {
        // Dev: tetap pakai database/database.sqlite di project root (mudah di-inspect)
        app_root().join("database/database.sqlite")
    } else {
        // Produksi: pakai app_data_dir agar writable tanpa admin (Program Files read-only)
        // e.g. C:\Users\<user>\AppData\Roaming\id.karsa.rkas2026\database.sqlite
        app.path()
            .app_data_dir()
            .unwrap_or_else(|_| app_root())
            .join("database.sqlite")
    }
}

fn ensure_db_ready(app: &tauri::AppHandle) {
    let root = app_root();
    let db_path = db_path_for(app);

    // Jika database sudah ada dan berisi tabel master, jangan timpa (jaga data sekolah)
    if db_path.exists() {
        if let Ok(meta) = std::fs::metadata(&db_path) {
            if meta.len() > 0 {
                // Cek cepat: file ada dan tidak kosong = dianggap sudah pernah di-seed
                // Guard tambahan: kalau suatu hari file korup (0 bytes), akan di-copy ulang di bawah
                log::info!("DB sudah ada: {:?} ({} bytes), skip first-run copy", db_path, meta.len());
                return;
            }
        }
    }

    // Migrasi dari instalasi lama: jika DB di app_data belum ada tapi ada di install dir (Program Files)
    // pindahkan (copy) agar data sekolah lama tidak hilang saat update
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

    // Cari file seed di beberapa lokasi kandidat (dev vs bundled resources)
    // Tauri bundle dengan resources: ["../resources/database-seed.sqlite"] akan ter-install sebagai
    // <exe>/_up_/resources/database-seed.sqlite (Wix/NSIS, lihat C:\Temp\karsa-test\PFiles\KARSA 2026\_up_\resources\)
    // jadi harus cek _up_ juga.
    let mut candidates: Vec<std::path::PathBuf> = Vec::new();
    if let Ok(res_dir) = app.path().resource_dir() {
        candidates.push(res_dir.join("database-seed.sqlite"));
        candidates.push(res_dir.join("resources/database-seed.sqlite"));
        candidates.push(res_dir.join("_up_/resources/database-seed.sqlite"));
    }
    candidates.push(root.join("_up_/resources/database-seed.sqlite"));
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

fn spawn_php_server(app: &tauri::AppHandle) -> Child {
    let mut cmd = Command::new("php");
    cmd.args([
        "artisan",
        "serve",
        "--host=127.0.0.1",
        "--port=9200",
        "--no-reload",
    ])
    .current_dir(app_root())
    .stdout(std::process::Stdio::null())
    .stderr(std::process::Stdio::null());

    // Produksi: arahkan Laravel ke DB di app_data_dir via env (writable tanpa admin)
    if !cfg!(debug_assertions) {
        let db = db_path_for(app);
        cmd.env("DB_DATABASE", &db);
        // Pastikan directory ada sebelum Laravel buka
        if let Some(parent) = db.parent() {
            let _ = std::fs::create_dir_all(parent);
        }
        log::info!("Spawn PHP dengan DB_DATABASE={:?}", db);
    }

    cmd.spawn()
        .expect("Gagal menjalankan PHP server. Pastikan PHP terinstal dan ada di PATH.")
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app = tauri::Builder::default()
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            // First-run: pastikan database/database.sqlite ada (copy pre-seeded jika belum)
            // Harus SEBELUM spawn PHP server dan SEBELUM window dibuka (blocking <1 detik)
            ensure_db_ready(app.handle());

            // Saat produksi, PHP server dijalankan dari dalam app.
            // Saat dev, `beforeDevCommand` di tauri.conf.json sudah menjalankan server.
            if !cfg!(debug_assertions) {
                let child = spawn_php_server(app.handle());
                app.manage(PhpServerState(Mutex::new(Some(child))));
            }

            let _window = WebviewWindowBuilder::new(
                app,
                "main",
                WebviewUrl::External(PHP_URL.parse().expect("URL server PHP tidak valid")),
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