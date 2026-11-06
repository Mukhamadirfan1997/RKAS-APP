@extends('layouts.guest')

@section('judul', 'Reset Password')
@section('subjudul', 'Buat Kata Sandi Baru (minimal 8 karakter)')

@section('kartu')
    <form method="POST" action="{{ route('auth.reset.attempt') }}" class="space-y-4" x-data="{ lihatSandi: false }">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Kata sandi baru <span class="text-red-500">*</span></label>
            <div class="relative">
                <input :type="lihatSandi ? 'text' : 'password'" name="password" required autofocus minlength="8" autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-300 px-3 pr-11 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <button type="button" @click="lihatSandi = !lihatSandi" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-700"
                    :aria-label="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                    <svg x-show="!lihatSandi" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <svg x-show="lihatSandi" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 012.223-3.592m3.11-2.183A9.96 9.96 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M3 3l18 18"/></svg>
                </button>
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Ulangi kata sandi <span class="text-red-500">*</span></label>
            <input :type="lihatSandi ? 'text' : 'password'" name="password_confirmation" required minlength="8" autocomplete="new-password"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm">Simpan &amp; Masuk</button>
    </form>
    <p class="text-[10px] text-slate-400 text-center mt-3">Sesi verifikasi berlaku 10 menit.</p>
@endsection
