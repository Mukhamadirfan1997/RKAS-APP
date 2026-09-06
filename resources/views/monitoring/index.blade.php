@extends('layouts.app')

@section('content')
@php
    $statusBadge = [
        'sesuai' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30',
        'melebihi' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
        'kurang' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
    ];
    $statusLabel = ['sesuai' => 'SESUAI', 'melebihi' => 'MELEBIHI', 'kurang' => 'KURANG'];
    $barColor = ['sesuai' => 'bg-emerald-500', 'melebihi' => 'bg-red-500', 'kurang' => 'bg-amber-500'];
@endphp

<div class="space-y-6" x-data="{ tab: 'ringkasan' }">
    @if(session('success'))
        <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400">{{ session('success') }}</div>
    @endif

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Review &amp; Monitoring Kepatuhan JUKNIS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Permendikdasmen No. 8/2026 — referensi sebelum pengisian ARKAS resmi.</p>
            <div class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 rounded-full text-[11px] font-bold border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                Status Sekolah: {{ ucfirst($summary['status_sekolah'] ?? 'negeri') }} · batas honor {{ $summary['honor']['batas_persen'] ?? 20 }}%
                <span class="opacity-60">· Pagu Rp {{ number_format($summary['pagu_total'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">Dashboard</a>
            <a href="{{ route('rkas.index') }}" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-md shadow-blue-600/20">Lembar Kerja</a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/50 w-fit">
        <button @click="tab='ringkasan'" :class="tab==='ringkasan' ? 'bg-white dark:bg-slate-700 shadow text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">Ringkasan Kepatuhan (Otomatis)</button>
        <button id="tour-monitoring-pemetaan" @click="tab='pemetaan'" :class="tab==='pemetaan' ? 'bg-white dark:bg-slate-700 shadow text-slate-800 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">Konfigurasi Pemetaan Manual</button>
    </div>

    <!-- Ringkasan -->
    <div x-show="tab==='ringkasan'" x-cloak class="space-y-6">
        <div id="tour-monitoring-ringkasan" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach([
                ['key' => 'honor', 'label' => 'Honor Belanja Pegawai', 'desc' => 'batas maksimal', 'batasLabel' => 'maks'],
                ['key' => 'buku', 'label' => 'Anggaran Buku', 'desc' => 'batas minimal', 'batasLabel' => 'min'],
                ['key' => 'sarpras', 'label' => 'Sarana & Prasarana', 'desc' => 'batas maksimal', 'batasLabel' => 'maks'],
                ['key' => 'tahap1', 'label' => 'Alokasi Tahap I (Jan–Jun)', 'desc' => 'minimal', 'batasLabel' => 'min'],
            ] as $c)
                @php $d = $summary[$c['key']]; $pct = (float)($d['persen'] ?? 0); $batas = (float)($d['batas_persen'] ?? 0); $status = $d['status'] ?? 'sesuai'; @endphp
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $c['label'] }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $statusBadge[$status] }}">{{ $statusLabel[$status] }}</span>
                    </div>
                    <div class="text-xl font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($d['total'] ?? 0, 0, ',', '.') }}</div>
                    <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $pct }}% · {{ $c['desc'] }} {{ $batas }}% (Rp {{ number_format($d['batas_nominal'] ?? 0, 0, ',', '.') }})</div>
                    <!-- Progress terhadap pagu -->
                    <div class="mt-3 relative h-2 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                        <div class="absolute inset-y-0 rounded-full {{ $barColor[$status] }}" style="width: {{ min(100, $pct) }}%"></div>
                        @if($batas > 0 && $batas < 100)
                            <div class="absolute inset-y-0 w-0.5 bg-slate-800/60 dark:bg-white/70" style="left: {{ min(100, $batas) }}%" title="Batas {{ $batas }}%"></div>
                        @endif
                    </div>
                    <div class="mt-1 flex justify-between text-[10px] text-slate-400">
                        <span>0%</span>
                        <span class="font-semibold {{ $status === 'melebihi' ? 'text-red-500' : ($status === 'kurang' ? 'text-amber-500' : 'text-emerald-500') }}">
                            @if($status === 'melebihi')
                                Kelebihan Rp {{ number_format(abs($d['sisa'] ?? 0), 0, ',', '.') }}
                            @elseif($status === 'kurang' && $c['key'] !== 'tahap1')
                                Kurang Rp {{ number_format($d['sisa'] ?? 0, 0, ',', '.') }}
                            @elseif($status === 'kurang' && $c['key'] === 'tahap1')
                                Kurang Rp {{ number_format($d['sisa'] ?? 0, 0, ',', '.') }} ke 50%
                            @else
                                Sisa kuota Rp {{ number_format($d['sisa'] ?? 0, 0, ',', '.') }}
                            @endif
                        </span>
                        <span>{{ $batas }}%</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card p-4 flex flex-wrap gap-2 text-xs items-center">
            <span class="font-bold text-slate-700 dark:text-slate-200">Info:</span>
            <span class="text-slate-500 dark:text-slate-400">Ringkasan dihitung <b>otomatis</b> via <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 font-mono text-[11px]">JuknisValidator</code> (kombinasi kode program + jenis belanja/prefix rekening/keyword uraian, honor &gt; buku &gt; sarpras). Tidak tergantung pemetaan manual di tab sebelah.</span>
        </div>

        <!-- Checklist Revisi sebagai tabel -->
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Checklist Revisi — Item Belum Terpetakan Manual</h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Hanya untuk pemetaan manual tab Konfigurasi — tidak mempengaruhi status SESUAI di atas. {{ $unmapped->count() }} item.</p>
                </div>
                <span class="text-xs px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300">{{ $unmapped->count() }} belum terpetakan</span>
            </div>
            @if($unmapped->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-emerald-600 dark:text-emerald-400 font-semibold">✓ Semua rekening sudah terpetakan di tab Konfigurasi.</div>
            @else
                <div class="overflow-x-auto max-h-[420px]">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">Uraian</th>
                                <th class="px-4 py-2 text-left">Kegiatan</th>
                                <th class="px-4 py-2 text-left">Rekening</th>
                                <th class="px-4 py-2 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @foreach($unmapped->take(100) as $item)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-2 font-medium text-slate-700 dark:text-slate-200">{{ $item->uraian }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400">{{ $item->program->kode ?? '—' }} · {{ $item->program->nama ?? '-' }}</td>
                                    <td class="px-4 py-2 text-xs font-mono text-slate-600 dark:text-slate-300">{{ $item->kodeRekening->kode ?? 'tanpa rekening' }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-slate-700 dark:text-slate-200">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($unmapped->count() > 100)
                    <div class="px-4 py-2 text-[11px] text-slate-400 text-center border-t border-slate-200 dark:border-slate-700/60">Menampilkan 100 dari {{ $unmapped->count() }} — filter di Lembar Kerja untuk revisi.</div>
                @endif
            @endif
        </div>
    </div>

    <!-- Pemetaan Manual — Centang ala SmartRKAS -->
    <div x-show="tab==='pemetaan'" x-cloak class="space-y-4">
        <div class="card p-4 text-xs text-slate-500 dark:text-slate-400">
            Pemetaan manual <b>Kategori JUKNIS ↔ Kode Rekening</b> ala SmartRKAS — centang rekening yang termasuk kategori. Simpan per kategori. Pencarian memfilter daftar; checklist di tab Ringkasan mengacu ke sini.
        </div>

        @forelse($kategoriList as $kategori)
            @php $mappedIds = $kategori->rekenings->pluck('id')->toArray(); @endphp
            <div class="card overflow-hidden" x-data="{ q: '' }">
                <div class="px-5 py-3 bg-slate-50 dark:bg-slate-700/30 border-b border-slate-200 dark:border-slate-700/60 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $kategori->nama }}</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500">Arah: <b>{{ ucfirst($kategori->arah) }}</b> · Batas: <b>{{ $kategori->batas_persen }}%</b> · <span class="font-semibold text-blue-600 dark:text-blue-400">{{ count($mappedIds) }} kode terpetakan</span></div>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400">ID: {{ $kategori->id }}</span>
                </div>

                <form method="POST" action="{{ route('monitoring.juknis.mapping') }}" class="p-5 space-y-3">
                    @csrf
                    <input type="hidden" name="kategori_juknis_id" value="{{ $kategori->id }}">

                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" x-model="q" placeholder="Cari kode / nama / jenis — filter daftar centang…" class="input flex-1" autocomplete="off">
                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-500 whitespace-nowrap cursor-pointer">
                            <input type="checkbox" @change="const c=$el.checked; $el.closest('form').querySelectorAll('.rek-check').forEach(cb=>{ if(cb.closest('[data-visible]')?.dataset.visible !== 'false') cb.checked=c; })">
                            Pilih terlihat
                        </label>
                    </div>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700/60 overflow-hidden">
                        <div class="max-h-[260px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50 p-2 space-y-2">
                            @foreach($rekeningByJenis as $jenisNama => $reks)
                                @php $checkedInGroup = $reks->filter(fn($r)=>in_array($r->id,$mappedIds))->count(); @endphp
                                <details class="group" {{ $checkedInGroup > 0 ? 'open' : '' }}>
                                    <summary class="flex items-center justify-between px-2 py-1.5 bg-slate-50 dark:bg-slate-800/40 rounded cursor-pointer list-none">
                                        <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">{{ $jenisNama }} <span class="font-normal normal-case text-slate-400">({{ $reks->count() }})</span></span>
                                        <span class="flex items-center gap-2">
                                            @if($checkedInGroup>0)<span class="text-[11px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">{{ $checkedInGroup }} terpilih</span>@endif
                                            <span class="text-slate-400 group-open:rotate-180 transition-transform text-xs">▼</span>
                                        </span>
                                    </summary>
                                    <div class="mt-1 space-y-0.5">
                                        @foreach($reks as $rek)
                                            <label class="flex items-start gap-2 px-2 py-1 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer" data-visible="true" x-show="!q || ('{{ strtolower($rek->kode . ' ' . $rek->nama . ' ' . $jenisNama) }}'.includes(q.toLowerCase()))" x-bind:data-visible="!q || ('{{ strtolower($rek->kode . ' ' . $rek->nama . ' ' . $jenisNama) }}'.includes(q.toLowerCase())) ? 'true' : 'false'">
                                                <input type="checkbox" name="kode_rekening[]" value="{{ $rek->id }}" {{ in_array($rek->id, $mappedIds) ? 'checked' : '' }} class="rek-check mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-mono text-blue-600 dark:text-blue-400 font-bold">{{ $rek->kode }}</div>
                                                    <div class="text-xs text-slate-700 dark:text-slate-200 leading-tight truncate">{{ $rek->nama }}</div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-[11px] text-slate-400">Centang lalu Simpan — kosongkan semua untuk hapus pemetaan.</span>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-md shadow-blue-600/20">Simpan Pemetaan</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="card p-8 text-center text-xs text-slate-400">Belum ada kategori JUKNIS. Jalankan seeder JuknisSeeder.</div>
        @endforelse
    </div>
</div>

<!-- Pembatas visual — Cek RKA Gelondongan (terpisah dari section JUKNIS di atas) -->
<div class="pt-8 mt-10 border-t-2 border-slate-200 dark:border-slate-700"></div>

<div class="space-y-4" id="tour-monitoring-gelondongan">
    <div>
        <h2 class="text-lg font-extrabold text-slate-800 dark:text-white tracking-tight">Cek RKA Gelondongan (3 Kategori Dinas)</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Rekap 3 kolom sesuai file PAK Dinas — mapping via <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 font-mono text-[11px]">config/rka_gelondongan.php</code>.
            <span class="text-slate-400">· Tahun {{ $tahunAnggaran->tahun }} · Sumber: jumlah_koreksi (jumlah + koreksi)</span>
        </p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 text-[11px] uppercase tracking-wide">
                        <th class="px-4 py-3 text-left font-bold">Kategori</th>
                        <th class="px-4 py-3 text-right font-bold">Target</th>
                        <th class="px-4 py-3 text-right font-bold">Realisasi</th>
                        <th class="px-4 py-3 text-right font-bold">Selisih <span class="normal-case font-normal text-[10px]">(Target − Realisasi)</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($gelondongan['rows'] ?? [] as $row)
                        <tr class="bg-white dark:bg-slate-800">
                            <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">
                                {{ $row['label'] }}
                                @if($row['key'] === 'barang_jasa')
                                    <div class="text-[10px] font-normal text-slate-400">6 jenis</div>
                                @elseif($row['key'] === 'modal_aset_lainnya')
                                    <div class="text-[10px] font-normal text-slate-400">Aset + Buku</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($row['target'] === null)
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-700/40 dark:text-slate-400 dark:border-slate-600">Target belum diisi</span>
                                @else
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">Rp {{ number_format($row['target'], 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">Rp {{ number_format($row['realisasi'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($row['status'] === 'belum_diisi')
                                    <span class="text-[11px] text-slate-400">—</span>
                                @elseif($row['status'] === 'sesuai')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">Sesuai Target</span>
                                @elseif($row['status'] === 'kurang')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">Kurang Rp {{ number_format(abs($row['selisih']), 0, ',', '.') }} dari target</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30">Lebih Rp {{ number_format(abs($row['selisih']), 0, ',', '.') }} dari target</span>
                                @endif
                                @if($row['target'] !== null)
                                    <div class="text-[10px] font-mono text-slate-400 mt-0.5">{{ $row['target'] > $row['realisasi'] ? '-' : ($row['target'] < $row['realisasi'] ? '+' : '') }}Rp {{ number_format(abs($row['selisih']), 0, ',', '.') }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 dark:bg-slate-800/40 border-t-2 border-slate-200 dark:border-slate-700">
                    <tr class="font-bold">
                        <td class="px-4 py-3 text-left text-slate-700 dark:text-slate-200">Jumlah</td>
                        <td class="px-4 py-3 text-right">
                            @if($gelondongan['jumlah_target'] === null)
                                <span class="text-[11px] font-medium text-slate-400">—</span>
                            @else
                                <span class="text-slate-800 dark:text-white">Rp {{ number_format($gelondongan['jumlah_target'], 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-900 dark:text-white">Rp {{ number_format($gelondongan['jumlah'] ?? 0, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($gelondongan['jumlah_target'] !== null)
                                @php $st = (float)($gelondongan['jumlah_target'] - $gelondongan['jumlah']); @endphp
                                @if(abs($st) < 1000.5)
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">Sesuai</span>
                                @elseif($gelondongan['jumlah'] < $gelondongan['jumlah_target'])
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">Kurang Rp {{ number_format(abs($st), 0, ',', '.') }}</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30">Lebih Rp {{ number_format(abs($st), 0, ',', '.') }}</span>
                                @endif
                            @else
                                <span class="text-[11px] text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr class="border-t border-slate-200 dark:border-slate-700/60">
                        <td colspan="2" class="px-4 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Pagu Total</td>
                        <td colspan="2" class="px-4 py-3 text-right font-extrabold text-slate-800 dark:text-white">Rp {{ number_format($gelondongan['pagu_total'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    <tr class="border-t border-slate-200 dark:border-slate-700/60">
                        <td colspan="2" class="px-4 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Selisih dari Pagu <span class="font-normal normal-case text-[11px] text-slate-400">(Jumlah − Pagu Total)</span></td>
                        <td colspan="2" class="px-4 py-3 text-right">
                            @if($gelondongan['is_sesuai'] ?? false)
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">Sesuai Pagu ✓</span>
                                <div class="text-[11px] text-slate-400 mt-1">Rp 0</div>
                            @else
                                @php $selisih = (float)($gelondongan['selisih'] ?? 0); @endphp
                                @if($selisih < 0)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600">Rp {{ number_format(abs($selisih), 0, ',', '.') }} belum dianggarkan</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600">Rp {{ number_format($selisih, 0, ',', '.') }} melebihi pagu</span>
                                @endif
                                <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400 mt-1">({{ $selisih < 0 ? '-' : '+' }}Rp {{ number_format(abs($selisih), 0, ',', '.') }})</div>
                            @endif
                        </td>
                    </tr>
                    @if($gelondongan['jumlah_target'] !== null)
                    <tr class="border-t border-slate-200 dark:border-slate-700/60">
                        <td colspan="2" class="px-4 py-3 text-right text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wide">Selisih Target vs Pagu <span class="font-normal normal-case text-[11px] text-slate-400">(Jumlah Target − Pagu)</span></td>
                        <td colspan="2" class="px-4 py-3 text-right">
                            @php $stp = (float)($gelondongan['selisih_target_pagu'] ?? 0); @endphp
                            @if(abs($stp) < 0.5)
                                <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">Sesuai Pagu ✓</span>
                            @elseif($stp < 0)
                                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30">Target kurang Rp {{ number_format(abs($stp), 0, ',', '.') }}</span>
                            @else
                                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30">Target lebih Rp {{ number_format($stp, 0, ',', '.') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
        <div class="px-4 py-2.5 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-200 dark:border-slate-700/50 text-[11px] text-slate-400 dark:text-slate-500">
            Mapping: <span class="font-mono">Barang &amp; Jasa = 6 jenis</span> (Barang, Barang Persediaan, Cetak, Jasa, Jasa Pemeliharaan, Perjalanan Dinas) ·
            <span class="font-mono">Modal Mesin = Peralatan &amp; Mesin</span> ·
            <span class="font-mono">Modal Aset Lainnya = Aset Tetap Lainnya + Modal Buku</span> — total 3 kolom = grand jumlah_koreksi. Selisih ≠ 0 saat Draft itu normal. Target dari Pengaturan → Pagu (nullable).
        </div>
    </div>
</div>

@endsection
