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
            const intervalMin = parseInt(localStorage.getItem('karsa_break_interval') || '45', 10);
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
    snoozed: false,
    breakCountdown: 0,
    breakInterval: null,
    workMinutes: 45,
    init() {
        // interval dari localStorage atau 45, untuk demo ?breakTest=1 jadi 1 menit
        const params = new URLSearchParams(location.search);
        if (params.get('breakTest') === '1') {
            this.workMinutes = 1;
            localStorage.setItem('karsa_break_interval', '1');
        } else {
            this.workMinutes = parseInt(localStorage.getItem('karsa_break_interval') || '45', 10);
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
        this.breakCountdown = 5 * 60;
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