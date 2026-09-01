<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - KARSA 2026</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-white/95 flex items-center justify-center text-blue-700 font-extrabold text-2xl shadow-xl">K</div>
            <h1 class="text-white font-bold text-xl mt-4">KARSA 2026</h1>
            <p class="text-blue-100 text-xs mt-1">Kertas Anggaran Sekolah — Rujukan sebelum ARKAS</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-6">
            @if($errors->any())
                <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                    @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@sekolah.id') }}" required autofocus
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kata Sandi</label>
                    <input type="password" name="password" value="password" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-xs text-slate-500">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"> Ingat saya
                    </label>
                </div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-sm transition-colors">
                    Masuk
                </button>
            </form>

            <p class="text-[10px] text-slate-400 text-center mt-4 leading-relaxed">
                Default: <b>admin@sekolah.id</b> / <b>password</b><br>
                Aplikasi desktop lokal offline &mdash; data tersimpan di komputer ini.
            </p>
        </div>
    </div>
</body>
</html>