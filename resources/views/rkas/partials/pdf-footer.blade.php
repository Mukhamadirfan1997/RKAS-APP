{{-- Footer TTD — dipakai ketiga varian PDF --}}
<div class="footer">
    <table class="ttd">
        <tr>
            <td class="kolom" style="width:50%; text-align:center;">
                <p>Bendahara,</p>
                <div class="jarak-ttd"></div>
                <p><b>{{ $sekolah->nama_bendahara ?? '................................' }}</b></p>
                <p>NIP. {{ $sekolah->nip_bendahara ?? '........................' }}</p>
            </td>
            <td class="kolom" style="width:50%; text-align:center;">
                <p>Kepala Sekolah,</p>
                <div class="jarak-ttd"></div>
                <p><b>{{ $sekolah->nama_kepala_sekolah ?? '................................' }}</b></p>
                <p>NIP. {{ $sekolah->nip_kepala_sekolah ?? '........................' }}</p>
            </td>
        </tr>
    </table>
</div>
