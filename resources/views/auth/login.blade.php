<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - KARSA</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/logo.png') }}">
    
    <!-- Fonts: Inter for UI & Outfit for Brand/Headings -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

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
<body class="min-h-full flex items-center justify-center login-bg p-4 relative overflow-hidden select-none">

    <!-- Background Constellation Network (Presisi Sesuai Gambar) -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
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

            <!-- Network Lines (Top & Right) -->
            <g stroke="#38bdf8" stroke-opacity="0.28" stroke-width="0.8" fill="none">
                <path d="M 880,110 L 1080,220 L 1320,130 L 1440,280" />
                <path d="M 1080,220 L 1180,350 L 1380,440" />
                <path d="M 1180,350 L 1020,520 L 1160,680 L 1400,600" />
                <path d="M 1320,130 L 1180,350 L 1400,600" />
                <path d="M 1160,680 L 1040,840" />
                <path d="M 880,110 L 1180,350" />
            </g>

            <!-- Network Lines (Bottom & Left) -->
            <g stroke="#38bdf8" stroke-opacity="0.28" stroke-width="0.8" fill="none">
                <path d="M 0,380 L 160,280 L 280,400 L 140,600 L 0,540" />
                <path d="M 160,280 L 60,160" />
                <path d="M 280,400 L 440,320 L 580,480" />
                <path d="M 140,600 L 260,740 L 460,660" />
                <path d="M 280,400 L 260,740" />
                <path d="M 140,600 L 0,720" />
            </g>

            <!-- Star / Mesh Glowing Nodes (Top & Right) -->
            <circle cx="880" cy="110" r="14" fill="url(#node-cyan)" />
            <circle cx="880" cy="110" r="2.5" fill="#ffffff" />

            <circle cx="1080" cy="220" r="26" fill="url(#node-halo)" />
            <circle cx="1080" cy="220" r="4.5" fill="#ffffff" />

            <circle cx="1320" cy="130" r="18" fill="url(#node-cyan)" />
            <circle cx="1320" cy="130" r="3" fill="#ffffff" />

            <circle cx="1180" cy="350" r="32" fill="url(#node-halo)" />
            <circle cx="1180" cy="350" r="5" fill="#ffffff" />

            <circle cx="1380" cy="440" r="16" fill="url(#node-cyan)" />
            <circle cx="1380" cy="440" r="2.5" fill="#ffffff" />

            <circle cx="1020" cy="520" r="20" fill="url(#node-cyan)" />
            <circle cx="1020" cy="520" r="3" fill="#ffffff" />

            <circle cx="1160" cy="680" r="22" fill="url(#node-halo)" />
            <circle cx="1160" cy="680" r="4" fill="#ffffff" />

            <circle cx="1400" cy="600" r="16" fill="url(#node-cyan)" />
            <circle cx="1400" cy="600" r="2.5" fill="#ffffff" />

            <!-- Star / Mesh Glowing Nodes (Bottom & Left) -->
            <circle cx="160" cy="280" r="18" fill="url(#node-cyan)" />
            <circle cx="160" cy="280" r="3" fill="#ffffff" />

            <circle cx="280" cy="400" r="28" fill="url(#node-halo)" />
            <circle cx="280" cy="400" r="4.5" fill="#ffffff" />

            <circle cx="140" cy="600" r="24" fill="url(#node-halo)" />
            <circle cx="140" cy="600" r="4" fill="#ffffff" />

            <circle cx="440" cy="320" r="14" fill="url(#node-cyan)" />
            <circle cx="440" cy="320" r="2.5" fill="#ffffff" />

            <circle cx="260" cy="740" r="26" fill="url(#node-halo)" />
            <circle cx="260" cy="740" r="4.5" fill="#ffffff" />
        </svg>
    </div>

    <!-- Center Login Form Box -->
    <div class="w-full max-w-[380px] z-10 py-6 flex flex-col items-center">
        
        <!-- App Logo (Square White Container Sesuai Gambar) -->
        <div class="bg-white rounded-md shadow-2xl p-2 mb-3 flex items-center justify-center w-28 h-28 sm:w-32 sm:h-32">
            <img src="{{ asset('icons/logo.png') }}" alt="KARSA" class="w-full h-full object-contain">
        </div>

        <!-- Title & Subtitle -->
        <div class="text-center mb-5">
            <h1 class="text-white font-display font-extrabold text-2xl sm:text-3xl tracking-wider uppercase">KARSA</h1>
            <p class="text-blue-100/90 text-xs font-normal mt-0.5">Kertas Anggaran Sekolah &mdash; Rujukan sebelum ARKAS</p>
        </div>

        <!-- Login Card -->
        <div class="w-full bg-white rounded-2xl shadow-2xl p-6 sm:p-7 border border-slate-100">
            @if($errors->any())
                <div class="mb-4 px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@sekolah.id') }}" required autofocus
                        placeholder="admin@sekolah.id"
                        class="w-full rounded-lg border-2 border-slate-700/80 px-3.5 py-2 text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi</label>
                    <input type="password" name="password" value="password" required
                        placeholder="••••••••"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                </div>

                <div class="flex items-center justify-between pt-0.5">
                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                        <span>Ingat saya</span>
                    </label>
                    <a href="{{ route('auth.forgot') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline">Lupa password?</a>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-bold shadow-md shadow-blue-600/25 transition-all">
                    Masuk
                </button>
            </form>

            <div class="mt-5 pt-3 border-t border-slate-100 text-center">
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Default: <b class="font-semibold text-slate-700">admin@sekolah.id</b> / <b class="font-semibold text-slate-700">password</b>
                </p>
                <p class="text-[10px] text-slate-400 mt-0.5 leading-snug">
                    Aplikasi desktop lokal offline &mdash; data tersimpan di komputer ini.
                </p>
            </div>
        </div>

    </div>

</body>
</html>