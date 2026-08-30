<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ARKAS 4.2.18 - Rencana Kegiatan Anggaran Sekolah' }}</title>
    
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
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="h-full text-slate-800 antialiased flex flex-col selection:bg-blue-600 selection:text-white">
    <!-- Top System Bar -->
    <header class="bg-white border-b border-slate-200 px-4 py-2 flex items-center justify-between shadow-xs select-none">
        <div class="flex items-center space-x-3">
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-xs">
                    A
                </div>
                <span class="text-xs font-bold tracking-tight text-slate-700">ARKAS 4.2.18</span>
            </div>
            <span class="text-slate-300">|</span>
            <span class="text-xs font-medium text-slate-500">{{ $sekolah->nama_sekolah ?? 'SD NEGERI TOYANING 1' }} (NPSN: {{ $sekolah->npsn ?? '20512345' }})</span>
        </div>
        <div class="flex items-center space-x-4 text-xs">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                Mode Desktop Offline
            </span>
            <span class="text-slate-400">Tahun Anggaran 2026</span>
            @auth
                <span class="text-slate-500 font-medium hidden sm:inline">{{ Auth::user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-400 hover:text-red-600 font-semibold">Keluar</button>
                </form>
            @endauth
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white border-b border-slate-200 px-4 py-1.5 flex items-center gap-1 select-none overflow-x-auto">
        @php
            $navItems = [
                'dashboard.index' => ['Dashboard', 'M'],
                'rkas.index' => ['Lembar Kerja', 'L'],
                'monitoring.juknis' => ['Monitoring JUKNIS', 'J'],
                'master.program' => ['Master Data', 'D'],
                'audit.index' => ['Audit Log', 'R'],
                'pengaturan.index' => ['Pengaturan', 'P'],
            ];
            $current = request()->route() ? request()->route()->getName() : '';
            $activeGroup = '';
            foreach (['dashboard.index', 'rkas.index', 'monitoring.juknis', 'master.program', 'audit.index', 'pengaturan.index'] as $nav) {
                if (str_starts_with($current, explode('.', $nav)[0] . '.')) { $activeGroup = $nav; break; }
                if ($current === $nav) { $activeGroup = $nav; break; }
            }
        @endphp
        @foreach($navItems as $routeName => [$label, $icon])
            @php
                $isActive = $current === $routeName || str_starts_with($current, explode('.', $routeName)[0] . '.');
                if ($routeName === 'master.program' && str_starts_with($current, 'master.')) $isActive = true;
            @endphp
            <a href="{{ route($routeName) }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors
                   {{ $isActive ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100' }}">
                <span class="w-5 h-5 rounded flex items-center justify-center text-[10px] font-bold {{ $isActive ? 'bg-blue-500' : 'bg-slate-100 text-slate-400' }}">{{ $icon }}</span>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</body>
</html>