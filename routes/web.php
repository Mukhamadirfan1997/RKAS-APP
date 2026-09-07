<?php

use App\Http\Controllers\AktivasiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KatalogUpdateController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MonitoringJuknisController;
use App\Http\Controllers\PanduanController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\RkasController;
use App\Http\Controllers\RkasSearchController;
use App\Http\Controllers\TentangController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/lupa-password', [AuthController::class, 'showForgot'])->name('auth.forgot');
    Route::post('/lupa-password', [AuthController::class, 'handleForgot'])->middleware('throttle:5,1')->name('auth.forgot.attempt');
    Route::get('/reset-password', [AuthController::class, 'showReset'])->name('auth.reset');
    Route::post('/reset-password', [AuthController::class, 'handleReset'])->middleware('throttle:5,1')->name('auth.reset.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/rkas', [RkasController::class, 'index'])->name('rkas.index');
    Route::post('/rkas/store', [RkasController::class, 'store'])->middleware('cek.lisensi')->name('rkas.store');
    Route::get('/rkas/{id}/json', [RkasController::class, 'showJson'])->name('rkas.json');
    Route::post('/rkas/{id}/update', [RkasController::class, 'update'])->middleware('cek.lisensi')->name('rkas.update');
    Route::delete('/rkas/{id}/delete', [RkasController::class, 'destroy'])->middleware('cek.lisensi')->name('rkas.destroy');
    Route::get('/rkas/pdf', [RkasController::class, 'pdf'])->middleware('cek.lisensi')->name('rkas.pdf');
    Route::get('/rkas/pdf-grouped', [RkasController::class, 'pdfGrouped'])->middleware('cek.lisensi')->name('rkas.pdf-grouped');
    Route::get('/rkas/pdf-per-bulan', [RkasController::class, 'pdfPerBulan'])->middleware('cek.lisensi')->name('rkas.pdf-per-bulan');
    Route::get('/rkas/pdf-per-tahap', [RkasController::class, 'pdfPerTahap'])->middleware('cek.lisensi')->name('rkas.pdf-per-tahap');
    Route::get('/rkas/export', [RkasController::class, 'export'])->middleware('cek.lisensi')->name('rkas.export');
    Route::get('/rkas/export-grouped', [RkasController::class, 'exportGrouped'])->middleware('cek.lisensi')->name('rkas.export-grouped');

    // Backup & Restore
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/create', [BackupController::class, 'create'])->middleware('cek.lisensi')->name('backup.create');
    Route::post('/backup/restore', [BackupController::class, 'restore'])->middleware('cek.lisensi')->name('backup.restore');
    Route::post('/backup/{filename}/restore', [BackupController::class, 'restoreExisting'])->middleware('cek.lisensi')->name('backup.restore-file');
    Route::get('/backup/{filename}/download', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/backup/{filename}/delete', [BackupController::class, 'destroy'])->middleware('cek.lisensi')->name('backup.destroy');

    // Dashboard & Monitoring JUKNIS
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/monitoring/juknis', [MonitoringJuknisController::class, 'index'])->name('monitoring.juknis');
    Route::post('/monitoring/juknis/mapping', [MonitoringJuknisController::class, 'mapping'])->middleware('cek.lisensi')->name('monitoring.juknis.mapping');

    // Audit Log
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');

    // Pengaturan — 5 halaman terpisah (B)
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
    Route::get('/pengaturan/profil', [PengaturanController::class, 'indexProfil'])->name('pengaturan.profil');
    Route::get('/pengaturan/akun', [PengaturanController::class, 'indexAkun'])->name('pengaturan.akun');
    Route::get('/pengaturan/pagu', [PengaturanController::class, 'indexPagu'])->name('pengaturan.pagu');
    Route::get('/pengaturan/status', [PengaturanController::class, 'indexStatus'])->name('pengaturan.status');
    Route::get('/pengaturan/tahun', [PengaturanController::class, 'indexTahun'])->name('pengaturan.tahun.index');
    Route::post('/pengaturan/sekolah', [PengaturanController::class, 'updateSekolah'])->middleware('cek.lisensi')->name('pengaturan.update-sekolah');
    Route::post('/pengaturan/akun', [PengaturanController::class, 'updateAkun'])->middleware('cek.lisensi')->name('pengaturan.update-akun');
    Route::post('/pengaturan/pagu', [PengaturanController::class, 'updatePagu'])->middleware('cek.lisensi')->name('pengaturan.update-pagu');
    Route::post('/pengaturan/pengesahan/sahkan', [PengaturanController::class, 'sahkan'])->middleware('cek.lisensi')->name('pengaturan.pengesahan.sahkan');
    Route::post('/pengaturan/pengesahan/buka-kembali', [PengaturanController::class, 'bukaKembali'])->middleware('cek.lisensi')->name('pengaturan.pengesahan.buka-kembali');
    Route::post('/pengaturan/tahun', [PengaturanController::class, 'storeTahun'])->middleware('cek.lisensi')->name('pengaturan.tahun.store');
    Route::post('/pengaturan/tahun/{id}/aktifkan', [PengaturanController::class, 'activateTahun'])->middleware('cek.lisensi')->name('pengaturan.tahun.aktifkan');
    Route::delete('/pengaturan/tahun/{id}', [PengaturanController::class, 'destroyTahun'])->middleware('cek.lisensi')->name('pengaturan.tahun.destroy');
    Route::get('/pengaturan/katalog', [KatalogUpdateController::class, 'index'])->name('pengaturan.katalog');
    Route::post('/pengaturan/katalog/update', [KatalogUpdateController::class, 'update'])->middleware('cek.lisensi')->name('pengaturan.katalog.update');

    // Master Data
    Route::get('/master/program', [MasterDataController::class, 'program'])->name('master.program');
    Route::post('/master/program', [MasterDataController::class, 'storeProgram'])->middleware('cek.lisensi')->name('master.program.store');
    Route::post('/master/program/{id}/update', [MasterDataController::class, 'updateProgram'])->middleware('cek.lisensi')->name('master.program.update');
    Route::delete('/master/program/{id}/delete', [MasterDataController::class, 'destroyProgram'])->middleware('cek.lisensi')->name('master.program.destroy');

    Route::get('/master/rekening', [MasterDataController::class, 'rekening'])->name('master.rekening');
    Route::post('/master/rekening', [MasterDataController::class, 'storeRekening'])->middleware('cek.lisensi')->name('master.rekening.store');
    Route::post('/master/rekening/{id}/update', [MasterDataController::class, 'updateRekening'])->middleware('cek.lisensi')->name('master.rekening.update');
    Route::delete('/master/rekening/{id}/delete', [MasterDataController::class, 'destroyRekening'])->middleware('cek.lisensi')->name('master.rekening.destroy');

    Route::get('/master/barang', [MasterDataController::class, 'barang'])->name('master.barang');
    Route::post('/master/barang', [MasterDataController::class, 'storeBarang'])->middleware('cek.lisensi')->name('master.barang.store');
    Route::post('/master/barang/{id}/update', [MasterDataController::class, 'updateBarang'])->middleware('cek.lisensi')->name('master.barang.update');
    Route::delete('/master/barang/{id}/delete', [MasterDataController::class, 'destroyBarang'])->middleware('cek.lisensi')->name('master.barang.destroy');
    Route::post('/master/import', [MasterDataController::class, 'import'])->middleware('cek.lisensi')->name('master.import');

    // Tentang & Panduan
    Route::get('/tentang', [TentangController::class, 'index'])->name('tentang.index');
    Route::get('/panduan', [PanduanController::class, 'index'])->name('panduan.index');

    // Aktivasi Lisensi (harus bisa diakses bahkan saat read-only, jadi tanpa cek.lisensi)
    Route::get('/aktivasi', [AktivasiController::class, 'index'])->name('aktivasi.index');
    Route::post('/aktivasi', [AktivasiController::class, 'activate'])->name('aktivasi.activate');

    // Cek & Unduh Update Aplikasi (SmartRKAS pola, Laravel-only tanpa Rust)
    Route::get('/update/cek', [UpdateController::class, 'cek'])->name('update.cek');
    Route::post('/update/unduh', [UpdateController::class, 'unduh'])->name('update.unduh');
    Route::get('/update/status-unduhan', [UpdateController::class, 'status'])->name('update.status');
    Route::get('/update/download-file/{nama}', [UpdateController::class, 'downloadFile'])->name('update.download');

    // Live Search Endpoints (SmartRKAS style autocomplete)
    Route::get('/api/search/kegiatan', [RkasSearchController::class, 'searchKegiatan'])->name('api.search.kegiatan');
    Route::get('/api/search/rekening', [RkasSearchController::class, 'searchRekening'])->name('api.search.rekening');
    Route::get('/api/search/barang', [RkasSearchController::class, 'searchBarang'])->name('api.search.barang');
});
