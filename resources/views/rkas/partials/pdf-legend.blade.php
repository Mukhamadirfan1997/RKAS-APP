{{-- Legend Kontrol (& Validasi) — per_bulan tanpa Validasi --}}
@php $showValidasi = $showValidasi ?? true; @endphp
<div class="legend">
    <strong>Keterangan Kolom Kontrol{{ $showValidasi ? ' & Validasi' : '' }}:</strong><br>
    <span class="dot" style="background:#dcfce7; border:1px solid #86efac;"></span> <strong>Kontrol OK</strong> = Harga Satuan sesuai Harga Katalog ARKAS &nbsp;|&nbsp;
    <span class="dot" style="background:#fee2e2; border:1px solid #fca5a5;"></span> <strong>SELISIH</strong> = Harga berbeda dari Katalog
    @if($showValidasi)
        &nbsp;&nbsp;
        <span class="dot" style="background:#dcfce7; border:1px solid #86efac;"></span> <strong>Validasi BENAR</strong> = Jumlah (Vol×Harga) = Tahap I + Tahap II &nbsp;|&nbsp;
        <span class="dot" style="background:#fee2e2; border:1px solid #fca5a5;"></span> <strong>SALAH</strong> = Tidak konsisten (perlu perbaiki alokasi bulanan)
    @endif
</div>
