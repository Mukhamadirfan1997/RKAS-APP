@php
    use App\Services\DatabaseHealthService;
    $dbCorrupt = false;
    $dbCorruptMessage = null;
    try {
        $dbCorrupt = DatabaseHealthService::isCorrupt();
        if ($dbCorrupt) {
            $dbCorruptMessage = DatabaseHealthService::getCorruptMessage();
        }
    } catch (\Throwable $e) {}
@endphp
@if($dbCorrupt)
    <div class="px-4 py-3 bg-red-600 text-white text-sm font-semibold text-center leading-relaxed">
        <span class="inline-flex items-center gap-2 flex-wrap justify-center">
            <span>⚠️ Masalah terdeteksi pada database{{ $dbCorruptMessage ? ': '.e($dbCorruptMessage) : '.' }}</span>
            <span>SEGERA lakukan Restore dari Backup terakhir yang sehat. Jangan lanjutkan mengisi data sampai dipulihkan.</span>
            <a href="{{ route('backup.index') }}" class="ml-2 inline-flex items-center px-3 py-1 rounded-md bg-white text-red-700 font-bold text-xs hover:bg-red-50">Buka Halaman Backup →</a>
        </span>
    </div>
@endif
