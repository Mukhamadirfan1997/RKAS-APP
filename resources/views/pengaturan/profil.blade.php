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
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Profil Sekolah</h2>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Data identitas untuk kop laporan dan header kertas kerja.</p>
        </div>
        <form method="POST" action="{{ route('pengaturan.update-sekolah') }}" class="p-6 space-y-5">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">NPSN</label>
                    <input type="text" name="npsn" value="{{ old('npsn', $sekolah->npsn) }}" class="input" placeholder="Nomor Pokok Sekolah Nasional">
                </div>
                <div>
                    <label class="label">Nama Sekolah <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_sekolah" value="{{ old('nama_sekolah', $sekolah->nama_sekolah) }}" required class="input">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nama Kepala Sekolah</label>
                    <input type="text" name="nama_kepala_sekolah" value="{{ old('nama_kepala_sekolah', $sekolah->nama_kepala_sekolah) }}" class="input">
                </div>
                <div>
                    <label class="label">NIP Kepala Sekolah</label>
                    <input type="text" name="nip_kepala_sekolah" value="{{ old('nip_kepala_sekolah', $sekolah->nip_kepala_sekolah) }}" class="input">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nama Bendahara</label>
                    <input type="text" name="nama_bendahara" value="{{ old('nama_bendahara', $sekolah->nama_bendahara) }}" class="input">
                </div>
                <div>
                    <label class="label">NIP Bendahara</label>
                    <input type="text" name="nip_bendahara" value="{{ old('nip_bendahara', $sekolah->nip_bendahara) }}" class="input">
                </div>
            </div>
            <div>
                <label class="label">Status Sekolah <span class="text-red-500">*</span></label>
                <select name="status_sekolah" class="input">
                    <option value="negeri" {{ old('status_sekolah', $sekolah->status_sekolah ?? 'negeri') === 'negeri' ? 'selected' : '' }}>Negeri</option>
                    <option value="swasta" {{ old('status_sekolah', $sekolah->status_sekolah ?? 'negeri') === 'swasta' ? 'selected' : '' }}>Swasta</option>
                </select>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">Penentu batas maksimal honor JUKNIS: <b class="text-slate-600 dark:text-slate-300">Negeri 20%</b> / <b class="text-slate-600 dark:text-slate-300">Swasta 40%</b> dari total pagu.</p>
            </div>
            <div>
                <label class="label">Alamat</label>
                <input type="text" name="alamat" value="{{ old('alamat', $sekolah->alamat) }}" class="input">
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="label">Desa/Kelurahan</label>
                    <input type="text" name="desa_kelurahan" value="{{ old('desa_kelurahan', $sekolah->desa_kelurahan) }}" class="input">
                </div>
                <div>
                    <label class="label">Kecamatan</label>
                    <input type="text" name="kecamatan" value="{{ old('kecamatan', $sekolah->kecamatan) }}" class="input">
                </div>
                <div>
                    <label class="label">Kabupaten/Kota</label>
                    <input type="text" name="kabupaten_kota" value="{{ old('kabupaten_kota', $sekolah->kabupaten_kota) }}" class="input">
                </div>
                <div>
                    <label class="label">Provinsi</label>
                    <input type="text" name="provinsi" value="{{ old('provinsi', $sekolah->provinsi) }}" class="input">
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors">Simpan Profil</button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden" x-data="breakPrefs()" x-init="init()">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Preferensi Istirahat</h2>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Atur seberapa sering pengingat muncul — disimpan di perangkat ini (offline).</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label">Ingatkan setiap</label>
                    <select x-model="interval" @change="save()" class="input">
                        <option value="0">Nonaktif — jangan ingatkan</option>
                        <option value="30">30 menit</option>
                        <option value="45">45 menit</option>
                        <option value="60">60 menit (bawaan)</option>
                        <option value="90">90 menit</option>
                    </select>
                </div>
                <div>
                    <label class="label">Durasi istirahat</label>
                    <select x-model="duration" @change="save()" class="input">
                        <option value="5">5 menit</option>
                        <option value="10">10 menit</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3">
                <div class="text-xs">
                    <div class="font-semibold text-slate-700 dark:text-slate-200">Pratinjau: <span x-text="preview"></span></div>
                    <div class="text-[11px] text-slate-400 dark:text-slate-500">Topbar akan tampil “Fokus • istirahat dalam X menit”.</div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold border" :class="interval==='0' ? 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-600' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30'" x-text="interval==='0' ? 'Nonaktif' : interval + ' / ' + duration + ' menit'"></span>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Perubahan langsung aktif tanpa reload. Atur ke <b class="text-slate-600 dark:text-slate-300">90/10</b> jika susun RKAS maraton tanpa gangguan sering.</p>
        </div>
    </div>
    <script>
    function breakPrefs(){
        return {
            interval: '60',
            duration: '5',
            preview: '',
            init(){
                try{
                    this.interval = localStorage.getItem('karsa_break_interval') || '60';
                    this.duration = localStorage.getItem('karsa_break_duration') || '5';
                    // migrasi lama 45 -> 60 untuk pengguna pertama kali setelah update
                    if(!localStorage.getItem('karsa_break_interval')) { this.interval='60'; }
                }catch(e){ this.interval='60'; this.duration='5'; }
                this.updatePreview();
            },
            save(){
                try{
                    localStorage.setItem('karsa_break_interval', this.interval);
                    localStorage.setItem('karsa_break_duration', this.duration);
                    if(this.interval==='0'){
                        localStorage.setItem('karsa_break_disabled', new Date().toISOString().slice(0,10));
                    } else {
                        localStorage.removeItem('karsa_break_disabled');
                        localStorage.setItem('karsa_break_last', String(Date.now()));
                    }
                    // hapus snooze agar hint baru langsung terlihat
                    localStorage.removeItem('karsa_break_snooze');
                }catch(e){}
                this.updatePreview();
            },
            updatePreview(){
                if(this.interval==='0') this.preview='Nonaktif — tidak akan ada pengingat';
                else this.preview = 'Ingatkan tiap ' + this.interval + ' menit, istirahat ' + this.duration + ' menit';
            }
        }
    }
    </script>
</div>
@endsection
