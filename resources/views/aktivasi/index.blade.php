@extends('layouts.app')

@section('content')
@php $kontak = $kontak ?? config('karsa.kontak'); @endphp
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Aktivasi Lisensi</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Aktifkan KARSA untuk tahun anggaran yang sedang aktif. Rejoso gratis selamanya.</p>
    </div>

    @if(session('success'))
        <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-400">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if($isRejoso)
        <div class="card p-6 border-emerald-200 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/30">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0">✓</span>
                <div>
                    <div class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Gratis Selamanya</div>
                    <p class="text-sm text-emerald-700 dark:text-emerald-300/90 mt-1">Sekolah Anda terdeteksi berada di kecamatan <strong>{{ \App\Models\PengaturanSekolah::value('kecamatan') }}</strong> yang mengandung "Rejoso" — tidak ada trial dan tidak perlu aktivasi. Semua fitur terbuka selamanya.</p>
                </div>
            </div>
        </div>
    @else
        <!-- Status trial -->
        <div class="card p-5">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Status Lisensi</h2>
            <div class="mt-3 space-y-2 text-sm">
                @if($isTrialActive)
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $sisaHari <=7 ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30' : 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/30' }}">
                            Trial aktif — sisa {{ $sisaHari }} hari
                        </span>
                        @if($trialEnd)<span class="text-xs text-slate-500">sampai {{ $trialEnd->format('d M Y') }}</span>@endif
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Tahun aktif: <strong class="text-slate-700 dark:text-slate-200">{{ $tahunAktif?->tahun ?? '-' }}</strong> @if($tahunAktifLicensed) <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">Sudah teraktivasi</span> @endif</p>
                @else
                    @if($tahunAktifLicensed)
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30">Tahun {{ $tahunAktif?->tahun }} sudah teraktivasi</span>
                        <p class="text-xs text-slate-500">Trial habis, tapi tahun ini sudah ada kode aktivasi — mode lihat-saja tidak aktif.</p>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30">Mode Lihat-Saja — trial habis</span>
                        <p class="text-xs text-slate-500">Masa percobaan berakhir @if($trialEnd) ({{ $trialEnd->format('d M Y') }}) @endif — masukkan kode aktivasi untuk tahun <strong>{{ $tahunAktif?->tahun ?? '-' }}</strong> agar bisa edit lagi.</p>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <!-- Device code -->
    <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Kode Perangkat</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kirim kode ini ke pengembang untuk mendapat Kode Aktivasi.</p>
        <div class="mt-3 flex gap-2 items-center">
            <code id="device-code" class="flex-1 px-3 py-2.5 rounded-xl bg-slate-900 text-slate-100 font-mono text-sm break-all">{{ $deviceCode }}</code>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('device-code').innerText).then(()=>{this.innerText='Tersalin!'; setTimeout(()=>this.innerText='Salin',1500)})" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold shrink-0">Salin</button>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            <a href="{{ $kontak['whatsapp_link'] ?? 'https://wa.me/6285156830304' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 font-semibold">
                WhatsApp {{ $kontak['whatsapp_display'] ?? '085156830304' }}
            </a>
            <a href="{{ $kontak['instagram_link'] ?? 'https://instagram.com/mukhamadirfan22' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-pink-50 dark:bg-pink-500/10 border border-pink-200 dark:border-pink-500/30 text-pink-700 dark:text-pink-300 font-semibold">
                {{ $kontak['instagram_display'] ?? '@mukhamadirfan22' }}
            </a>
        </div>
        <p class="mt-2 text-[11px] text-slate-400">Pengembang: {{ $kontak['pengembang'] ?? 'IrfanDev97 — OPS Rejoso' }}</p>
    </div>

    @if(!$isRejoso)
    <!-- Form aktivasi -->
    <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Masukkan Kode Aktivasi</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kode terikat ke tahun <strong>{{ $tahunAktif?->tahun ?? '-' }}</strong>. Jika tahun berganti, minta kode baru untuk tahun tersebut.</p>
        <form method="POST" action="{{ route('aktivasi.activate') }}" class="mt-4 flex gap-2">
            @csrf
            <input type="text" name="kode_aktivasi" value="{{ old('kode_aktivasi') }}" placeholder="AB3F-K9M2-XR7Q-2WZT" required class="input flex-1 font-mono uppercase tracking-widest" maxlength="19">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20">Aktifkan</button>
        </form>
        <p class="mt-2 text-[11px] text-slate-400">Format: 4-4-4-4 huruf/angka tanpa 0/O/1/I. Tulis persis seperti yang dikirim pengembang.</p>
    </div>
    @endif
</div>
@endsection
