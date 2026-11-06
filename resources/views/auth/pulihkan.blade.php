@extends('layouts.guest')

@section('judul', 'Pulihkan Data')
@section('lebar', 'max-w-[560px]')
@section('subjudul', 'Pulihkan Data dari Berkas Backup')

@section('kartu')
    <div class="mb-4 px-3.5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs leading-relaxed">
        Gunakan halaman ini bila Anda memindahkan aplikasi ke komputer baru atau ingin memakai data lama
        <b>tanpa registrasi ulang</b>. Berkas backup berformat <b>.zip</b> (dibuat dari menu Backup).
        Data yang ada sekarang akan dicadangkan otomatis sebelum ditimpa.
    </div>

    <form method="POST" action="{{ route('pulihkan.proses') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ nama: '' }">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Berkas Backup (.zip) <span class="text-red-500">*</span></label>
            <input type="file" name="file" accept=".zip,application/zip" required
                @change="nama = $event.target.files.length ? $event.target.files[0].name : ''"
                class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-slate-300 rounded-lg">
            <p class="text-[10px] text-slate-400 mt-1" x-show="nama" x-cloak>
                Berkas dipilih: <span class="font-semibold text-slate-600" x-text="nama"></span>
            </p>
            <p class="text-[10px] text-slate-500 mt-1">
                Batas unggah server: <b>{{ $maksUnggahMb }} MB</b> per berkas. Berkas lebih besar akan ditolak sebelum data dipulihkan.
            </p>
        </div>

        <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white text-sm font-bold shadow-md shadow-amber-600/25 transition-all">
            Pulihkan Sekarang
        </button>
    </form>

    <div class="mt-5 pt-3 border-t border-slate-100 text-center">
        <p class="text-[11px] text-slate-500">Ingin memulai dari nol tanpa data lama?</p>
        <a href="{{ route('registrasi') }}" class="inline-block mt-1 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline">
            Isi registrasi profil sekolah &amp; akun
        </a>
    </div>
@endsection

@section('bawah')
    Restore hanya tersedia selama instalasi belum memiliki akun. Setelah ada akun, gunakan menu <b>Backup</b> di dalam aplikasi.
@endsection
