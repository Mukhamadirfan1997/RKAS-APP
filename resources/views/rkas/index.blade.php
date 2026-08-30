@extends('layouts.app')

@section('content')
@php
    $bulanIndonesia = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
@endphp

<div class="p-5 md:p-6 max-w-[1440px] mx-auto">
    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800">Lembar Kerja RKAS {{ $tahunAnggaran->tahun }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                {{ $tahunAnggaran->sumber_dana }} &mdash; {{ $sekolah->nama_sekolah }}
                (NPSN {{ $sekolah->npsn }})
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('rkas.pdf') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak / PDF
            </a>
            <button type="button" id="btn-open-modal" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Sisip Uraian Anggaran
            </button>
        </div>
    </div>

    @php
        $tahap1Total = \App\Models\RkasItemBulan::whereHas('item', fn($q) => $q->where('tahun_anggaran_id', $tahunAnggaran->id))->whereBetween('bulan', [1, 6])->sum('jumlah');
        $tahap1Pct = $tahunAnggaran->pagu_total > 0 ? round($tahap1Total / $tahunAnggaran->pagu_total * 100) : 0;
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Pagu Total</div>
            <div class="text-lg md:text-xl font-bold text-slate-800 mt-1">Rp {{ number_format($tahunAnggaran->pagu_total, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Tahap I: Rp {{ number_format($tahunAnggaran->pagu_tahap1, 0, ',', '.') }} &middot; Tahap II: Rp {{ number_format($tahunAnggaran->pagu_tahap2, 0, ',', '.') }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Sudah Dianggarkan</div>
            <div class="text-lg md:text-xl font-bold text-indigo-700 mt-1">Rp {{ number_format($totalSudahDianggarkan, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $items->count() }} item belanja</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Sisa Pagu</div>
            <div class="text-lg md:text-xl font-bold {{ $sisaPagu >= 0 ? 'text-emerald-700' : 'text-red-600' }} mt-1">Rp {{ number_format($sisaPagu, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $sisaPagu >= 0 ? 'Siap untuk tambahan kegiatan' : 'Melebihi pagu total' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Capaian Tahap I (min 50%)</div>
            <div class="text-lg md:text-xl font-bold {{ $tahap1Pct >= 50 ? 'text-emerald-700' : 'text-amber-600' }} mt-1">Rp {{ number_format($tahap1Total, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $tahap1Pct }}% dari pagu &middot; {{ $tahap1Pct >= 50 ? 'memenuhi minimal 50%' : 'belum mencapai minimal 50%' }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-700">Daftar Rincian Anggaran</h2>
                <p class="text-[11px] text-slate-400">Filter alokasi bulan &mdash; pilah item sesuai bulan pelaksanaan.</p>
            </div>
            <form method="GET" action="{{ route('rkas.index') }}" class="flex items-center gap-2">
                <select name="bulan" onchange="this.form.submit()" class="rounded-lg border border-slate-300 text-sm px-3 py-1.5 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0" {{ $selectedBulan === 0 ? 'selected' : '' }}>Semua Bulan</option>
                    @for($b = 1; $b <= 12; $b++)
                        <option value="{{ $b }}" {{ $selectedBulan === $b ? 'selected' : '' }}>{{ $bulanIndonesia[$b] }}</option>
                    @endfor
                </select>
                @if($selectedBulan > 0)
                    <a href="{{ route('rkas.index', ['bulan' => 0]) }}" class="text-xs text-slate-400 hover:text-red-500">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wide">
                        <th class="px-3 py-2.5 text-left font-semibold">No</th>
                        <th class="px-3 py-2.5 text-left font-semibold min-w-[220px]">Kegiatan (Kode SNP)</th>
                        <th class="px-3 py-2.5 text-left font-semibold min-w-[260px]">Uraian / Keterangan Khusus</th>
                        <th class="px-3 py-2.5 text-left font-semibold min-w-[200px]">Kode Rekening</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Volume</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Satuan</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Harga Satuan</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Jumlah</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Bulan Aktif</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                        @php
                            $aktif = $item->alokasiBulan->where('volume', '>', 0);
                            $labelBulan = $aktif->map(fn($ab) => $bulanIndonesia[$ab->bulan])->implode(', ');
                            $manyBulan = $aktif->count();
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-3 py-3 text-slate-400">{{ $item->no_urut }}</td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-slate-700">{{ $item->program->nama ?? '-' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $item->program->kode ?? '' }} &middot; {{ $item->program->standar_snp ?? '' }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-slate-700">{{ $item->uraian }}</div>
                                @if($item->keterangan_kustom)
                                    <div class="text-[11px] mt-0.5 text-indigo-500">{{ $item->keterangan_kustom }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-slate-700">{{ $item->kodeRekening->nama ?? '-' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $item->kodeRekening->kode ?? '' }} &middot; {{ $item->kodeRekening->kategori_belanja ?? '' }}</div>
                            </td>
                            <td class="px-3 py-3 text-right text-slate-700">{{ number_format($item->volume, 2, ',', '.') }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ $item->satuan }}</td>
                            <td class="px-3 py-3 text-right text-slate-700">Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 text-right font-bold text-slate-800">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 text-slate-500 max-w-[200px]">
                                <span class="text-xs">{{ $manyBulan }} bulan</span>
                                <span class="text-[10px] text-slate-400 block leading-snug">{{ $manyBulan < 12 && $manyBulan > 0 ? $labelBulan : ($manyBulan === 12 ? 'Januari s.d. Desember' : '-') }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button type="button" data-action="edit" data-id="{{ $item->id }}" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" data-action="delete" data-id="{{ $item->id }}" class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-12 text-center text-slate-400">
                                <div class="text-sm font-semibold">Belum ada rincian anggaran</div>
                                <div class="text-xs mt-1">Klik tombol &ldquo;Sisip Uraian Anggaran&rdquo; untuk mulai menyusun RKAS {{ $tahunAnggaran->tahun }}.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($items->isNotEmpty())
                <tfoot class="bg-slate-50 border-t border-slate-200 text-sm">
                    <tr>
                        <td colspan="7" class="px-3 py-3 text-right font-bold text-slate-600">Total Keseluruhan</td>
                        <td class="px-3 py-3 text-right font-bold text-slate-800">Rp {{ number_format($items->sum('jumlah'), 0, ',', '.') }}</td>
                        <td colspan="2" class="px-3 py-3 text-[11px] text-slate-400">Tahap I: Rp {{ number_format($items->sum(fn($i) => $i->alokasiBulan->whereBetween('bulan',[1,6])->sum('jumlah')), 0, ',', '.') }} &middot; Tahap II: Rp {{ number_format($items->sum(fn($i) => $i->alokasiBulan->whereBetween('bulan',[7,12])->sum('jumlah')), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div id="rkas-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" style="background-color: rgba(15,23,42,.55); backdrop-filter: blur(2px);">
        <div class="min-h-full flex items-center justify-center p-4" x-data="modalForm()">
            <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl flex flex-col max-h-[92vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Detail Anggaran Kegiatan</h3>
                        <p class="text-[11px] text-slate-400">{{ $tahunAnggaran->sumber_dana }} Tahun {{ $tahunAnggaran->tahun }}</p>
                    </div>
                    <button @click="close()" class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Kegiatan <span class="text-red-500">*</span></label>
                            <div class="relative" @click.outside="pickers.kegiatan.open = false">
                                <input type="text" x-model="pickers.kegiatan.q" @input="debouncedSearch('kegiatan')" placeholder="Ketik untuk mencari kegiatan dari 8 SNP..." autocomplete="off"
                                    @focus="if (pickers.kegiatan.q) pickers.kegiatan.open = true"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <template x-if="pickers.kegiatan.open && pickers.kegiatan.loading"><div class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg px-3 py-2 text-xs text-slate-400">Mencari...</div></template>
                                <template x-if="pickers.kegiatan.open && !pickers.kegiatan.loading">
                                    <ul class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-auto divide-y divide-slate-100">
                                        <template x-for="r in pickers.kegiatan.results" :key="r.id">
                                            <li @click="selectKegiatan(r)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer">
                                                <div class="text-sm text-slate-700" x-text="r.text"></div>
                                                <div class="text-[11px] text-slate-400" x-text="'SNP: ' + r.subtext"></div>
                                            </li>
                                        </template>
                                        <template x-if="pickers.kegiatan.results.length === 0"><li class="px-3 py-2 text-xs text-slate-400">Tidak ada data cocok.</li></template>
                                    </ul>
                                </template>
                                <template x-if="pickers.kegiatan.label"><div class="mt-1 text-[11px] text-emerald-600" x-text="'Terpilih: ' + pickers.kegiatan.label"></div></template>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Kode Rekening Belanja <span class="text-red-500">*</span></label>
                            <div class="relative" @click.outside="pickers.rekening.open = false">
                                <input type="text" x-model="pickers.rekening.q" @input="debouncedSearch('rekening')" placeholder="Ketik untuk mencari kode rekening belanja..." autocomplete="off"
                                    @focus="if (pickers.rekening.q) pickers.rekening.open = true"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <template x-if="pickers.rekening.open && pickers.rekening.loading"><div class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg px-3 py-2 text-xs text-slate-400">Mencari...</div></template>
                                <template x-if="pickers.rekening.open && !pickers.rekening.loading">
                                    <ul class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-auto divide-y divide-slate-100">
                                        <template x-for="r in pickers.rekening.results" :key="r.id">
                                            <li @click="selectRekening(r)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer">
                                                <div class="text-sm text-slate-700" x-text="r.text"></div>
                                                <div class="text-[11px] text-slate-400" x-text="r.subtext"></div>
                                            </li>
                                        </template>
                                        <template x-if="pickers.rekening.results.length === 0"><li class="px-3 py-2 text-xs text-slate-400">Tidak ada data cocok.</li></template>
                                    </ul>
                                </template>
                                <template x-if="pickers.rekening.label"><div class="mt-1 text-[11px] text-emerald-600" x-text="'Terpilih: ' + pickers.rekening.label"></div></template>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600 flex items-center justify-between">
                            <span>Katalog Barang (pengisi cepat)</span>
                            <span class="text-[10px] font-normal text-slate-400">Pilih untuk mengisi uraian, satuan &amp; harga acuan</span>
                        </div>
                        <div class="p-3">
                            <div class="relative" @click.outside="pickers.barang.open = false">
                                <input type="text" x-model="pickers.barang.q" @input="debouncedSearch('barang')" placeholder="Cari nama barang / kode barang di katalog..." autocomplete="off"
                                    @focus="if (pickers.barang.q) pickers.barang.open = true"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <template x-if="pickers.barang.open && pickers.barang.loading"><div class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg px-3 py-2 text-xs text-slate-400">Mencari...</div></template>
                                <template x-if="pickers.barang.open && !pickers.barang.loading">
                                    <ul class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-72 overflow-auto divide-y divide-slate-100">
                                        <template x-for="r in pickers.barang.results" :key="r.id">
                                            <li @click="selectBarang(r); fillFromCatalog(r)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer">
                                                <div class="text-sm text-slate-700" x-text="r.nama"></div>
                                                <div class="text-[11px] text-slate-400" x-text="r.subtext"></div>
                                            </li>
                                        </template>
                                        <template x-if="pickers.barang.results.length === 0"><li class="px-3 py-2 text-xs text-slate-400">Tidak ada data cocok.</li></template>
                                    </ul>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Uraian <span class="text-red-500">*</span></label>
                            <textarea x-model="form.uraian" maxlength="500" rows="2" placeholder="Ketik uraian bebas, maksimal 500 karakter..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                            <div class="text-[10px] text-slate-400 text-right mt-0.5" x-text="form.uraian.length + ' / 500'"></div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Keterangan Khusus / Peruntukan Anggaran</label>
                            <textarea x-model="form.keterangan_kustom" maxlength="255" rows="2" placeholder="Contoh: Pemeliharaan Ruang Kelas 1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                            <div class="text-[10px] text-slate-400 mt-0.5">Ketikan bebas untuk membedakan peruntukan walau kode rekening sama.</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Harga Satuan yang Dianggarkan <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-1">
                            <span class="text-sm text-slate-400">Rp</span>
                            <input type="text" inputmode="numeric" x-bind:value="form.harga_satuan.toLocaleString('id-ID')"
                                @input="form.harga_satuan = Number(String($event.target.value).replace(/[^\d]/g, '')) || 0"
                                placeholder="0" class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-semibold text-slate-600">Dianggarkan untuk Bulan</span>
                            <span class="text-[11px] text-slate-500">Kartu biru = Tahap I (Jan&ndash;Jun) &middot; hijau = Tahap II (Jul&ndash;Des)</span>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                            <template x-for="b in months" :key="b.no">
                                <div :class="b.no <= 6 ? 'border-blue-200' : 'border-emerald-200'" class="rounded-lg border bg-slate-50/50 p-2">
                                    <div class="text-[10px] font-bold text-slate-500 mb-1" x-text="b.nama"></div>
                                    <input type="number" x-model.number="alokasi[b.no].volume" min="0" step="any" placeholder="Vol" class="w-full rounded-md border border-slate-300 px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    <input type="text" x-model="alokasi[b.no].satuan" placeholder="satuan" class="w-full rounded-md border border-slate-200 px-1.5 py-1 text-[10px] mt-1 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    <div class="text-[10px] text-slate-400 mt-1.5 text-right" x-text="subtotal(b.no)"></div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2">
                                <div class="text-[10px] font-semibold text-blue-500 uppercase">Subtotal Tahap I</div>
                                <div class="text-sm font-bold text-blue-700" x-text="sumTahap(1)"></div>
                            </div>
                            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2">
                                <div class="text-[10px] font-semibold text-emerald-600 uppercase">Subtotal Tahap II</div>
                                <div class="text-sm font-bold text-emerald-700" x-text="sumTahap(2)"></div>
                            </div>
                            <div class="rounded-lg bg-slate-800 border border-slate-900 px-3 py-2">
                                <div class="text-[10px] font-semibold text-slate-400 uppercase">Total Anggaran</div>
                                <div class="text-sm font-bold text-white" x-text="totalAll()"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-200 gap-3">
                        <div class="text-[11px]" x-show="error" x-cloak style="color:#dc2626" x-text="error"></div>
                        <div class="flex items-center gap-2 ml-auto">
                            <button @click="close()" class="px-5 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50">Tutup</button>
                            <button @click="submit()" :disabled="saving" class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold inline-flex items-center gap-2">
                                <svg x-show="saving" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                <span x-text="editingId ? 'Simpan Perubahan' : 'Simpan ke Anggaran'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = @json(url('/'));
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

const NAMA_BULAN = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const BULAN_KE = [1,2,3,4,5,6,7,8,9,10,11,12];

function makeAlokasi() {
    const a = {};
    BULAN_KE.forEach(m => a[m] = { volume: 0, satuan: '' });
    return a;
}

function makePickerState() {
    return { q: '', results: [], open: false, loading: false, value: '', label: '' };
}

function modalForm() {
    return {
        open: false,
        editingId: null,
        saving: false,
        error: '',
        months: BULAN_KE.map(m => ({ no: m, nama: NAMA_BULAN[m] })),
        form: { uraian: '', keterangan_kustom: '', harga_satuan: 0, kode_barang_id: null },
        alokasi: makeAlokasi(),
        pickers: { kegiatan: makePickerState(), rekening: makePickerState(), barang: makePickerState() },
        _timers: {},

        reset() {
            this.editingId = null;
            this.error = '';
            this.form = { uraian: '', keterangan_kustom: '', harga_satuan: 0, kode_barang_id: null };
            this.alokasi = makeAlokasi();
            this.pickers = { kegiatan: makePickerState(), rekening: makePickerState(), barang: makePickerState() };
        },

        close() {
            if (window.__rkasModalHost) window.__rkasModalHost.close();
        },

        async debouncedSearch(key) {
            clearTimeout(this._timers[key]);
            this._timers[key] = setTimeout(() => this.search(key), 250);
        },

        async search(key) {
            const url = key === 'kegiatan' ? '/api/search/kegiatan' : key === 'rekening' ? '/api/search/rekening' : '/api/search/barang';
            const p = this.pickers[key];
            p.loading = true;
            p.open = true;
            try {
                const res = await fetch(url + '?q=' + encodeURIComponent(p.q));
                const d = await res.json();
                p.results = d.results || [];
            } catch (e) {
                p.results = [];
            } finally {
                p.loading = false;
            }
        },

        selectKegiatan(r) {
            const p = this.pickers.kegiatan;
            p.q = r.text; p.value = r.id; p.label = r.text; p.open = false;
        },
        selectRekening(r) {
            const p = this.pickers.rekening;
            p.q = r.text; p.value = r.id; p.label = r.text; p.open = false;
        },
        selectBarang(r) {
            const p = this.pickers.barang;
            p.q = r.text; p.value = r.id; p.label = ''; p.open = false;
            this.form.kode_barang_id = r.id;
        },

        fillFromCatalog(r) {
            if (r.nama) this.form.uraian = r.nama;
            if (r.satuan) {
                BULAN_KE.forEach(m => { if (!this.alokasi[m].satuan) this.alokasi[m].satuan = r.satuan; });
            }
            if (Number(r.harga) > 0 && this.form.harga_satuan === 0) this.form.harga_satuan = Number(r.harga);
        },

        subtotal(b) {
            return 'Rp ' + ((Number(this.alokasi[b].volume) || 0) * (this.form.harga_satuan || 0)).toLocaleString('id-ID');
        },
        sumTahap(phase) {
            const lo = phase === 1 ? 1 : 7, hi = phase === 1 ? 6 : 12;
            let total = 0;
            for (let b = lo; b <= hi; b++) total += (Number(this.alokasi[b].volume) || 0) * (this.form.harga_satuan || 0);
            return 'Rp ' + total.toLocaleString('id-ID');
        },
        totalAll() {
            let total = 0;
            BULAN_KE.forEach(m => total += (Number(this.alokasi[m].volume) || 0) * (this.form.harga_satuan || 0));
            return 'Rp ' + total.toLocaleString('id-ID');
        },

        async load(id) {
            this.reset();
            this.editingId = id;
            try {
                const res = await fetch('/rkas/' + id + '/json', { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                this.editingId = d.id;
                this.form = {
                    uraian: d.uraian || '',
                    keterangan_kustom: d.keterangan_kustom || '',
                    harga_satuan: Number(d.harga_satuan) || 0,
                    kode_barang_id: d.kode_barang_id || null
                };
                if (d.kegiatan_id) { this.pickers.kegiatan.value = d.kegiatan_id; this.pickers.kegiatan.label = d.kegiatan_text; this.pickers.kegiatan.q = d.kegiatan_text; }
                if (d.rekening_id) { this.pickers.rekening.value = d.rekening_id; this.pickers.rekening.label = d.rekening_text; this.pickers.rekening.q = d.rekening_text; }
                const a = makeAlokasi();
                for (const b in d.alokasi) {
                    a[b] = { volume: Number(d.alokasi[b].volume) || 0, satuan: d.alokasi[b].satuan || '' };
                }
                this.alokasi = a;
            } catch (e) {
                this.error = 'Gagal memuat data item: ' + e.message;
            }
        },

        async submit() {
            this.error = '';
            const keg = this.pickers.kegiatan, rek = this.pickers.rekening;
            if (!keg.value) { this.error = 'Silakan pilih Kegiatan terlebih dahulu.'; return; }
            if (!rek.value) { this.error = 'Silakan pilih Kode Rekening terlebih dahulu.'; return; }
            if (!this.form.uraian.trim()) { this.error = 'Uraian wajib diisi.'; return; }

            const payload = {
                _token: CSRF,
                master_program_id: keg.value,
                master_kode_rekening_id: rek.value,
                kode_barang_id: this.form.kode_barang_id || null,
                uraian: this.form.uraian,
                keterangan_kustom: this.form.keterangan_kustom || null,
                harga_satuan: this.form.harga_satuan || 0,
                alokasi: {}
            };
            BULAN_KE.forEach(m => {
                payload.alokasi[m] = { volume: Number(this.alokasi[m].volume) || 0, satuan: (this.alokasi[m].satuan || '').trim() };
            });

            this.saving = true;
            try {
                const url = this.editingId ? '/rkas/' + this.editingId + '/update' : '/rkas/store';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    let msg = data.message || 'Terjadi kesalahan.';
                    if (data.errors) msg = Object.values(data.errors).flat().join('; ');
                    this.error = msg;
                    return;
                }
                window.location.reload();
            } catch (e) {
                this.error = 'Gagal menyimpan: ' + e.message;
            } finally {
                this.saving = false;
            }
        }
    };
}

function getModal() {
    const el = document.querySelector('#rkas-modal [x-data="modalForm()"]');
    return el && el._x_dataStack ? el._x_dataStack[0] : null;
}

window.__rkasModalHost = {
    openNew() {
        const m = getModal();
        if (!m) return;
        m.reset();
        document.getElementById('rkas-modal').classList.remove('hidden');
    },
    openEdit(id) {
        const m = getModal();
        if (!m) return;
        document.getElementById('rkas-modal').classList.remove('hidden');
        m.load(id);
    },
    close() {
        document.getElementById('rkas-modal').classList.add('hidden');
    }
};

document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    if (btn.getAttribute('data-action') === 'edit') {
        window.__rkasModalHost.openEdit(id);
    } else if (btn.getAttribute('data-action') === 'delete') {
        if (!confirm('Hapus item anggaran ini?')) return;
        fetch('/rkas/' + id + '/delete', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        }).finally(() => window.location.reload());
    }
});

document.getElementById('btn-open-modal').addEventListener('click', function () {
    window.__rkasModalHost.openNew();
});
</script>
@endsection