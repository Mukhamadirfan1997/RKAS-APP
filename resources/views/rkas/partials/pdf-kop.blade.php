{{-- Kop RKAS — dipakai pdf-grouped, pdf-per-tahap, pdf-per-bulan --}}
@php
    $judulH2 = $kopJudul ?? 'Rincian Rencana Belanja Dana BOSP Reguler';
    $subP = $kopSub ?? ('Tahun Anggaran '.($tahunAnggaran->tahun ?? 2026).' · '.($tahunAnggaran->sumber_dana ?? 'BOSP REGULER').' · Rincian per Kegiatan dan Rekening Belanja');
    $st = strtoupper($tahunAnggaran->status_pengesahan ?? 'DRAFT');
@endphp
<div class="kop">
    <h1>KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) <span class="tahun">TA {{ $tahunAnggaran->tahun ?? 2026 }}</span></h1>
    <div class="sub">
        {{ $sekolah->nama_sekolah }} &middot; NPSN {{ $sekolah->npsn }}<br>
        {{ $sekolah->alamat }} {{ $sekolah->desa_kelurahan }} {{ $sekolah->kecamatan }} {{ $sekolah->kabupaten_kota }} {{ $sekolah->provinsi }}
    </div>
</div>

<h2 class="center">{{ $judulH2 }}</h2>
<p class="center">{{ $subP }}</p>
<div style="text-align:center; margin-bottom:10px;">
    <span class="status-badge" style="border-color: {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }}; color: {{ $st === 'DISAHKAN' ? '#059669' : ($st === 'PERGESERAN' ? '#d97706' : '#64748b') }};">{{ $st }}</span>
</div>
