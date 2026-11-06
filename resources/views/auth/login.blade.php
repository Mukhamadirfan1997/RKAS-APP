@extends('layouts.guest')

@section('judul', 'Masuk')
@section('subjudul', 'Rencana Kegiatan & Anggaran Sekolah')

@section('kartu')
    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ lihatSandi: false }">
        @csrf
        <div>
            <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', session('registrasi_email')) }}" required autofocus
                autocomplete="username"
                placeholder="nama@sekolah.id"
                class="w-full rounded-lg border-2 border-slate-700/80 px-3.5 py-2 text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi</label>
            <div class="relative">
                <input id="password" :type="lihatSandi ? 'text' : 'password'" name="password" required
                    autocomplete="current-password"
                    placeholder="Kata sandi akun Anda"
                    class="w-full rounded-lg border border-slate-300 px-3.5 pr-11 py-2 text-sm font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                <button type="button" @click="lihatSandi = !lihatSandi"
                    class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-700"
                    :aria-label="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    :title="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                    <svg x-show="!lihatSandi" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <svg x-show="lihatSandi" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 012.223-3.592m3.11-2.183A9.96 9.96 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M3 3l18 18"/></svg>
                </button>
            </div>
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
            Akun operator hanya dibuat saat registrasi pertama di komputer ini &mdash; aplikasi tidak menyimpan akun bawaan.
        </p>
    </div>
@endsection

@section('bawah')
    Aplikasi desktop lokal offline &mdash; data tersimpan di komputer ini.
@endsection
