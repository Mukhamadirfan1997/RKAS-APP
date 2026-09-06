<div x-data="breakReminder" x-init="init()" x-cloak>
    <!-- Modal istirahat — ala SmartRKAS, lembut tidak memaksa -->
    <div x-show="show" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center p-4" style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">
        <div x-show="show" x-transition.scale class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 shadow-2xl border border-slate-200 dark:border-slate-700/60 overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-emerald-400 via-blue-400 to-indigo-400"></div>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center text-2xl">☕</div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-extrabold text-slate-800 dark:text-white">Waktu istirahat sejenak</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">
                            Kamu sudah fokus <b x-text="workMinutes + ' menit'"></b>. Beri mata dan pikiran jeda <b x-text="breakMinutes + ' menit'"></b> — minum air, regangkan badan, lihat yang hijau. RKAS lebih rapi kalau kepala tidak jenuh.
                        </p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-2">Bisa diatur di Pengaturan → Profil (30/45/60/90 menit). Istirahat bukan malas, tapi biar fokusnya balik.</p>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-1 gap-2">
                    <button @click="startBreak()" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-md shadow-emerald-600/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="'Mulai istirahat ' + breakMinutes + ' menit'"></span>
                    </button>
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="snooze(10)" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Tunda 10 menit</button>
                        <button @click="disableToday()" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-sm font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Hari ini jangan ingatkan</button>
                    </div>
                    <button @click="snooze(10)" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 py-1">Lanjut dulu, nanti saja →</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Overlay istirahat berjalan — countdown -->
    <div x-show="breakCountdown > 0" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center p-4" style="background: rgba(15,23,42,0.72); backdrop-filter: blur(6px);">
        <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-800 shadow-2xl border border-slate-200 dark:border-slate-700/60 p-6 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-100 dark:bg-emerald-500/15 flex items-center justify-center text-2xl">🧘</div>
            <h3 class="mt-4 text-base font-extrabold text-slate-800 dark:text-white">Sedang istirahat</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Tarik napas, lihat jauh 20 detik, minum air.</p>
            <div class="mt-4 text-3xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400" x-text="String(Math.floor(breakCountdown/60)).padStart(2,'0') + ':' + String(breakCountdown%60).padStart(2,'0')"></div>
            <div class="mt-3 h-2 rounded-full bg-slate-100 dark:bg-slate-700/60 overflow-hidden">
                <div class="h-full bg-emerald-500 transition-all" :style="`width: ${(1 - breakCountdown/(breakMinutes*60))*100}%`"></div>
            </div>
            <button @click="skipBreak()" class="mt-5 w-full px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-semibold">Selesai — lanjut kerja</button>
            <p class="text-[11px] text-slate-400 mt-2">Otomatis selesai dalam <span x-text="breakCountdown"></span> detik</p>
        </div>
    </div>

    <!-- Toast selesai istirahat -->
    <div x-show="showDone" x-transition.opacity x-cloak class="fixed bottom-6 right-6 z-[80] max-w-sm rounded-xl bg-slate-800 dark:bg-slate-700 text-white px-4 py-3 shadow-xl flex items-center gap-3">
        <span class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center">✓</span>
        <div>
            <div class="text-sm font-bold">Istirahat selesai</div>
            <div class="text-xs text-slate-300">Mata segar lagi — lanjut susun RKAS dengan fokus!</div>
        </div>
    </div>

    <!-- Mini bar istirahat di pojok (ketika countdown) -->
    <div x-show="breakCountdown > 0" x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 px-4 py-2 rounded-full bg-emerald-600 text-white text-xs font-bold shadow-lg flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        Istirahat <span x-text="Math.floor(breakCountdown/60) + ':' + String(breakCountdown%60).padStart(2,'0')"></span>
        <button @click="skipBreak()" class="ml-2 px-2 py-0.5 rounded-full bg-white/20 hover:bg-white/30 text-xs">Lewati</button>
    </div>
</div>
