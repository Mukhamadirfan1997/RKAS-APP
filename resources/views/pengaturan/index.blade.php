@extends('layouts.app')

@section('content')
<div class="p-5 md:p-6 max-w-[1440px] mx-auto">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800">Pengaturan</h1>
            <p class="text-xs text-slate-500 mt-0.5">Profil sekolah, pagu anggaran, dan konfigurasi sistem.</p>
        </div>
        <a href="{{ route('rkas.index') }}" class="text-xs text-blue-600 hover:underline">Kembali ke Lembar Kerja</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Profil Sekolah -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-sm font-bold text-slate-700">Profil Sekolah</h2>
                <p class="text-[11px] text-slate-400">Data identitas untuk kop laporan dan header kertas kerja.</p>
            </div>
            <form method="POST" action="{{ route('pengaturan.update-sekolah') }}" class="p-5 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">NPSN</label>
                        <input type="text" name="npsn" value="{{ old('npsn', $sekolah->npsn) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Nomor Pokok Sekolah Nasional">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Sekolah <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_sekolah" value="{{ old('nama_sekolah', $sekolah->nama_sekolah) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Kepala Sekolah</label>
                        <input type="text" name="nama_kepala_sekolah" value="{{ old('nama_kepala_sekolah', $sekolah->nama_kepala_sekolah) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">NIP Kepala Sekolah</label>
                        <input type="text" name="nip_kepala_sekolah" value="{{ old('nip_kepala_sekolah', $sekolah->nip_kepala_sekolah) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Bendahara</label>
                        <input type="text" name="nama_bendahara" value="{{ old('nama_bendahara', $sekolah->nama_bendahara) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">NIP Bendahara</label>
                        <input type="text" name="nip_bendahara" value="{{ old('nip_bendahara', $sekolah->nip_bendahara) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat</label>
                    <input type="text" name="alamat" value="{{ old('alamat', $sekolah->alamat) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Desa/Kelurahan</label>
                        <input type="text" name="desa_kelurahan" value="{{ old('desa_kelurahan', $sekolah->desa_kelurahan) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Kecamatan</label>
                        <input type="text" name="kecamatan" value="{{ old('kecamatan', $sekolah->kecamatan) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Kabupaten/Kota</label>
                        <input type="text" name="kabupaten_kota" value="{{ old('kabupaten_kota', $sekolah->kabupaten_kota) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Provinsi</label>
                        <input type="text" name="provinsi" value="{{ old('provinsi', $sekolah->provinsi) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-sm">Simpan Profil</button>
                </div>
            </form>
        </div>

        <!-- Pagu Anggaran -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-sm font-bold text-slate-700">Pagu Anggaran {{ $tahunAnggaran->tahun ?? 2026 }}</h2>
                <p class="text-[11px] text-slate-400">Total pagu, Tahap I (Jan&ndash;Jun), dan Tahap II (Jul&ndash;Des).</p>
            </div>
            <form method="POST" action="{{ route('pengaturan.update-pagu') }}" class="p-5 space-y-4" x-data="{ 
                pagu_total: {{ old('pagu_total', $tahunAnggaran->pagu_total ?? 0) }}, 
                pagu_tahap1: {{ old('pagu_tahap1', $tahunAnggaran->pagu_tahap1 ?? 0) }}, 
                pagu_tahap2: {{ old('pagu_tahap2', $tahunAnggaran->pagu_tahap2 ?? 0) }} 
            }">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sumber Dana</label>
                    <select name="sumber_dana" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option {{ ($tahunAnggaran->sumber_dana ?? '') === 'BOSP REGULER' ? 'selected' : '' }}>BOSP REGULER</option>
                        <option {{ ($tahunAnggaran->sumber_dana ?? '') === 'BOS KINERJA' ? 'selected' : '' }}>BOS KINERJA</option>
                        <option {{ ($tahunAnggaran->sumber_dana ?? '') === 'BOSDA' ? 'selected' : '' }}>BOSDA</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Pagu Total 1 Tahun <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400">Rp</span>
                        <input type="hidden" name="pagu_total" :value="pagu_total">
                        <input type="text" x-bind:value="pagu_total.toLocaleString('id-ID')" @input="pagu_total = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0" placeholder="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Pagu Tahap I (Jan&ndash;Jun)</label>
                        <div class="flex items-center gap-1">
                            <span class="text-sm text-slate-400">Rp</span>
                            <input type="hidden" name="pagu_tahap1" :value="pagu_tahap1">
                            <input type="text" x-bind:value="pagu_tahap1.toLocaleString('id-ID')" @input="pagu_tahap1 = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0" placeholder="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Pagu Tahap II (Jul&ndash;Des)</label>
                        <div class="flex items-center gap-1">
                            <span class="text-sm text-slate-400">Rp</span>
                            <input type="hidden" name="pagu_tahap2" :value="pagu_tahap2">
                            <input type="text" x-bind:value="pagu_tahap2.toLocaleString('id-ID')" @input="pagu_tahap2 = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0" placeholder="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-500">
                    <div class="flex justify-between mb-1">
                        <span>Tahap I + Tahap II:</span>
                        <span class="font-bold text-slate-700" x-text="'Rp ' + (Number(pagu_tahap1) + Number(pagu_tahap2)).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Selisih terhadap Total:</span>
                        <span class="font-bold" :class="Number(pagu_total) === (Number(pagu_tahap1) + Number(pagu_tahap2)) ? 'text-emerald-600' : 'text-red-600'" x-text="'Rp ' + Math.abs(Number(pagu_total) - (Number(pagu_tahap1) + Number(pagu_tahap2))).toLocaleString('id-ID') + (Number(pagu_total) === (Number(pagu_tahap1) + Number(pagu_tahap2)) ? ' (Sesuai)' : ' (Tidak Sesuai)')"></span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status Pengesahan</label>
                    <select name="status_pengesahan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option {{ ($tahunAnggaran->status_pengesahan ?? '') === 'Draft' ? 'selected' : '' }}>Draft</option>
                        <option {{ ($tahunAnggaran->status_pengesahan ?? '') === 'Disahkan' ? 'selected' : '' }}>Disahkan</option>
                        <option {{ ($tahunAnggaran->status_pengesahan ?? '') === 'Pergeseran' ? 'selected' : '' }}>Pergeseran</option>
                    </select>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-sm">Simpan Pagu</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
