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
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Profil sekolah, tahun anggaran, pagu, dan status pengesahan RKAS.</p>
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
            target_modal_aset: @json(old('target_modal_aset', $tahunAnggaran->target_modal_aset)),
            terkunci: @json(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan'),
            // Penanda: operator sudah mengubah pagu tahap secara manual.
            tahap_manual: false,
            init() {
                const t = Number(this.pagu_total), a = Number(this.pagu_tahap1), b = Number(this.pagu_tahap2);
                if (a === 0 && b === 0) {
                    // Belum pernah diisi: ikuti otomatis 50% : 50%.
                    this.tahap_manual = false;
                    this.bagiOtomatis();
                } else {
                    // Data lama yang sudah pas 50/50 dibiarkan mode otomatis;
                    // pembagian lain (mis. 40/60) dianggap sudah diatur manual.
                    this.tahap_manual = !(t > 0 && (a + b) === t && a === Math.floor(t / 2));
                }
            },
            bagiOtomatis() {
                const t = Math.max(0, Math.floor(Number(this.pagu_total) / 2));
                this.pagu_tahap1 = t;
                this.pagu_tahap2 = Math.max(0, Number(this.pagu_total) - t);
            },
            ubahTotal(v) {
                this.pagu_total = v;
                // Jangan timpa pembagian yang sudah diatur manual oleh operator.
                if (!this.tahap_manual) this.bagiOtomatis();
            },
            ubahTahap(n, v) {
                this.tahap_manual = true;
                if (n === 1) { this.pagu_tahap1 = v } else { this.pagu_tahap2 = v }
            },
            kembaliOtomatis() {
                this.tahap_manual = false;
                this.bagiOtomatis();
            },
            get batasTahap() { return Number(this.pagu_total) / 2 },
            get lewatBatas() { return Number(this.pagu_tahap1) > this.batasTahap || Number(this.pagu_tahap2) > this.batasTahap },
            get persenTahap1() { return Number(this.pagu_total) > 0 ? Number(this.pagu_tahap1) / Number(this.pagu_total) * 100 : 0 },
            get persenTahap2() { return Number(this.pagu_total) > 0 ? Number(this.pagu_tahap2) / Number(this.pagu_total) * 100 : 0 }
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
                    <input type="text" x-bind:value="pagu_total.toLocaleString('id-ID')" @input="ubahTotal(Number(String($event.target.value).replace(/[^\d]/g, '')) || 0)" placeholder="0" class="input">
                </div>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Setelah pagu 1 tahun diisi, pagu tiap tahap otomatis dibagi 50% : 50% &mdash; tetap bisa Anda ubah manual.</p>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-[11px] text-slate-400 dark:text-slate-500">Jatah tiap tahap maksimal 50% dari pagu 1 tahun.</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                      :class="tahap_manual ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30'"
                      x-text="tahap_manual ? 'Diatur manual' : 'Otomatis 50% : 50%'"></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label">Pagu Tahap I (Jan&ndash;Jun)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="pagu_tahap1" :value="pagu_tahap1">
                        <input type="text" x-bind:value="pagu_tahap1.toLocaleString('id-ID')" @input="ubahTahap(1, Number(String($event.target.value).replace(/[^\d]/g, '')) || 0)" placeholder="0" class="input"
                               :class="Number(pagu_tahap1) > batasTahap ? 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500/30' : ''">
                    </div>
                    <p class="mt-1 text-[11px]" :class="Number(pagu_tahap1) > batasTahap ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-400 dark:text-slate-500'">
                        Maksimal 50% = Rp <span x-text="batasTahap.toLocaleString('id-ID')"></span>
                        <span x-show="Number(pagu_tahap1) > batasTahap"> — melebihi batas!</span>
                    </p>
                </div>
                <div>
                    <label class="label">Pagu Tahap II (Jul&ndash;Des)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="hidden" name="pagu_tahap2" :value="pagu_tahap2">
                        <input type="text" x-bind:value="pagu_tahap2.toLocaleString('id-ID')" @input="ubahTahap(2, Number(String($event.target.value).replace(/[^\d]/g, '')) || 0)" placeholder="0" class="input"
                               :class="Number(pagu_tahap2) > batasTahap ? 'border-red-400 dark:border-red-500 focus:border-red-500 focus:ring-red-500/30' : ''">
                    </div>
                    <p class="mt-1 text-[11px]" :class="Number(pagu_tahap2) > batasTahap ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-400 dark:text-slate-500'">
                        Maksimal 50% = Rp <span x-text="batasTahap.toLocaleString('id-ID')"></span>
                        <span x-show="Number(pagu_tahap2) > batasTahap"> — melebihi batas!</span>
                    </p>
                </div>
            </div>
            <div x-show="tahap_manual" x-cloak class="flex justify-end">
                <button type="button" @click="kembaliOtomatis()" class="text-[11px] font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                    &#8635; Bagi ulang otomatis 50% : 50%
                </button>
            </div>
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex justify-between mb-1">
                    <span>Pagu Tahap I:</span>
                    <span class="font-bold" :class="Number(pagu_tahap1) > batasTahap ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'"
                          x-text="persenTahap1.toLocaleString('id-ID', {maximumFractionDigits: 2}) + '%' + (Number(pagu_tahap1) > batasTahap ? ' · melebihi 50%' : '')"></span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>Pagu Tahap II:</span>
                    <span class="font-bold" :class="Number(pagu_tahap2) > batasTahap ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'"
                          x-text="persenTahap2.toLocaleString('id-ID', {maximumFractionDigits: 2}) + '%' + (Number(pagu_tahap2) > batasTahap ? ' · melebihi 50%' : '')"></span>
                </div>
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

            <div x-show="lewatBatas" x-cloak class="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-4 py-3 text-xs text-red-700 dark:text-red-400">
                <span class="font-bold">Tidak bisa disimpan:</span> pagu tiap tahap maksimal <b>50%</b> dari Pagu Total 1 tahun (maksimal Rp <span x-text="batasTahap.toLocaleString('id-ID')"></span> per tahap). Kurangi nominal tahap tersebut, atau naikkan Pagu Total.
            </div>
            @if(($tahunAnggaran->status_pengesahan ?? 'Draft') === 'Disahkan')
            <div class="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-4 py-3 text-xs text-red-700 dark:text-red-400">
                <span class="font-bold">Terkunci:</span> Pagu tidak bisa diubah saat status Disahkan. Buka kembali di Status RKAS jika perlu revisi.
            </div>
            @endif
            <div class="flex justify-end pt-2">
                <button type="submit" :disabled="terkunci || lewatBatas" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 dark:hover:bg-blue-500 text-white text-sm font-semibold shadow-md shadow-blue-600/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Simpan Pagu</button>
            </div>
        </form>
    </div>

    {{-- ============ Status RKAS (pengesahan & kunci data) ============ --}}
    @php $statusRkas = $tahunAnggaran->status_pengesahan ?? 'Draft'; @endphp
    <div class="card overflow-hidden" x-data="{ showSahkan:false, showBuka:false, paksa:false, alasan:'' }">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Status RKAS — TA {{ $tahunAnggaran->tahun }}</h2>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Pengesahan &amp; kunci data — cegah edit setelah disahkan.</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold border
                @if($statusRkas === 'Disahkan') bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30
                @elseif($statusRkas === 'Pergeseran') bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30
                @else bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600
                @endif">{{ $statusRkas }}</span>
        </div>
        <div class="p-6 space-y-4">
            @if(isset($kesiapan))
                <div class="rounded-xl border px-4 py-3 text-sm
                    {{ $kesiapan['siap']
                        ? 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300'
                        : 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <b>Kesiapan RKAS: {{ $kesiapan['skor'] }}%</b>
                            ({{ $kesiapan['ok_count'] }}/{{ $kesiapan['total'] }} syarat) —
                            {{ $kesiapan['siap'] ? 'semua syarat terpenuhi.' : count($kesiapan['gagal']).' syarat belum terpenuhi.' }}
                        </div>
                        <a href="{{ route('kesiapan.index', ['tahun' => $tahunAnggaran->tahun]) }}" class="text-[11px] font-bold underline shrink-0">Lihat detail &amp; perbaiki &rarr;</a>
                    </div>
                    @if(! $kesiapan['siap'])
                        <ul class="mt-2 space-y-1 text-[11px]">
                            @foreach($kesiapan['gagal'] as $gagal)
                                <li>&bull; {{ $gagal }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
            @if($statusRkas === 'Draft')
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    RKAS masih <b>Draft</b> — data masih bisa ditambah/diedit/dihapus. Sahkan jika sudah final; sistem akan membuat backup otomatis <code class="text-xs bg-slate-200 dark:bg-slate-700 px-1 py-0.5 rounded">rkas-pengesahan-{{ $tahunAnggaran->tahun }}-*.zip</code>.
                </div>
                <button type="button" @click="showSahkan = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-md shadow-emerald-600/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sahkan RKAS TA {{ $tahunAnggaran->tahun }}
                </button>
            @elseif($statusRkas === 'Pergeseran')
                <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                    RKAS dalam <b>Pergeseran</b> (dibuka kembali dari Disahkan untuk revisi). Data masih bisa diedit; sahkan kembali jika revisi selesai — backup baru akan dibuat.
                </div>
                <button type="button" @click="showSahkan = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-md shadow-emerald-600/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Sahkan Kembali RKAS TA {{ $tahunAnggaran->tahun }}
                </button>
            @else
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
                    RKAS sudah <b>Disahkan</b> — data terkunci. Backup resmi telah dibuat di menu Backup. Buka kembali jika perlu revisi darurat.
                </div>
                <button type="button" @click="showBuka = true" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow-md shadow-amber-600/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Buka Kembali untuk Revisi
                </button>
            @endif
        </div>

        <div x-show="showSahkan" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(15,23,42,.6); backdrop-filter: blur(3px);" @click.self="showSahkan = false">
            <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl p-6 space-y-4 ring-1 ring-slate-200 dark:ring-slate-700/60">
                <h3 class="text-base font-extrabold text-slate-800 dark:text-white">Yakin ingin mengesahkan RKAS TA {{ $tahunAnggaran->tahun }}?</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">Sistem akan membuat backup otomatis <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-1 py-0.5 rounded">rkas-pengesahan-{{ $tahunAnggaran->tahun }}-*.zip</span>, dan data tidak bisa diedit lagi setelah ini.</p>

                @if(isset($kesiapan) && ! $kesiapan['siap'])
                    <div class="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-4 py-3 text-xs text-red-800 dark:text-red-300 space-y-1">
                        <div class="font-bold">RKAS belum siap disahkan ({{ $kesiapan['skor'] }}%).</div>
                        <ul class="space-y-0.5">
                            @foreach($kesiapan['gagal'] as $gagal)
                                <li>&bull; {{ $gagal }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('kesiapan.index', ['tahun' => $tahunAnggaran->tahun]) }}" class="inline-block font-bold underline">Buka halaman Kesiapan RKAS &rarr;</a>
                    </div>
                    <label class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <input type="checkbox" x-model="paksa" class="mt-0.5 rounded border-slate-300 dark:border-slate-600">
                        <span>Saya sudah memeriksa daftar di atas dan tetap ingin mengesahkan RKAS ini (kondisi khusus).</span>
                    </label>
                    <div x-show="paksa" x-cloak>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Alasan pengesahan (wajib, minimal 10 karakter — dicatat di audit log)</label>
                        <textarea x-model="alasan" rows="2" class="input w-full" placeholder="Contoh: sisa pagu dikembalikan ke kas daerah sesuai arahan Dinas."></textarea>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showSahkan = false" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300">Batal</button>
                    <form method="POST" action="{{ route('pengaturan.pengesahan.sahkan') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahunAnggaran->tahun }}">
                        @if(isset($kesiapan) && ! $kesiapan['siap'])
                            <input type="hidden" name="konfirmasi_paksa" :value="paksa ? 1 : 0">
                            <input type="hidden" name="alasan_paksa" :value="alasan">
                            <button type="submit" x-bind:disabled="!paksa || alasan.trim().length < 10"
                                x-bind:class="(!paksa || alasan.trim().length < 10) ? 'opacity-50 cursor-not-allowed' : ''"
                                class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold">Tetap Sahkan</button>
                        @else
                            <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Ya, Sahkan</button>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <div x-show="showBuka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(15,23,42,.6); backdrop-filter: blur(3px);" @click.self="showBuka = false">
            <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl p-6 space-y-4 ring-1 ring-slate-200 dark:ring-slate-700/60">
                <h3 class="text-base font-extrabold text-slate-800 dark:text-white">Yakin ingin membuka kembali untuk revisi?</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">Status akan berubah dari <b>Disahkan</b> menjadi <b>Pergeseran</b>. Ini akan dicatat di audit log dan data kembali bisa diedit.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showBuka = false" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300">Batal</button>
                    <form method="POST" action="{{ route('pengaturan.pengesahan.buka-kembali') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahunAnggaran->tahun }}">
                        <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">Ya, Buka Kembali</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
