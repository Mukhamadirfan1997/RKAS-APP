import './bootstrap';
import Alpine from 'alpinejs';
import { initTours } from './tours.js';

window.Alpine = Alpine;

// Jam real-time + pengingat istirahat ala SmartRKAS (anti-jenuh)
Alpine.data('karsaClock', () => ({
    time: '--:--',
    date: '',
    breakHint: '',
    breakColor: 'bg-emerald-500',
    interval: null,
    breakTimer: null,
    init() {
        this.tick();
        this.interval = setInterval(() => this.tick(), 1000);
        // update hint tiap 30 detik
        this.updateBreakHint();
        this.breakTimer = setInterval(() => this.updateBreakHint(), 30000);
    },
    tick() {
        const now = new Date();
        this.time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
        this.date = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
    },
    updateBreakHint() {
        try {
            const disabled = localStorage.getItem('karsa_break_disabled') === new Date().toISOString().slice(0,10);
            if (disabled) { this.breakHint = 'Istirahat dinonaktifkan hari ini'; this.breakColor = 'bg-slate-400'; return; }
            const last = parseInt(localStorage.getItem('karsa_break_last') || '0', 10);
            const snooze = parseInt(localStorage.getItem('karsa_break_snooze') || '0', 10);
            const now = Date.now();
            if (snooze && now < snooze) {
                const mins = Math.ceil((snooze - now)/60000);
                this.breakHint = `Ditunda ${mins} menit`;
                this.breakColor = 'bg-amber-500';
                return;
            }
            const intervalMin = parseInt(localStorage.getItem('karsa_break_interval') || '60', 10);
            if (intervalMin <= 0) { this.breakHint = 'Istirahat nonaktif'; this.breakColor = 'bg-slate-400'; return; }
            if (!last) {
                localStorage.setItem('karsa_break_last', String(now));
                this.breakHint = `Fokus • istirahat dalam ${intervalMin} menit`;
                this.breakColor = 'bg-emerald-500';
                return;
            }
            const elapsed = (now - last) / 60000;
            const remain = Math.max(0, intervalMin - elapsed);
            if (remain <= 0) {
                this.breakHint = 'Waktunya istirahat sejenak ☕';
                this.breakColor = 'bg-amber-500 animate-pulse';
            } else if (remain <= 5) {
                this.breakHint = `Istirahat dalam ${Math.ceil(remain)} menit`;
                this.breakColor = 'bg-amber-500';
            } else {
                this.breakHint = `Fokus • istirahat dalam ${Math.ceil(remain)} menit`;
                this.breakColor = 'bg-emerald-500';
            }
        } catch { this.breakHint = ''; }
    }
}));

Alpine.data('breakReminder', () => ({
    show: false,
    showDone: false,
    snoozed: false,
    breakCountdown: 0,
    breakInterval: null,
    workMinutes: 60,
    breakMinutes: 5,
    pageLoadAt: Date.now(),
    init() {
        // interval dari localStorage atau 60 (default baru), durasi 5/10; ?breakTest=1 jadi 1 menit demo
        const params = new URLSearchParams(location.search);
        if (params.get('breakTest') === '1') {
            this.workMinutes = 1;
            this.breakMinutes = 1;
            localStorage.setItem('karsa_break_interval', '1');
            localStorage.setItem('karsa_break_duration', '1');
        } else {
            this.workMinutes = parseInt(localStorage.getItem('karsa_break_interval') || '60', 10);
            this.breakMinutes = parseInt(localStorage.getItem('karsa_break_duration') || '5', 10);
            if (this.breakMinutes <= 0) this.breakMinutes = 5;
        }
        // jika interval 0/nonaktif, jangan tampilkan sama sekali
        if (this.workMinutes <= 0) {
            this.workMinutes = 0;
            return;
        }
        if (!localStorage.getItem('karsa_break_last')) {
            localStorage.setItem('karsa_break_last', String(Date.now()));
        }
        this.check();
        setInterval(() => this.check(), 30000);
        // reset timer on user activity (scroll/click) debounce
        let t;
        const bump = () => {
            clearTimeout(t);
            t = setTimeout(() => {
                // jangan reset jika sedang istirahat
                if (!this.show && !this.breakCountdown) {
                    // optional: bisa perpanjang, tapi kita biarkan wall-time saja
                }
            }, 1000);
        };
        window.addEventListener('click', bump, { passive: true });
        window.addEventListener('keydown', bump);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.check();
        });
    },
    check() {
        try {
            if (this.workMinutes <= 0) return;
            // Grace 3 menit setelah load — biar tidak kaget langsung setelah login
            if (Date.now() - this.pageLoadAt < 3 * 60 * 1000) return;
            const today = new Date().toISOString().slice(0,10);
            if (localStorage.getItem('karsa_break_disabled') === today) return;
            const snooze = parseInt(localStorage.getItem('karsa_break_snooze') || '0', 10);
            if (snooze && Date.now() < snooze) return;
            const last = parseInt(localStorage.getItem('karsa_break_last') || '0', 10);
            const elapsed = (Date.now() - (last || Date.now())) / 60000;
            if (elapsed >= this.workMinutes) {
                this.show = true;
                // gentle chime via Web Audio (offline, tanpa file)
                this.chime();
            }
        } catch {}
    },
    chime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.08;
            o.connect(g); g.connect(ctx.destination);
            o.start(); g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.6);
            setTimeout(() => { o.stop(); ctx.close(); }, 600);
        } catch {}
    },
    snooze(min = 10) {
        localStorage.setItem('karsa_break_snooze', String(Date.now() + min*60000));
        this.show = false;
    },
    disableToday() {
        localStorage.setItem('karsa_break_disabled', new Date().toISOString().slice(0,10));
        this.show = false;
    },
    startBreak() {
        const dur = Math.max(1, parseInt(this.breakMinutes || 5, 10));
        this.breakCountdown = dur * 60;
        this.show = false;
        localStorage.setItem('karsa_break_last', String(Date.now()));
        if (this.breakInterval) clearInterval(this.breakInterval);
        this.breakInterval = setInterval(() => {
            this.breakCountdown--;
            if (this.breakCountdown <= 0) {
                clearInterval(this.breakInterval);
                this.breakInterval = null;
                localStorage.setItem('karsa_break_last', String(Date.now()));
                // notifikasi selesai
                this.showDone = true;
                setTimeout(() => this.showDone = false, 4000);
            }
        }, 1000);
    },
    skipBreak() {
        if (this.breakInterval) { clearInterval(this.breakInterval); this.breakInterval = null; }
        this.breakCountdown = 0;
        localStorage.setItem('karsa_break_last', String(Date.now()));
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    try { initTours(); } catch {}
});

/*
 * Notifikasi & dialog simpan untuk semua tombol unduh.
 * - Desktop (Tauri/WebView2): dialog "Simpan sebagai" (tombol Simpan/Batal).
 * - Browser (mode dev): dialog unduhan bawaan browser; toast hanya penanda.
 */
(function () {
    const POLA_UNDUH = [
        /\/backup\/[^/]+\/download(\?|$)/,
        /\/rkas\/pdf/,
        /\/rkas\/export/,
        /\/update\/download-file\//,
    ];

    const tauriInternals = (typeof window !== 'undefined' && window.__TAURI_INTERNALS__)
        ? window.__TAURI_INTERNALS__
        : null;
    const bisaDialogDesktop = !!(tauriInternals && typeof tauriInternals.invoke === 'function');

    let sedangMenyimpan = false;

    document.addEventListener('click', async (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
        const anchor = event.target.closest('a[href]');
        if (!anchor) return;
        if (anchor.dataset.karsaBypass) return;

        const href = anchor.getAttribute('href') || '';
        const cocok = anchor.hasAttribute('download') || POLA_UNDUH.some((pola) => pola.test(href));
        if (!cocok) return;

        const label = (anchor.dataset.downloadLabel || anchor.textContent || 'berkas')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 60) || 'berkas';

        if (!bisaDialogDesktop) {
            if (window.karsaToast) {
                window.karsaToast('Mengunduh ' + label + '… Jika muncul dialog, pilih lokasi penyimpanan.', 'info', 9000);
            }
            return; // browser menangani dialog unduhannya sendiri
        }

        event.preventDefault();
        if (sedangMenyimpan) return;
        sedangMenyimpan = true;

        if (window.karsaToast) {
            window.karsaToast('Menyiapkan ' + label + ' — pilih lokasi penyimpanan…', 'info', 6000);
        }

        try {
            const hasil = await tauriInternals.invoke('simpan_unduhan', {
                url: new URL(anchor.href, window.location.href).toString(),
            });

            if (hasil) {
                if (window.karsaToast) window.karsaToast('Berkas tersimpan di: ' + hasil, 'success', 12000);
            } else if (window.karsaToast) {
                window.karsaToast('Penyimpanan dibatalkan.', 'info', 6000);
            }
        } catch (e) {
            const pesan = (e && (e.message || e.toString())) || 'tidak diketahui';
            if (window.karsaToast) {
                window.karsaToast('Dialog simpan tidak tersedia (' + pesan + '). Mengunduh lewat cara bawaan…', 'error', 12000);
            }

            // Jaring pengaman: bila command desktop ditolak/di luar kendali,
            // teruskan ke perilaku unduh bawaan agar pengguna tidak buntu.
            anchor.dataset.karsaBypass = '1';
            anchor.click();
            setTimeout(() => { delete anchor.dataset.karsaBypass; }, 0);
        } finally {
            sedangMenyimpan = false;
        }
    }, true);
})();

/*
 * Pemasangan update otomatis (khusus aplikasi desktop Tauri).
 * Alur: cek versi -> unduh + verifikasi tanda tangan -> pasang -> aplikasi
 * menutup dan terbuka kembali dengan versi baru.
 * handlers: { onProgress({tahap, persen, terunduh, total, versi}) }
 */
window.karsaDesktop = !!(typeof window !== 'undefined'
    && window.__TAURI_INTERNALS__
    && typeof window.__TAURI_INTERNALS__.invoke === 'function');

window.karsaPasangUpdate = async function (handlers = {}) {
    if (!window.karsaDesktop) {
        throw new Error('Pemasangan otomatis hanya tersedia di aplikasi desktop.');
    }

    let berhenti = false;
    const timer = setInterval(async () => {
        if (berhenti) return;
        try {
            const status = await window.__TAURI_INTERNALS__.invoke('status_update');
            if (!berhenti && handlers.onProgress) handlers.onProgress(status || {});
        } catch (e) {
            /* progres hanya pelengkap; abaikan bila belum siap */
        }
    }, 400);

    try {
        return await window.__TAURI_INTERNALS__.invoke('perbarui_sekarang');
    } finally {
        berhenti = true;
        clearInterval(timer);
    }
};

/*
 * Unduh installer update dengan progres nyata (NDJSON stream dari server).
 * handlers: { onProgress(msg), onDone(msg), onError(msg) }
 */
window.karsaUnduhUpdate = async function (urlUnduh, handlers = {}) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const resp = await fetch('/update/unduh', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/x-ndjson',
            'X-Progress-Stream': '1',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ url_unduh: urlUnduh }),
    });

    if (!resp.ok) {
        const teks = await resp.text();
        const err = { success: false, message: 'Gagal mengunduh (HTTP ' + resp.status + '). ' + teks.slice(0, 140) };
        handlers.onError?.(err);
        return err;
    }

    // Server lama/tanpa stream: balasan JSON biasa.
    if (!(resp.headers.get('content-type') || '').includes('ndjson') || !resp.body) {
        const data = await resp.json().catch(() => ({ success: false, message: 'Respons unduhan tidak valid.' }));
        if (data.success) handlers.onDone?.(data);
        else handlers.onError?.(data);
        return data;
    }

    const reader = resp.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let terakhir = null;

    const proses = (line) => {
        if (!line) return;
        let msg;
        try { msg = JSON.parse(line); } catch { return; }
        terakhir = msg;
        if (msg.phase === 'start' || msg.phase === 'progress') handlers.onProgress?.(msg);
        else if (msg.phase === 'done') handlers.onDone?.(msg);
        else if (msg.phase === 'error') handlers.onError?.(msg);
    };

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        let idx;
        while ((idx = buffer.indexOf('\n')) >= 0) {
            proses(buffer.slice(0, idx).trim());
            buffer = buffer.slice(idx + 1);
        }
    }
    proses(buffer.trim());

    return terakhir;
};
