{{-- Pita REKENING full-width — dipakai ketiga varian PDF cetak --}}
@php
    $colspan = $colspan ?? 12;
    $rekKode = $rek['kode'] ?? $rek->kode ?? '';
    $rekNama = $rek['nama'] ?? $rek->nama ?? '';
@endphp
<tr class="rekening-row">
    <td colspan="{{ $colspan }}" style="background:#fffbeb; border:1px solid #fde68a; padding:5px 8px; font-weight:700; color:#92400e; font-size:9px;">REKENING: [{{ $rekKode }}] {{ $rekNama }}</td>
</tr>
