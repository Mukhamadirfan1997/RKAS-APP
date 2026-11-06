@extends('layouts.guest')

@section('judul', 'Lupa Password')
@section('subjudul', 'Verifikasi NPSN + Email untuk Reset')

@section('kartu')
    <form method="POST" action="{{ route('auth.forgot.attempt') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">NPSN <span class="text-red-500">*</span></label>
            <input type="text" name="npsn" value="{{ old('npsn') }}" required autofocus placeholder="NPSN sekolah"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p class="text-[10px] text-slate-400 mt-1">NPSN tersimpan di Pengaturan &rarr; Profil Sekolah</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Email terdaftar <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@sekolah.id" autocomplete="username"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-sm">Verifikasi</button>
    </form>

    <a href="{{ route('login') }}" class="block text-center text-xs text-slate-500 hover:text-slate-700 mt-4">&larr; Kembali ke Masuk</a>
    <p class="text-[10px] text-slate-400 text-center mt-3">Offline &mdash; NPSN+email dicocokkan lokal di komputer ini.</p>
@endsection
