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
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Anggaran TA {{ $tahunAnggaran->tahun ?? 2026 }} — Atur Pagu</h2>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Edit pagu untuk TA yang sedang dilihat ({{ $tahunAnggaran->tahun }}, {{ $tahunAnggaran->is_active ? 'Aktif' : 'Arsip' }}). Ganti TA aktif via <b>Daftar Tahun</b> di Pengaturan → Tahun.</p>
        </div>
        <div class="mx-6 mt-4 px-3 py-2 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 text-xs text-blue-700 dark:text-blue-300">💡 Mengatur pagu untuk TA {{ $tahunAnggaran->tahun }} — TA lain? Ganti dulu di tab <a href="{{ route('pengaturan.tahun.index') }}" class="underline font-semibold">Tahun</a>.</div>
        <form method="POST" action="{{ route('pengaturan.update-pagu') }}" class="p-6 space-y-5" x-data="{
            pagu_total: {{ (int) old('pagu_total', $tahunAnggaran->pagu_total ?? 0) }},
            pagu_tahap1: {{ (int) old('pagu_tahap1', $tahunAnggaran->pagu_tahap1 ?? 0) }},
            pagu_tahap2: {{ (int) old('pagu_tahap2', $tahunAnggaran->pagu_tahap2 ?? 0) }},
            target_barjas: @json(old('target_barjas', $tahunAnggaran->target_barjas)),
            target_modal_mesin: @json(old('target_modal_mesin', $tahunAnggaran->target_modal_mesin)),
            target_modal_aset: @json(old('target_modal_aset', $tahunAnggaran->target_modal_aset))
        }">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahunAnggaran->tahun }}">
            <div>
                <label class="label">Sumber Dana</label>
                <select name="sumber_dana" class="input">
                    <option {{ ($tahunAnggaran->sumber_dana ?? '') === 'BOSP REGULER' ? 'selected' : '' }}>BOSP REGULER</option>
                    <option {{ ($tahunAnggaran->sumber_dana ?? '') === 'BOS KINERJA' ? 'selected' : '' }}>BOS KINERJA</option>
                    <option {{ ($tahunAnggaran->sumber_dana ?? '') === 'BOSDA' ? 'selected' : '' }}>BOSDA</option>
                </select>
            </div>
            <div>
                <label class="label">Pagu Total 1 Tahun <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-1">
                    <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                    <input type="hidden" name="pagu_total" :value="pagu_total">
                    <input type="text" x-bind:value="pagu_total.toLocaleString('id-ID')" @input="pagu_total = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0" placeholder="0" class="input">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Pagu Tahap I (Jan&ndash;Jun)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="pagu_tahap1" :value="pagu_tahap1">
                        <input type="text" x-bind:value="pagu_tahap1.toLocaleString('id-ID')" @input="pagu_tahap1 = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0" placeholder="0" class="input">
                    </div>
                </div>
                <div>
                    <label class="label">Pagu Tahap II (Jul&ndash;Des)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="pagu_tahap2" :value="pagu_tahap2">
                        <input type="text" x-bind:value="pagu_tahap2.toLocaleString('id-ID')" @input="pagu_tahap2 = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0" placeholder="0" class="input">
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex justify-between mb-1">
                    <span>Tahap I + Tahap II:</span>
                    <span class="font-bold text-slate-700 dark:text-slate-200" x-text="'Rp ' + (Number(pagu_tahap1) + Number(pagu_tahap2)).toLocaleString('id-ID')"></span>
                </div>
                <div class="flex justify-between">
                    <span>Selisih terhadap Total:</span>
                    <span class="font-bold" :class="Number(pagu_total) === (Number(pagu_tahap1) + Number(pagu_tahap2)) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" x-text="'Rp ' + Math.abs(Number(pagu_total) - (Number(pagu_tahap1) + Number(pagu_tahap2))).toLocaleString('id-ID') + (Number(pagu_total) === (Number(pagu_tahap1) + Number(pagu_tahap2)) ? ' (Sesuai)' : ' (Tidak Sesuai)')"></span>
                </div>
            </div>

            <!-- Target Gelondongan (3 Kategori Dinas) -->
            <div class="pt-4 mt-2 border-t border-slate-200 dark:border-slate-700/60 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Target RKA Gelondongan — 3 Kategori Dinas</h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Isi target awal dari file Dinas (kolom PAK). Jumlah ketiganya harus = Pagu Total. Kosongkan = belum diisi (bukan 0).</p>
                </div>
                <div>
                    <label class="label">Target Belanja Barang dan Jasa</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="target_barjas" :value="target_barjas ?? ''">
                        <input type="text" :value="target_barjas === null ? '' : Number(target_barjas).toLocaleString('id-ID')" @input="let v=String($event.target.value).replace(/[^\d]/g,''); target_barjas = v === '' ? null : Number(v)" placeholder="— belum diisi —" class="input" @if(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan') disabled @endif>
                    </div>
                </div>
                <div>
                    <label class="label">Target Modal Mesin</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="target_modal_mesin" :value="target_modal_mesin ?? ''">
                        <input type="text" :value="target_modal_mesin === null ? '' : Number(target_modal_mesin).toLocaleString('id-ID')" @input="let v=String($event.target.value).replace(/[^\d]/g,''); target_modal_mesin = v === '' ? null : Number(v)" placeholder="— belum diisi —" class="input" @if(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan') disabled @endif>
                    </div>
                </div>
                <div>
                    <label class="label">Target Modal Aset Tetap Lainnya (termasuk buku)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="target_modal_aset" :value="target_modal_aset ?? ''">
                        <input type="text" :value="target_modal_aset === null ? '' : Number(target_modal_aset).toLocaleString('id-ID')" @input="let v=String($event.target.value).replace(/[^\d]/g,''); target_modal_aset = v === '' ? null : Number(v)" placeholder="— belum diisi —" class="input" @if(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan') disabled @endif>
                    </div>
                </div>
                <div class="rounded-xl border px-4 py-3 text-xs"
                     :class="((Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)) === Number(pagu_total) && target_barjas !== null && target_modal_mesin !== null && target_modal_aset !== null) ? 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30' : 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30'">
                    <div class="flex justify-between mb-1">
                        <span class="text-slate-500 dark:text-slate-400">Jumlah 3 Target:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-200" x-text="'Rp ' + (Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Selisih vs Pagu Total:</span>
                        <span class="font-bold" :class="((Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)) === Number(pagu_total) && target_barjas !== null && target_modal_mesin !== null && target_modal_aset !== null) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                              x-text="(() => { const s = (Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)) - Number(pagu_total); const a = Math.abs(s); if (s === 0 && target_barjas !== null && target_modal_mesin !== null && target_modal_aset !== null) return 'Rp 0 (Sesuai)'; if (target_barjas === null && target_modal_mesin === null && target_modal_aset === null) return '— belum diisi —'; return (s < 0 ? '-' : '+') + 'Rp ' + a.toLocaleString('id-ID') + (s === 0 ? ' (Sesuai)' : ' (Tidak sama dengan Pagu!)'); })()"></span>
                    </div>
                    <p class="mt-2 text-[11px] leading-snug" :class="((Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)) === Number(pagu_total) && target_barjas !== null && target_modal_mesin !== null && target_modal_aset !== null) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                        <span x-show="(Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)) === Number(pagu_total) && target_barjas !== null && target_modal_mesin !== null && target_modal_aset !== null">✓ Jumlah 3 target pas dengan Pagu Total.</span>
                        <span x-show="!((Number(target_barjas ?? 0) + Number(target_modal_mesin ?? 0) + Number(target_modal_aset ?? 0)) === Number(pagu_total) && target_barjas !== null && target_modal_mesin !== null && target_modal_aset !== null)">⚠️ Jumlah 3 target harus = Pagu Total (file Dinas). Periksa lagi sebelum sahkan.</span>
                    </p>
                </div>
            </div>

            @if(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan')
            <div class="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-4 py-3 text-xs text-red-700 dark:text-red-400">
                <span class="font-bold">Terkunci:</span> Pagu tidak bisa diubah saat status Disahkan. Buka kembali di Status RKAS jika perlu revisi.
            </div>
            @endif
            <div class="flex justify-end pt-2">
                <button type="submit" @if(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan') disabled @endif class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Simpan Pagu</button>
            </div>
        </form>
    </div>
</div>
@endsection
