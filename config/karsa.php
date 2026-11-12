<?php

// PENTING: JANGAN jalankan `php artisan config:cache` / `config:clear` / `optimize`
// di proses build (tauri.conf.json beforeBuildCommand).
// Nilai `version` dibaca dari ENV RUNTIME `APP_VERSION` yang disuntikkan
// src-tauri/src/lib.rs (env!("CARGO_PKG_VERSION")) saat aplikasi Tauri
// start -> spawn PHP server. Config cache akan membekukan versi lama
// (mis. 1.0.1) menjadi string statis di bootstrap/cache/config.php dan
// ikut ter-bundle, sehingga perubahan versi di Cargo.toml/tauri.conf.json
// tidak akan terlihat sampai cache di-regenerate manual. Itulah yang
// menyebabkan bug v0.0.0 / versi kedaluwarsa dalam bentuk berbeda.
// Biarkan config tetap dibaca live tiap boot; overhead-nya negligible.

$version = null;

// 1. Prioritas utama: APP_VERSION dari runtime env (production via Tauri).
//    Ini SATU-SATUNYA sumber yang benar di installer, karena src-tauri/
//    tidak ikut ter-bundle sehingga file tauri.conf.json tidak ada di
//    mesin user. lib.rs sudah mengisi APP_VERSION dari CARGO_PKG_VERSION.
$envVersion = env('APP_VERSION');
if (is_string($envVersion) && trim($envVersion) !== '') {
    $version = trim($envVersion);
}

// 2. Fallback dev: baca src-tauri/tauri.conf.json langsung dari disk.
//    Aman untuk `php artisan serve` tanpa Tauri (APP_VERSION kosong).
if ($version === null || $version === '') {
    try {
        $tauriConf = base_path('src-tauri/tauri.conf.json');
        if (is_file($tauriConf)) {
            $json = json_decode((string) file_get_contents($tauriConf), true);
            if (isset($json['version']) && is_string($json['version']) && trim($json['version']) !== '') {
                $version = trim($json['version']);
            }
        }
    } catch (Throwable $e) {
        // biarkan null — UpdateService akan return null (tidak tampil banner palsu)
    }
}

// 3. Jika kedua sumber gagal (sangat tidak wajar), biarkan null.
//    JANGAN fallback ke '0.0.0' — itu yang menyebabkan version_compare()
//    selalu true dan banner update palsu "Anda pakai v0.0.0".

return [
    'version' => $version,

    // Lokasi berkas riwayat versi yang dibaca halaman Tentang.
    // Dibuat bisa diarahkan (env) agar perilaku "berkas tidak ada" tetap
    // bisa diuji tanpa menyentuh CHANGELOG.md asli.
    'changelog_path' => env('KARSA_CHANGELOG_PATH') ?: base_path('CHANGELOG.md'),

    // --- Akses LAN (lihat app/Services/JaringanService.php) ---
    // Daftar kata kunci nama adapter yang TIDAK dianggap jaringan fisik.
    // Klien yang datang lewat adapter ini (VPN/tunnel/virtual) selalu ditolak,
    // walaupun alamatnya juga alamat privat RFC1918.
    'lan' => [
        // Lokasi berkas status akses LAN. `null` = storage/app/lan.json.
        'berkas' => null,

        'adapter_ditolak' => [
            'loopback',
            'wi-fi direct',
            'wifi direct',
            'virtual',
            'vmware',
            'virtualbox',
            'hyper-v',
            'vethernet',
            'docker',
            'wsl',
            'tap-',
            'tap adapter',
            'tap-windows',
            'tunnel',
            'vpn',
            'tailscale',
            'zerotier',
            'wireguard',
            'openvpn',
            'bluetooth',
            'teredo',
            'isatap',
            'npcap',
            'hamachi',
            'radmin',
            'sangfor',
            'fortinet',
            'anyconnect',
            'sonicwall',
            'globalprotect',
            'pulse secure',
            'nordlynx',
            'proton',
            'mullvad',
            'softether',
            'pptp',
            'l2tp',
        ],
    ],

    'kontak' => [
        'whatsapp_display' => '085156830304',
        'whatsapp_link' => 'https://wa.me/6285156830304',
        'instagram_display' => '@mukhamadirfan22',
        'instagram_link' => 'https://instagram.com/mukhamadirfan22',
        'pengembang' => 'IrfanDev97 — OPS Rejoso',
    ],
];
