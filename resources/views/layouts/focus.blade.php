<!DOCTYPE html>
<html lang="id" class="h-full" x-data="appLayout()" :class="{'dark': dark}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'KARSA - Kertas Kerja RKAS' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full text-slate-800 antialiased bg-slate-50 dark:bg-slate-900 dark:text-slate-100 transition-colors duration-300">
    <div class="flex flex-col h-full">
        @include('layouts.partials.topbar', ['hideSidebarToggle' => true])
        <main class="flex-1 overflow-y-auto px-4 lg:px-10 py-6">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
    @include('layouts.partials.break-reminder')
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
                applyTheme() { document.documentElement.classList.toggle('dark', this.dark); },
                toggleDark() {
                    this.dark = !this.dark;
                    localStorage.setItem('rkas-theme', this.dark ? 'dark' : 'light');
                    this.applyTheme();
                },
                toggleSidebar() {
                    if (window.innerWidth < 1024) { this.mobileOpen = !this.mobileOpen; } else { this.collapsed = !this.collapsed; }
                },
            };
        }
    </script>
</body>
</html>
