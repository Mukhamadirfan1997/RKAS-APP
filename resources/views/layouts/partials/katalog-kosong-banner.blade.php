@php
    use App\Support\Instalasi;
    try {
        $katalogKosong = Instalasi::katalogKosong();
    } catch (\Throwable $e) {
        $katalogKosong = false;
    }
@endphp
@if($katalogKosong)
    <div class="px-4 py-3 bg-amber-500 text-white text-sm font-semibold text-center leading-relaxed">
        <span class="inline-flex items-center gap-2 flex-wrap justify-center">
            <span>Katalog kode barang belum terisi (0 baris).</span>
            <span>Pencarian barang di lembar kerja tidak akan menemukan apa pun. Muat katalog lewat menu di bawah, atau pasang ulang aplikasi.</span>
            <a href="{{ route('pengaturan.katalog') }}" class="ml-2 inline-flex items-center px-3 py-1 rounded-md bg-white text-amber-700 font-bold text-xs hover:bg-amber-50">Buka Update Katalog &rarr;</a>
        </span>
    </div>
@endif
