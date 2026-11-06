@php
    // Data navigasi dipakai dua tempat (sidebar & menu topbar) dari satu sumber.
    $navGroups = $navGroups ?? \App\Support\Navigasi::grup();
    $currentTop = request()->route() ? request()->route()->getName() : '';
    $menuAktif = fn ($group) => \App\Support\Navigasi::aktif($group, $currentTop);
    $labelHalaman = \App\Support\Navigasi::labelAktif($currentTop);
@endphp

<header class="flex items-center justify-between gap-3 px-4 lg:px-6 h-16 bg-white dark:bg-slate-800/95 border-b border-slate-200 dark:border-slate-700/60 shrink-0">
    <div class="flex items-center gap-3 min-w-0">
        @if(!($hideSidebarToggle ?? false))
        <button type="button" @click="toggleSidebar" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 dark:text-slate-300" title="Lipat sidebar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        @endif

        @if($labelHalaman)
            <span class="hidden md:inline text-sm font-bold text-slate-700 dark:text-slate-200 truncate">{{ $labelHalaman }}</span>
        @endif

        <div class="hidden sm:flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Mode Desktop Offline
            </span>
            @php
                $tahunAktifTop = \App\Models\TahunAnggaran::where('is_active', true)->first() ?? \App\Models\TahunAnggaran::where('tahun', (int) date('Y'))->first();
                $daftarTahunTop = \App\Models\TahunAnggaran::orderBy('tahun','desc')->get();
                $curTahunTop = request('tahun') ? (int)request('tahun') : ($tahunAktifTop->tahun ?? (int) date('Y'));
            @endphp
            <select onchange="const u=new URL(window.location.href); u.searchParams.set('tahun', this.value); window.location.href=u.toString()" class="text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 focus:ring-1 focus:ring-blue-500">
                @foreach($daftarTahunTop as $tOpt)
                    <option value="{{ $tOpt->tahun }}" @if($curTahunTop==$tOpt->tahun) selected @endif>TA {{ $tOpt->tahun }} @if($tOpt->is_active) • Aktif @endif — {{ $tOpt->status_pengesahan }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="hidden lg:flex items-center gap-2" x-data="karsaClock" x-init="init()">
        <div class="hidden xl:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="tabular-nums" x-text="time">--:--:--</span>
            <span class="w-px h-4 bg-slate-300 dark:bg-slate-600"></span>
            <span class="font-normal text-slate-500 dark:text-slate-400" x-text="date"></span>
        </div>
        <div class="flex lg:hidden items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 text-xs font-bold tabular-nums text-slate-600 dark:text-slate-300">
            <span x-text="time.slice(0,5)"></span>
        </div>
        <div class="hidden xl:flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
            <span class="w-2 h-2 rounded-full" :class="breakColor"></span>
            <span x-text="breakHint"></span>
        </div>
    </div>

    <div class="flex items-center gap-1.5" x-data="{ menu: false }">
        <button type="button" @click="toggleDark" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 dark:text-slate-300" title="Ganti tema">
            <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </button>

        @auth
        {{-- Satu menu: semua navigasi + satu tombol Keluar --}}
        <div class="relative" @click.outside="menu = false" @keydown.escape.window="menu = false">
            <button type="button" @click="menu = !menu"
                class="flex items-center gap-2 pl-1.5 pr-2 sm:pr-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors"
                :aria-expanded="menu" aria-haspopup="true" title="Menu aplikasi">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300 font-bold text-xs shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </span>
                <span class="hidden sm:block max-w-[9rem] text-left">
                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-200 truncate leading-tight">{{ Auth::user()->name }}</span>
                    <span class="block text-[10px] text-slate-400 truncate leading-tight">{{ Auth::user()->email }}</span>
                </span>
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="menu ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="menu" x-cloak x-transition.opacity.duration.120ms
                class="absolute right-0 mt-2 w-72 max-h-[calc(100vh-5rem)] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl shadow-slate-900/10 z-50">

                <div class="px-3 pt-3 pb-2">
                    <a href="{{ route('pengaturan.akun') }}" @click="menu = false"
                        class="flex items-center gap-2 px-2.5 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Akun &amp; Kata Sandi
                    </a>
                </div>

                @foreach($navGroups as $groupName => $items)
                    <div class="px-3 pt-1 pb-2 {{ !$loop->first ? 'border-t border-slate-100 dark:border-slate-700/60 mt-1' : '' }}">
                        <div class="px-2.5 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $groupName }}</div>
                        @foreach($items as $item)
                            @php $aktif = $menuAktif($item['group']); @endphp
                            <a href="{{ route($item['route']) }}" @click="menu = false"
                                class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm font-semibold transition-colors
                                       {{ $aktif ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white' }}">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['svg'] !!}</svg>
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach

                <div class="p-3 border-t border-slate-100 dark:border-slate-700/60">
                    <form method="POST" action="{{ route('logout') }}" onsubmit="try{localStorage.setItem('karsa_break_last', String(Date.now())); localStorage.removeItem('karsa_break_snooze');}catch(e){}">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 dark:text-red-400 dark:bg-red-500/10 dark:hover:bg-red-500/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endauth
    </div>
</header>
