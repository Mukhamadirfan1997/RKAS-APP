<?php

namespace App\Services;

use App\Models\Lisensi;
use App\Models\LisensiAktivasi;
use App\Models\PengaturanSekolah;
use App\Models\TahunAnggaran;
use Illuminate\Support\Str;

class LisensiService
{
    // Alphabet tanpa 0/O/1/I — 32 chars (Crockford style tanpa I,O,0,1)
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    // HMAC payload separator

    public static function getOrCreateDeviceCode(): string
    {
        $row = Lisensi::first();
        if ($row) {
            return $row->device_code;
        }
        $row = Lisensi::create([
            'device_code' => (string) Str::uuid(),
            'installed_at' => now(),
        ]);
        return $row->device_code;
    }

    public static function getLisensiRow(): ?Lisensi
    {
        $row = Lisensi::first();
        if ($row) return $row;
        // create if not exists
        return Lisensi::create([
            'device_code' => (string) Str::uuid(),
            'installed_at' => now(),
        ]);
    }

    public static function isRejosoExempt(): bool
    {
        $kec = PengaturanSekolah::value('kecamatan');
        if ($kec === null || trim((string)$kec) === '') {
            return false;
        }
        $normalized = strtolower(trim((string)$kec));
        return str_contains($normalized, 'rejoso');
    }

    public static function isTrialActive(): bool
    {
        $row = self::getLisensiRow();
        if (!$row || !$row->installed_at) return true; // if not set, consider active
        $trialHari = (int) config('lisensi.trial_hari', 30);
        return $row->installed_at->copy()->addDays($trialHari)->isFuture();
    }

    public static function sisaHari(): int
    {
        $row = self::getLisensiRow();
        if (!$row || !$row->installed_at) return (int) config('lisensi.trial_hari', 30);
        $end = $row->installed_at->copy()->addDays((int) config('lisensi.trial_hari', 30));
        if ($end->isPast()) return 0;
        // ceil days remaining
        $seconds = $end->diffInSeconds(now());
        return (int) ceil($seconds / 86400);
    }

    public static function getTrialEndDate(): ?\Carbon\Carbon
    {
        $row = Lisensi::first();
        if (!$row || !$row->installed_at) return null;
        return $row->installed_at->copy()->addDays((int) config('lisensi.trial_hari', 30));
    }

    public static function isYearLicensed(int $tahun): bool
    {
        $row = Lisensi::first();
        if (!$row) return false;
        return LisensiAktivasi::where('lisensi_id', $row->id)->where('tahun', $tahun)->exists();
    }

    public static function getActiveTahun(): ?TahunAnggaran
    {
        $active = TahunAnggaran::where('is_active', true)->first();
        if ($active) return $active;
        return TahunAnggaran::orderBy('tahun', 'desc')->first();
    }

    public static function isReadOnlyMode(): bool
    {
        if (self::isRejosoExempt()) return false;
        if (self::isTrialActive()) return false;
        $tahun = self::getActiveTahun();
        if (!$tahun) return false;
        if (self::isYearLicensed((int)$tahun->tahun)) return false;
        return true;
    }

    // Generate activation code: HMAC-SHA256 of "deviceCode|tahun" with checksum_seed, take first 10 bytes, base32 encode -> 16 chars grouped 4-4-4-4
    public static function generateActivationCode(string $deviceCode, int $tahun): string
    {
        $seed = (string) config('lisensi.checksum_seed', '');
        $payload = trim($deviceCode) . '|' . $tahun;
        $hmac = hash_hmac('sha256', $payload, $seed, true); // binary 32 bytes
        $bytes = substr($hmac, 0, 10); // 80 bits -> 16 base32 chars
        $encoded = self::base32Encode($bytes);
        // encoded should be 16 chars
        $encoded = strtoupper($encoded);
        // group 4-4-4-4
        return implode('-', str_split($encoded, 4));
    }

    public static function validateActivationCode(string $deviceCode, int $tahun, string $kode): bool
    {
        $expected = self::generateActivationCode($deviceCode, $tahun);
        $normalize = function (string $s): string {
            return strtoupper(str_replace(['-', ' ', '_'], '', trim($s)));
        };
        $a = $normalize($expected);
        $b = $normalize($kode);
        // hash_equals to prevent timing attack
        if (strlen($a) !== strlen($b)) {
            // still use hash_equals with same length dummy to avoid timing leak? But spec says hash_equals
            // We'll pad to same length comparison via ensure length check then hash_equals
            return false;
        }
        return hash_equals($a, $b);
    }

    // Store activation if valid; returns true if stored or already exists
    public static function tryActivate(string $deviceCode, int $tahun, string $kode): bool
    {
        $deviceCode = trim($deviceCode);
        if (!self::validateActivationCode($deviceCode, $tahun, $kode)) {
            return false;
        }
        $row = Lisensi::where('device_code', $deviceCode)->first();
        if (!$row) {
            // device_code mismatch — don't create new, reject
            return false;
        }
        LisensiAktivasi::updateOrCreate(
            ['lisensi_id' => $row->id, 'tahun' => $tahun],
            ['kode_aktivasi' => strtoupper(str_replace(['-', ' '], '', trim($kode))), 'activated_at' => now()]
        );
        return true;
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = self::ALPHABET;
        $binary = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        $chunks = str_split($binary, 5);
        $out = '';
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $index = bindec($chunk);
            $out .= $alphabet[$index];
        }
        return $out;
    }
}
