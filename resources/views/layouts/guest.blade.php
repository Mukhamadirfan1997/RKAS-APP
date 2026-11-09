<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Masuk') - KARSA</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .login-bg {
            background-color: #072666;
            background-image: radial-gradient(circle at 50% 35%, #0d3885 0%, #06235e 60%, #031438 100%);
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center login-bg p-4 relative overflow-y-auto select-none py-4 sm:py-8">

    <!-- Background Constellation Network -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" viewBox="0 0 1440 900">
            <defs>
                <radialGradient id="node-gold" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#fbbf24" stop-opacity="1" />
                    <stop offset="35%" stop-color="#f59e0b" stop-opacity="0.8" />
                    <stop offset="100%" stop-color="#d97706" stop-opacity="0" />
                </radialGradient>
                <radialGradient id="node-cyan" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#67e8f9" stop-opacity="1" />
                    <stop offset="40%" stop-color="#06b6d4" stop-opacity="0.7" />
                    <stop offset="100%" stop-color="#0284c7" stop-opacity="0" />
                </radialGradient>
                <radialGradient id="node-halo" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#fef3c7" stop-opacity="0.9" />
                    <stop offset="50%" stop-color="#f59e0b" stop-opacity="0.3" />
                    <stop offset="100%" stop-color="#b45309" stop-opacity="0" />
                </radialGradient>
            </defs>

            <g stroke="#38bdf8" stroke-opacity="0.28" stroke-width="0.8" fill="none">
                <path d="M 880,110 L 1080,220 L 1320,130 L 1440,280" />
                <path d="M 1080,220 L 1180,350 L 1380,440" />
                <path d="M 1180,350 L 1020,520 L 1160,680 L 1400,600" />
                <path d="M 1320,130 L 1180,350 L 1400,600" />
                <path d="M 1160,680 L 1040,840" />
                <path d="M 880,110 L 1180,350" />
            </g>

            <g stroke="#38bdf8" stroke-opacity="0.28" stroke-width="0.8" fill="none">
                <path d="M 0,380 L 160,280 L 280,400 L 140,600 L 0,540" />
                <path d="M 160,280 L 60,160" />
                <path d="M 280,400 L 440,320 L 580,480" />
                <path d="M 140,600 L 260,740 L 460,660" />
                <path d="M 280,400 L 260,740" />
                <path d="M 140,600 L 0,720" />
            </g>

            <circle cx="880" cy="110" r="14" fill="url(#node-cyan)" /><circle cx="880" cy="110" r="2.5" fill="#ffffff" />
            <circle cx="1080" cy="220" r="26" fill="url(#node-halo)" /><circle cx="1080" cy="220" r="4.5" fill="#ffffff" />
            <circle cx="1320" cy="130" r="18" fill="url(#node-cyan)" /><circle cx="1320" cy="130" r="3" fill="#ffffff" />
            <circle cx="1180" cy="350" r="32" fill="url(#node-halo)" /><circle cx="1180" cy="350" r="5" fill="#ffffff" />
            <circle cx="1380" cy="440" r="16" fill="url(#node-cyan)" /><circle cx="1380" cy="440" r="2.5" fill="#ffffff" />
            <circle cx="1020" cy="520" r="20" fill="url(#node-cyan)" /><circle cx="1020" cy="520" r="3" fill="#ffffff" />
            <circle cx="1160" cy="680" r="22" fill="url(#node-halo)" /><circle cx="1160" cy="680" r="4" fill="#ffffff" />
            <circle cx="1400" cy="600" r="16" fill="url(#node-cyan)" /><circle cx="1400" cy="600" r="2.5" fill="#ffffff" />

            <circle cx="160" cy="280" r="18" fill="url(#node-cyan)" /><circle cx="160" cy="280" r="3" fill="#ffffff" />
            <circle cx="280" cy="400" r="28" fill="url(#node-halo)" /><circle cx="280" cy="400" r="4.5" fill="#ffffff" />
            <circle cx="140" cy="600" r="24" fill="url(#node-halo)" /><circle cx="140" cy="600" r="4" fill="#ffffff" />
            <circle cx="440" cy="320" r="14" fill="url(#node-cyan)" /><circle cx="440" cy="320" r="2.5" fill="#ffffff" />
            <circle cx="260" cy="740" r="26" fill="url(#node-halo)" /><circle cx="260" cy="740" r="4.5" fill="#ffffff" />
        </svg>
    </div>

    <div class="w-full @yield('lebar', 'max-w-[380px]') z-10 my-auto py-3 sm:py-6 flex flex-col items-center">

        <div class="bg-white rounded-md shadow-2xl p-2 mb-2 sm:mb-3 flex items-center justify-center w-16 h-16 sm:w-24 sm:h-24">
            <img src="{{ asset('icons/logo.png') }}" alt="KARSA" class="w-full h-full object-contain">
        </div>

        <div class="text-center mb-3 sm:mb-4">
            <h1 class="text-white font-display font-extrabold text-xl sm:text-2xl tracking-wider uppercase">KARSA</h1>
            <p class="text-blue-100/90 text-xs font-semibold tracking-[0.14em] uppercase mt-0.5">Kertas Kerja RKAS</p>
            <p class="text-blue-100/70 text-[11px] font-normal mt-0.5">@yield('subjudul', 'Rencana Kegiatan & Anggaran Sekolah')</p>
        </div>

        <div class="w-full bg-white rounded-2xl shadow-2xl p-5 sm:p-7 border border-slate-100">
            @if(session('status'))
                <div class="mb-4 px-3.5 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs space-y-0.5">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            @endif

            @yield('kartu')
        </div>

        @hasSection('bawah')
            <div class="mt-4 text-center text-[11px] text-blue-100/80 leading-relaxed">
                @yield('bawah')
            </div>
        @endif
    </div>

</body>
</html>
