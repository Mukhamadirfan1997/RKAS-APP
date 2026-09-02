@extends('layouts.app')
@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-400">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Pengaturan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Profil sekolah, pagu anggaran, dan konfigurasi sistem.</p>
        </div>
        <a href="{{ route('rkas.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Lembar Kerja
        </a>
    </div>
    @include('pengaturan._nav')
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Akun Operator</h2>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Ubah nama, email, dan password login (single-user offline).</p>
        </div>
        <form method="POST" action="{{ route('pengaturan.update-akun') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="label">Nama <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required class="input" placeholder="Nama operator">
            </div>
            <div>
                <label class="label">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="input" placeholder="admin@sekolah.id">
                <p class="text-[10px] text-slate-400 mt-1">Dipakai untuk login & pemulihan via NPSN.</p>
            </div>
            <div class="pt-3 border-t border-slate-200 dark:border-slate-700/60 space-y-3">
                <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Ganti Password (opsional)</p>
                <div>
                    <label class="label">Password Saat Ini <span class="text-red-500">*</span></label>
                    <input type="password" name="current_password" required class="input" placeholder="Wajib isi untuk konfirmasi">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Password Baru</label>
                        <input type="password" name="password" class="input" placeholder="Min 8, kosongkan jika tidak ganti">
                    </div>
                    <div>
                        <label class="label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" class="input" placeholder="Ulangi password baru">
                    </div>
                </div>
            </div>
            <div class="flex justify-between items-center pt-2">
                <a href="{{ route('auth.forgot') }}" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">Lupa password? Pakai NPSN</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-semibold shadow-md transition-colors">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>
@endsection
