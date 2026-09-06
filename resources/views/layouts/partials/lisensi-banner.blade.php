@php
    use App\Services\LisensiService;
    $showBanner = false;
    $bannerType = null; // 'warning' or 'readonly'
    $bannerSisa = 0;
    try {
        if (!LisensiService::isRejosoExempt()) {
            if (LisensiService::isReadOnlyMode()) {
                $showBanner = true;
                $bannerType = 'readonly';
            } elseif (LisensiService::isTrialActive()) {
                $sisa = LisensiService::sisaHari();
                if ($sisa <= 7) {
                    $showBanner = true;
                    $bannerType = 'warning';
                    $bannerSisa = $sisa;
                }
            }
        }
    } catch (\Throwable $e) {}
@endphp
@if($showBanner)
    @if($bannerType === 'readonly')
        <div class="px-4 py-2.5 bg-red-600 text-white text-xs font-semibold text-center">
            Mode Lihat-Saja — masa percobaan berakhir. <a href="{{ route('aktivasi.index') }}" class="underline font-bold hover:no-underline">Aktivasi Lisensi untuk kembali mengedit →</a>
        </div>
    @elseif($bannerType === 'warning')
        <div class="px-4 py-2.5 bg-amber-400 text-amber-900 text-xs font-semibold text-center">
            Masa percobaan tersisa {{ $bannerSisa }} hari. <a href="{{ route('aktivasi.index') }}" class="underline font-bold hover:no-underline">Aktivasi Lisensi →</a>
        </div>
    @endif
@endif
