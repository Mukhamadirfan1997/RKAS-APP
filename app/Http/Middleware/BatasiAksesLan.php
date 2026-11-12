<?php

namespace App\Http\Middleware;

use App\Services\JaringanService;
use App\Services\LanConfigService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga akses jaringan KARSA.
 *
 * Aturan (fail-closed):
 * 1. Localhost / komputer ini sendiri SELALU boleh — mode Desktop Offline tidak
 *    pernah terganggu walaupun LAN dimatikan.
 * 2. Bila "Akses LAN" dimatikan (default instalasi baru), semua permintaan dari
 *    komputer lain ditolak 403 — server tetap listen di 0.0.0.0, tetapi tidak
 *    ada data yang bisa diambil dari jaringan.
 * 3. Bila LAN diaktifkan, klien WAJIB berasal dari subnet adapter FISIK aktif
 *    komputer ini. Alamat RFC1918 milik VPN / adapter virtual tetap ditolak.
 *
 * Middleware ini hanya SATU lapis. Lapis lainnya: Windows Firewall (hanya
 * php.exe KARSA, profil Private/Domain) dan autentikasi login yang sudah ada
 * (tidak pernah dimatikan).
 */
class BatasiAksesLan
{
    public function __construct(
        private JaringanService $jaringan,
        private LanConfigService $lan
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $this->jaringan->normalisasiIp($request->ip());

        // Lapis 1 — komputer ini sendiri.
        if ($this->jaringan->ipLokal($ip)) {
            return $next($request);
        }

        // Lapis 2 — akses LAN dimatikan operator.
        if (! $this->lan->aktif()) {
            $this->jaringan->catatPenolakan($ip, 'lan_nonaktif', $request->path());

            return $this->tolak(
                $request,
                'nonaktif',
                'Akses melalui jaringan lokal sedang dimatikan di komputer ini.'
            );
        }

        // Lapis 3 — hanya subnet jaringan lokal fisik yang diizinkan.
        if ($ip === '' || ! $this->jaringan->ipDiizinkan($ip)) {
            $this->jaringan->catatPenolakan($ip, 'luar_subnet', $request->path());

            $subnet = implode(', ', $this->jaringan->subnetDiizinkan());
            $pesan = $ip === ''
                ? 'Alamat komputer Anda tidak bisa dibaca, jadi akses ditolak.'
                : "Komputer dengan alamat {$ip} tidak termasuk jaringan lokal yang diizinkan";

            if ($subnet !== '') {
                $pesan .= " (jaringan yang diizinkan: {$subnet}).";
            } else {
                $pesan .= ' (tidak ada jaringan lokal aktif di komputer KARSA).';
            }

            return $this->tolak($request, 'luar_subnet', $pesan);
        }

        return $next($request);
    }

    private function tolak(Request $request, string $alasan, string $pesan): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'alasan' => $alasan,
                'message' => $pesan,
            ], 403);
        }

        return response()->view('errors.lan', [
            'alasan' => $alasan,
            'pesan' => $pesan,
        ], 403);
    }
}
