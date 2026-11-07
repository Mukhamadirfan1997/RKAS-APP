<!DOCTYPE html>
<html lang="id" class="h-full" x-data="appLayout()" :class="{'dark': dark}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'KARSA - Kertas Kerja RKAS' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/logo.png') }}">

    {{-- Font Inter & Outfit di-host lokal (aplikasi offline, tanpa Google Fonts) --}}

    @auth
    <script>
        window.karsaToursSeen = {
            dashboard: {{ Auth::user()->tour_dashboard_seen ? 'true' : 'false' }},
            rkas: {{ Auth::user()->tour_rkas_seen ? 'true' : 'false' }},
            monitoring: {{ Auth::user()->tour_monitoring_seen ? 'true' : 'false' }}
        };
    </script>
    @endauth
    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full text-slate-800 antialiased bg-slate-50 dark:bg-slate-900 dark:text-slate-100 transition-colors duration-300">

    @php
        $current = request()->route() ? request()->route()->getName() : '';
        $sidebarItems = \App\Support\Navigasi::sidebar();
        $isActiveOf = fn ($group) => \App\Support\Navigasi::aktif($group, $current);
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
                <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-white dark:bg-slate-700 p-0.5 shadow-md shadow-blue-600/10 shrink-0 border border-slate-200/60 dark:border-slate-600 overflow-hidden">
                    <img src="{{ asset('icons/logo.png') }}" alt="KARSA" class="w-full h-full object-contain">
                </div>
                <div x-show="!collapsed" x-cloak class="min-w-0">
                    <div class="font-display font-extrabold text-slate-800 dark:text-white text-base tracking-wide leading-tight truncate">KARSA</div>
                    <div class="text-[10px] font-medium text-slate-400 dark:text-slate-400 truncate">{{ $sekolah->nama_sekolah ?? 'Profil sekolah belum diisi' }}</div>
                </div>
            </div>

            <!-- Nav (hanya menu kerja harian: Dashboard & Lembar Kerja; menu lainnya ada di dropdown profil topbar) -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                <div x-show="!collapsed" x-cloak class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Menu Utama</div>
                <div class="space-y-1">
                    @foreach($sidebarItems as $item)
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
            </nav>

            <!-- Footer / user (tombol Keluar ada di menu topbar, satu pintu) -->
            <div class="px-3 py-4 border-t border-slate-200 dark:border-slate-700/60">
                @auth
                    <div class="flex items-center gap-3 px-3">
                        <div class="flex items-center justify-center w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300 font-bold text-sm shrink-0">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div x-show="!collapsed" x-cloak class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate">{{ Auth::user()->name }}</div>
                            <a href="{{ route('pengaturan.akun') }}" class="text-[11px] font-semibold text-slate-400 hover:text-blue-600 dark:hover:text-blue-400">Akun &amp; kata sandi</a>
                        </div>
                    </div>
                @endauth
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
            @include('layouts.partials.topbar')
            @include('layouts.partials.lisensi-banner')
            @include('layouts.partials.database-health-banner')
            @include('layouts.partials.katalog-kosong-banner')

            <!-- Content -->
            <main class="flex-1 overflow-y-auto px-4 lg:px-8 py-6">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    @include('layouts.partials.break-reminder')
    @include('layouts.partials.toast')
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
