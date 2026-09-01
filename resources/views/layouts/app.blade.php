<!DOCTYPE html>
<html lang="id" class="h-full" x-data="appLayout()" :class="{'dark': dark}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'KARSA 2026 - Kertas Anggaran Sekolah' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full text-slate-800 antialiased bg-slate-50 dark:bg-slate-900 dark:text-slate-100 transition-colors duration-300">

    @php
        $current = request()->route() ? request()->route()->getName() : '';
        $navGroups = [
            'Kerja Harian' => [
                ['route' => 'dashboard.index', 'label' => 'Dashboard', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h14V10"/>', 'group' => 'dashboard'],
                ['route' => 'rkas.index', 'label' => 'Lembar Kerja', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>', 'group' => 'rkas'],
                ['route' => 'monitoring.juknis', 'label' => 'Monitoring JUKNIS', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>', 'group' => 'monitoring'],
            ],
            'Pengaturan & Kelola' => [
                ['route' => 'master.program', 'label' => 'Master Data', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>', 'group' => 'master'],
                ['route' => 'audit.index', 'label' => 'Audit Log', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'group' => 'audit'],
                ['route' => 'backup.index', 'label' => 'Backup', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>', 'group' => 'backup'],
                ['route' => 'pengaturan.index', 'label' => 'Pengaturan', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>', 'group' => 'pengaturan'],
            ],
        ];

        $isActiveOf = function ($group) use ($current) {
            return $current === $group . '.index' || str_starts_with($current, $group . '.');
        };
    @endphp

    <!-- Sidebar (desktop) -->
    <div class="flex h-full">
        <!-- Overlay mobile -->
        <div x-show="mobileOpen" x-cloak @click="mobileOpen = false" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"></div>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-white border-r border-slate-200 dark:bg-slate-800/95 dark:border-slate-700/60
                   transition-all duration-300
                   lg:static lg:z-auto
                   {{-- collapsed on desktop --}}
                   {{ '' }}"
            :class="collapsed && !mobileOpen ? '-translate-x-full lg:translate-x-0 lg:w-20' : (mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0')"
        >
            <!-- Brand -->
            <div class="flex items-center gap-3 px-5 h-16 border-b border-slate-200 dark:border-slate-700/60">
                <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-extrabold text-sm shadow-md shadow-blue-600/20 shrink-0">
                    K
                </div>
                <div x-show="!collapsed" x-cloak class="min-w-0">
                    <div class="font-extrabold text-slate-800 dark:text-white leading-tight truncate">KARSA 2026</div>
                    <div class="text-[10px] font-medium text-slate-400 dark:text-slate-400 truncate">{{ $sekolah->nama_sekolah ?? 'SD NEGERI TOYANING 1' }}</div>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
                @foreach($navGroups as $groupName => $items)
                    <div>
                        <div x-show="!collapsed" x-cloak class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $groupName }}</div>
                        <div class="space-y-1">
                            @foreach($items as $item)
                                @php $active = $isActiveOf($item['group']); @endphp
                                <a href="{{ route($item['route']) }}" x-data="{tip: false}" x-on:mouseenter="collapsed && (tip=true)" x-on:mouseleave="tip=false"
                                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors
                                          {{ $active ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700/50 dark:hover:text-slate-100' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['svg'] !!}</svg>
                                    <span x-show="!collapsed" x-cloak class="truncate">{{ $item['label'] }}</span>
                                    <span x-show="tip" x-cloak class="absolute left-full ml-2 px-2 py-1 rounded-md bg-slate-800 text-white text-xs whitespace-nowrap z-50 shadow-lg">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <!-- Footer / user -->
            <div class="px-3 py-4 border-t border-slate-200 dark:border-slate-700/60">
                @auth
                    <div class="flex items-center gap-3 px-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300 font-bold text-sm shrink-0">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div x-show="!collapsed" x-cloak class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate">{{ Auth::user()->name }}</div>
                            <form method="POST" action="{{ route('logout') }}" class="mt-0.5">
                                @csrf
                                <button type="submit" class="text-[11px] font-semibold text-slate-400 hover:text-red-600 dark:hover:text-red-400">Keluar</button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
            <!-- Topbar -->
            <header class="flex items-center justify-between gap-3 px-4 lg:px-6 h-16 bg-white dark:bg-slate-800/95 border-b border-slate-200 dark:border-slate-700/60 shrink-0">
                <div class="flex items-center gap-3">
                    <!-- Sidebar toggle -->
                    <button type="button" @click="toggleSidebar" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 dark:text-slate-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="hidden sm:flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Mode Desktop Offline
                        </span>
                        <span class="text-xs text-slate-400 dark:text-slate-500">Tahun Anggaran 2026</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Dark mode toggle -->
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

            <!-- Content -->
            <main class="flex-1 overflow-y-auto px-4 lg:px-8 py-6">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        function appLayout() {
            return {
                dark: false,
                collapsed: false,
                mobileOpen: false,
                init() {
                    this.dark = localStorage.getItem('rkas-theme') === 'dark'
                        || (!localStorage.getItem('rkas-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    this.applyTheme();
                },
                applyTheme() {
                    document.documentElement.classList.toggle('dark', this.dark);
                },
                toggleDark() {
                    this.dark = !this.dark;
                    localStorage.setItem('rkas-theme', this.dark ? 'dark' : 'light');
                    this.applyTheme();
                },
                toggleSidebar() {
                    if (window.innerWidth < 1024) {
                        this.mobileOpen = !this.mobileOpen;
                    } else {
                        this.collapsed = !this.collapsed;
                    }
                },
            };
        }
    </script>
</body>
</html>
