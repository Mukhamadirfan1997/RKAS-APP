import { driver } from "driver.js";
import "driver.js/dist/driver.css";

function shouldAutoStart(key) {
    try { return localStorage.getItem(key) !== '1'; } catch { return false; }
}
function isSeen(key) {
    try { return localStorage.getItem(key) === '1'; } catch { return false; }
}
function markSeen(key) {
    try { localStorage.setItem(key, '1'); } catch {}
}
function maybeChain(nextKey, nextPath, label) {
    try {
        if (!isSeen(nextKey)) {
            setTimeout(() => {
                // use native confirm for simplicity, offline safe
                if (confirm('Tur bagian ini selesai. Lanjutkan tur ke ' + label + '?')) {
                    window.location.href = nextPath;
                }
            }, 350);
        }
    } catch {}
}

function baseDriverOpts() {
    return {
        showProgress: true,
        progressText: '{{current}} dari {{total}}',
        nextBtnText: 'Lanjut →',
        prevBtnText: '← Kembali',
        doneBtnText: 'Selesai',
        allowClose: true,
        overlayColor: 'rgba(15,23,42,0.55)',
        popoverClass: 'karsa-tour-popover',
    };
}

// Dashboard tour — 4 langkah
function initDashboardTour() {
    const key = 'karsa_tour_dashboard_seen';
    const nextKey = 'karsa_tour_rkas_seen';
    const chainDoneText = !isSeen(nextKey) ? 'Lanjut ke Lembar Kerja →' : 'Selesai';
    const steps = [
        {
            element: '#tour-dashboard-juknis',
            popover: {
                title: 'Kepatuhan JUKNIS',
                description: 'Cek 4 aturan BOSP di sini. Warna hijau = sudah sesuai, kuning/merah = perlu diperbaiki.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: '#tour-dashboard-pagu',
            popover: {
                title: 'Pagu & Sisa Anggaran',
                description: 'Pagu total, yang sudah dianggarkan, dan sisa pagu. Sisa harus Rp 0 sebelum disahkan.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: '#tour-dashboard-kesiapan',
            popover: {
                title: 'Kesiapan RKAS',
                description: 'Skor kesiapan merangkum semua syarat. 100% artinya siap disahkan.',
                side: 'left',
                align: 'start',
            },
        },
        {
            element: '#tour-dashboard-lembar',
            popover: {
                title: 'Buka Lembar Kerja (4/4)',
                description: 'Klik di sini untuk mulai menyusun atau mengedit rincian anggaran. Tur berikutnya: Lembar Kerja.',
                side: 'left',
                align: 'center',
            },
        },
    ];
    const hasElements = steps.every(s => document.querySelector(s.element));
    if (!hasElements) return;
    const opts = { ...baseDriverOpts(), doneBtnText: chainDoneText };
    const d = driver({
        ...opts,
        steps,
        onDestroyStarted: () => { markSeen(key); d.destroy(); maybeChain(nextKey, '/rkas', 'Lembar Kerja'); },
    });
    if (shouldAutoStart(key)) {
        setTimeout(() => d.drive(), 800);
    }
    // expose manual trigger
    window.__karsaStartDashboardTour = () => {
        const dd = driver({ ...opts, steps, onDestroyStarted: () => { markSeen(key); dd.destroy(); maybeChain(nextKey, '/rkas', 'Lembar Kerja'); } });
        dd.drive();
    };
}

// Lembar Kerja tour — 5 langkah
function initRkasTour() {
    const key = 'karsa_tour_rkas_seen';
    const steps = [
        {
            element: '[data-tour="tambah-anggaran"]',
            popover: {
                title: 'Tambah Anggaran',
                description: 'Klik untuk menambah item baru. Isi kegiatan, rekening, uraian, volume dan harga.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: '#tour-rkas-filter',
            popover: {
                title: 'Filter Bulan',
                description: 'Pilih bulan untuk melihat rencana per bulan. “Semua” menampilkan 1 tahun penuh.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: '#tour-rkas-tabel',
            popover: {
                title: 'Tabel Rincian',
                description: 'Setiap baris adalah satu uraian belanja. Lihat volume, harga, total, dan badge Kontrol.',
                side: 'top',
                align: 'start',
            },
        },
        {
            element: '#tour-rkas-aksi',
            popover: {
                title: 'Menu Aksi (⋯)',
                description: 'Klik titik tiga untuk Lihat Detail, Sisip Serupa, Edit, atau Hapus.',
                side: 'left',
                align: 'center',
            },
        },
        {
            element: '#tour-rkas-cetak',
            popover: {
                title: 'Cetak / Unduh',
                description: 'Unduh PDF Lengkap, Per Tahap, Per Bulan, atau Excel Lengkap dari sini.',
                side: 'bottom',
                align: 'end',
            },
        },
    ];
    // filter out missing elements (aksi may not exist if empty table)
    const available = steps.filter(s => document.querySelector(s.element));
    if (available.length < 2) return;
    const nextKey = 'karsa_tour_monitoring_seen';
    const chainDoneText = !isSeen(nextKey) ? 'Lanjut ke Monitoring →' : 'Selesai';
    // update last step description to hint chaining
    if (!isSeen(nextKey) && available.length > 0) {
        const last = available[available.length - 1];
        if (last.popover) last.popover.description = 'Unduh PDF Lengkap, Per Tahap, Per Bulan, atau Excel Lengkap dari sini. Tur berikutnya: Monitoring.';
    }
    const opts = { ...baseDriverOpts(), doneBtnText: chainDoneText };
    const d = driver({
        ...opts,
        steps: available,
        onDestroyStarted: () => { markSeen(key); d.destroy(); maybeChain(nextKey, '/monitoring/juknis', 'Monitoring'); },
    });
    if (shouldAutoStart(key)) {
        setTimeout(() => d.drive(), 900);
    }
    window.__karsaStartRkasTour = () => {
        const dd = driver({ ...opts, steps: available, onDestroyStarted: () => { markSeen(key); dd.destroy(); maybeChain(nextKey, '/monitoring/juknis', 'Monitoring'); } });
        dd.drive();
    };
}

// Monitoring tour — 3 langkah
function initMonitoringTour() {
    const key = 'karsa_tour_monitoring_seen';
    const steps = [
        {
            element: '#tour-monitoring-ringkasan',
            popover: {
                title: 'Ringkasan Kepatuhan',
                description: 'Honor, Buku, Sarpras, dan Tahap I dihitung otomatis. Sesuaikan di Lembar Kerja jika belum sesuai.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            element: '#tour-monitoring-gelondongan',
            popover: {
                title: 'RKA Gelondongan',
                description: 'Rekap 3 kategori sesuai file Dinas. Isi target di Pengaturan → Pagu untuk cek selisih.',
                side: 'top',
                align: 'start',
            },
        },
        {
            element: '#tour-monitoring-pemetaan',
            popover: {
                title: 'Pemetaan Manual',
                description: 'Kalau perlu, atur sendiri kode rekening mana yang masuk kategori mana. Centang lalu Simpan.',
                side: 'bottom',
                align: 'start',
            },
        },
    ];
    const hasElements = steps.every(s => document.querySelector(s.element));
    if (!hasElements) return;
    // last step hint that this is the end
    if (steps.length > 0 && steps[steps.length-1].popover) {
        steps[steps.length-1].popover.description = 'Kalau perlu, atur sendiri kode rekening mana yang masuk kategori mana. Centang lalu Simpan. — Tur selesai! Ulangi kapan saja dari Tentang → Tur Ulang.';
    }
    const d = driver({
        ...baseDriverOpts(),
        doneBtnText: 'Selesai ✓',
        steps,
        onDestroyStarted: () => { markSeen(key); d.destroy(); },
    });
    if (shouldAutoStart(key)) {
        setTimeout(() => d.drive(), 800);
    }
    window.__karsaStartMonitoringTour = () => {
        const dd = driver({ ...baseDriverOpts(), doneBtnText: 'Selesai ✓', steps, onDestroyStarted: () => { markSeen(key); dd.destroy(); } });
        dd.drive();
    };
}

export function initTours() {
    const path = window.location.pathname;
    if (path === '/' || path.startsWith('/dashboard')) {
        initDashboardTour();
    } else if (path.startsWith('/rkas')) {
        // only on list page /rkas (not json)
        if (path === '/rkas' || path === '/rkas/') initRkasTour();
    } else if (path.startsWith('/monitoring')) {
        initMonitoringTour();
    }
}
