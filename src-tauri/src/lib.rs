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

fn spawn_php_server() -> Child {
    Command::new("php")
        .args([
            "artisan",
            "serve",
            "--host=127.0.0.1",
            "--port=9200",
            "--no-reload",
        ])
        .current_dir(app_root())
        .stdout(std::process::Stdio::null())
        .stderr(std::process::Stdio::null())
        .spawn()
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

            // Saat produksi, PHP server dijalankan dari dalam app.
            // Saat dev, `beforeDevCommand` di tauri.conf.json sudah menjalankan server.
            if !cfg!(debug_assertions) {
                let child = spawn_php_server();
                app.manage(PhpServerState(Mutex::new(Some(child))));
            }

            let _window = WebviewWindowBuilder::new(
                app,
                "main",
                WebviewUrl::External(PHP_URL.parse().expect("URL server PHP tidak valid")),
            )
            .title("KARSA 2026 — Kertas Anggaran Sekolah")
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