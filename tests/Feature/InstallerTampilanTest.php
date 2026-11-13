<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga tampilan installer desktop (NSIS).
 *
 * Installer versi lama memakai teks bawaan Tauri berbahasa Inggris tanpa
 * identitas KARSA. Sejak 13 Nov 2026 installer memakai:
 *   - halaman "Tentang KARSA" (bundle.licenseFile) berisi ringkasan aplikasi;
 *   - panel samping & gambar kepala bernuansa KARSA;
 *   - bahasa installer Indonesia (languages + customLanguageFiles).
 *
 * Bila salah satu berkas hilang atau namanya berubah, build installer akan
 * gagal atau tampil kosong. Test ini mengunci semuanya lebih awal.
 */
class InstallerTampilanTest extends TestCase
{
    /**
     * Seluruh LangString yang dirujuk template NSIS milik Tauri.
     *
     * Berkas `customLanguageFiles` MENGGANTIKAN terjemahan bawaan Tauri, jadi
     * satu nama yang hilang = teks kosong di layar installer. Daftar ini
     * diambil dari berkas yang dihasilkan Tauri saat build (English.nsh).
     */
    private const LANGSTRING_WAJIB = [
        'addOrReinstall',
        'alreadyInstalled',
        'alreadyInstalledLong',
        'appRunning',
        'appRunningOkKill',
        'chooseMaintenanceOption',
        'choowHowToInstall',
        'createDesktop',
        'dontUninstall',
        'dontUninstallDowngrade',
        'failedToKillApp',
        'installingWebview2',
        'newerVersionInstalled',
        'older',
        'olderOrUnknownVersionInstalled',
        'silentDowngrades',
        'unableToUninstall',
        'uninstallApp',
        'uninstallBeforeInstalling',
        'unknown',
        'webview2AbortError',
        'webview2DownloadError',
        'webview2DownloadSuccess',
        'webview2Downloading',
        'webview2InstallError',
        'webview2InstallSuccess',
        'deleteAppData',
    ];

    private function konfigurasi(): array
    {
        $path = base_path('src-tauri/tauri.conf.json');
        $this->assertFileExists($path, 'tauri.conf.json tidak ditemukan.');

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function nsis(): array
    {
        $nsis = $this->konfigurasi()['bundle']['windows']['nsis'] ?? [];
        $this->assertIsArray($nsis, 'Bagian bundle.windows.nsis tidak ditemukan.');

        return $nsis;
    }

    private function berkasInstaller(string $relatif): string
    {
        $path = base_path('src-tauri/'.$relatif);
        $this->assertFileExists($path, "Berkas installer hilang: src-tauri/{$relatif}");

        return $path;
    }

    public function test_halaman_informasi_karsa_terpasang_pada_installer(): void
    {
        $license = $this->konfigurasi()['bundle']['licenseFile'] ?? null;
        $this->assertSame('installer/KARSA-Info.rtf', $license, 'Installer tidak memakai halaman informasi KARSA.');

        $path = $this->berkasInstaller($license);
        $isi = (string) file_get_contents($path);

        $this->assertStringStartsWith('{\rtf1', $isi, 'Halaman informasi harus berupa RTF yang sah.');
        $this->assertStringEndsWith("}\n", $isi, 'RTF tidak ditutup dengan benar.');
        $this->assertStringContainsString('KARSA', $isi);
        $this->assertStringContainsString('Kertas Anggaran Sekolah', $isi);
        $this->assertStringContainsString('JUKNIS BOSP', $isi);
    }

    public function test_gambar_dan_ikon_installer_ada_dengan_ukuran_benar(): void
    {
        $nsis = $this->nsis();

        $this->assertSame('installer/sidebar.bmp', $nsis['sidebarImage'] ?? null);
        $this->assertSame('installer/header.bmp', $nsis['headerImage'] ?? null);
        $this->assertSame('installer/header.bmp', $nsis['uninstallerHeaderImage'] ?? null);
        $this->assertSame('icons/icon.ico', $nsis['installerIcon'] ?? null);
        $this->assertSame('icons/icon.ico', $nsis['uninstallerIcon'] ?? null);

        // MUI mensyaratkan ukuran persis; gambar meleset = tampilan terpotong/gepeng.
        $samping = getimagesize($this->berkasInstaller($nsis['sidebarImage']));
        $this->assertSame(164, $samping[0], 'Panel samping installer harus 164 px.');
        $this->assertSame(314, $samping[1], 'Panel samping installer harus 314 px.');
        $this->assertSame(IMAGETYPE_BMP, $samping[2], 'Panel samping harus berupa BMP.');

        $kepala = getimagesize($this->berkasInstaller($nsis['headerImage']));
        $this->assertSame(150, $kepala[0], 'Gambar kepala installer harus 150 px.');
        $this->assertSame(57, $kepala[1], 'Gambar kepala installer harus 57 px.');
        $this->assertSame(IMAGETYPE_BMP, $kepala[2], 'Gambar kepala harus berupa BMP.');

        $this->berkasInstaller($nsis['installerIcon']);
    }

    public function test_bahasa_installer_indonesia_dan_lengkap(): void
    {
        $nsis = $this->nsis();

        $this->assertSame(['Indonesian'], $nsis['languages'] ?? null, 'Installer harus berbahasa Indonesia.');

        $berkas = $nsis['customLanguageFiles']['Indonesian'] ?? null;
        $this->assertSame('installer/bahasa-installer.nsh', $berkas);

        $isi = (string) file_get_contents($this->berkasInstaller($berkas));
        $this->assertStringContainsString('${LANG_INDONESIAN}', $isi);

        foreach (self::LANGSTRING_WAJIB as $nama) {
            $this->assertMatchesRegularExpression(
                '/^LangString '.preg_quote($nama, '/').'\s+\$\{LANG_INDONESIAN\}/m',
                $isi,
                "Pesan installer '{$nama}' belum diterjemahkan — teks akan kosong di layar."
            );
        }

        // Halaman Modern UI yang sengaja disesuaikan untuk KARSA.
        foreach (['MUI_TEXT_WELCOME_INFO_TITLE', 'MUI_TEXT_WELCOME_INFO_TEXT', 'MUI_TEXT_LICENSE_TITLE', 'MUI_TEXT_FINISH_INFO_TEXT'] as $nama) {
            $this->assertMatchesRegularExpression(
                '/^LangString '.preg_quote($nama, '/').'\s+\$\{LANG_INDONESIAN\}/m',
                $isi,
                "Teks halaman '{$nama}' belum disesuaikan."
            );
        }

        $this->assertStringContainsString('Tentang KARSA', $isi);
        $this->assertStringContainsString('Kertas Anggaran Sekolah', $isi);
    }

    public function test_hook_nsis_tetap_terdaftar_untuk_pembaruan(): void
    {
        // Hook ini yang mematikan proses KARSA/php.exe sebelum berkas ditimpa.
        $this->assertSame('./installer.nsh', $this->nsis()['installerHooks'] ?? null);
        $this->berkasInstaller('installer.nsh');
    }
}
