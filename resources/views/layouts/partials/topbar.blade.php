<header class="flex items-center justify-between gap-3 px-4 lg:px-6 h-16 bg-white dark:bg-slate-800/95 border-b border-slate-200 dark:border-slate-700/60 shrink-0">
    <div class="flex items-center gap-3">
        @if(!($hideSidebarToggle ?? false))
        <button type="button" @click="toggleSidebar" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 dark:text-slate-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        @endif
        <div class="hidden sm:flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Mode Desktop Offline
            </span>
            @php
                $tahunAktifTop = \App\Models\TahunAnggaran::where('is_active', true)->first() ?? \App\Models\TahunAnggaran::where('tahun', 2026)->first();
                $daftarTahunTop = \App\Models\TahunAnggaran::orderBy('tahun','desc')->get();
                $curTahunTop = request('tahun') ? (int)request('tahun') : ($tahunAktifTop->tahun ?? 2026);
            @endphp
            <select onchange="const u=new URL(window.location.href); u.searchParams.set('tahun', this.value); window.location.href=u.toString()" class="text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 focus:ring-1 focus:ring-blue-500">
                @foreach($daftarTahunTop as $tOpt)
                    <option value="{{ $tOpt->tahun }}" @if($curTahunTop==$tOpt->tahun) selected @endif>TA {{ $tOpt->tahun }} @if($tOpt->is_active) • Aktif @endif — {{ $tOpt->status_pengesahan }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" @click="toggleDark" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 dark:text-slate-300" title="Ganti tema">
            <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </button>
        @auth
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-red-600 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-red-400" title="Keluar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span class="hidden sm:inline">Keluar</span>
            </button>
        </form>
        @endauth
    </div>
</header>
