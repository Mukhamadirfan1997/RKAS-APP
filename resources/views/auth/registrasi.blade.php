@extends('layouts.guest')

@section('judul', 'Registrasi Awal')
@section('lebar', 'max-w-[820px]')
@section('subjudul', 'Registrasi Awal — Profil Sekolah & Akun Operator')

@section('kartu')
    <div class="mb-4 px-3.5 py-2.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs leading-relaxed">
        Komputer ini belum memiliki akun operator. Lengkapi <b>profil sekolah</b> dan <b>akun operator</b> terlebih dahulu.
        Setelah tersimpan, Anda akan diarahkan ke halaman masuk.
    </div>

    <form method="POST" action="{{ route('registrasi.simpan') }}" class="space-y-5" x-data="{ lihatSandi: false }">
        @csrf

        {{-- ============ 1. PROFIL SEKOLAH ============ --}}
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold flex items-center justify-center">1</span>
                <h2 class="text-sm font-bold text-slate-800">Profil Sekolah</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">NPSN <span class="text-red-500">*</span></label>
                    <input type="text" name="npsn" value="{{ old('npsn') }}" required maxlength="20" placeholder="8 digit NPSN"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Sekolah <span class="text-red-500">*</span></label>
                    <select name="status_sekolah" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="negeri" @selected(old('status_sekolah', 'negeri') === 'negeri')>Negeri (Honor maks 20%)</option>
                        <option value="swasta" @selected(old('status_sekolah') === 'swasta')>Swasta (Honor maks 40%)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Sekolah <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_sekolah" value="{{ old('nama_sekolah') }}" required maxlength="150" placeholder="Contoh: SD NEGERI 1 CONTOH"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kepala Sekolah <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_kepala_sekolah" value="{{ old('nama_kepala_sekolah') }}" required maxlength="150"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">NIP Kepala Sekolah</label>
                    <input type="text" name="nip_kepala_sekolah" value="{{ old('nip_kepala_sekolah') }}" maxlength="50" placeholder="Opsional"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Bendahara <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_bendahara" value="{{ old('nama_bendahara') }}" required maxlength="150"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">NIP Bendahara</label>
                    <input type="text" name="nip_bendahara" value="{{ old('nip_bendahara') }}" maxlength="50" placeholder="Opsional"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat</label>
                    <input type="text" name="alamat" value="{{ old('alamat') }}" maxlength="255"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Desa / Kelurahan</label>
                    <input type="text" name="desa_kelurahan" value="{{ old('desa_kelurahan') }}" maxlength="100"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kecamatan</label>
                    <input type="text" name="kecamatan" value="{{ old('kecamatan') }}" maxlength="100"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kabupaten / Kota</label>
                    <input type="text" name="kabupaten_kota" value="{{ old('kabupaten_kota') }}" maxlength="100"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Provinsi</label>
                    <input type="text" name="provinsi" value="{{ old('provinsi') }}" maxlength="100"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        {{-- ============ 2. AKUN OPERATOR ============ --}}
        <div class="pt-4 border-t border-slate-200">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-[11px] font-bold flex items-center justify-center">2</span>
                <h2 class="text-sm font-bold text-slate-800">Akun Operator</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Operator <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="150" placeholder="Nama yang tampil di aplikasi"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email (untuk masuk) <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="username"
                        placeholder="nama@sekolah.id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="lihatSandi ? 'text' : 'password'" name="password" required minlength="8" autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="w-full rounded-lg border border-slate-300 px-3 pr-11 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <button type="button" @click="lihatSandi = !lihatSandi" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-700"
                            :aria-label="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                            <svg x-show="!lihatSandi" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="lihatSandi" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 012.223-3.592m3.11-2.183A9.96 9.96 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Ulangi Kata Sandi <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="lihatSandi ? 'text' : 'password'" name="password_confirmation" required minlength="8" autocomplete="new-password"
                            placeholder="Ketik ulang kata sandi"
                            class="w-full rounded-lg border border-slate-300 px-3 pr-11 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-bold shadow-md shadow-blue-600/25 transition-all">
                Simpan &amp; Lanjut ke Halaman Masuk
            </button>
            <p class="text-[10px] text-slate-400 mt-2 text-center leading-relaxed">
                Nominal pagu anggaran tidak diisi di sini &mdash; isi setelah masuk di menu <b>Pengaturan &rarr; Pagu</b>.
            </p>
        </div>
    </form>
@endsection

@section('bawah')
    Sudah punya berkas backup? <a href="{{ route('pulihkan') }}" class="underline font-semibold hover:text-white">Pulihkan tanpa registrasi</a>
@endsection
