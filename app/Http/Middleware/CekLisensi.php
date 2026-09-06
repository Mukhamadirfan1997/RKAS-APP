<?php

namespace App\Http\Middleware;

use App\Services\LisensiService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CekLisensi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (LisensiService::isReadOnlyMode()) {
            // Jika request expects JSON (API/autocomplete) — return JSON 403
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masa percobaan sudah berakhir. Masukkan Kode Aktivasi untuk melanjutkan.',
                    'redirect' => route('aktivasi.index'),
                ], 403);
            }
            return redirect()->route('aktivasi.index')->withErrors(['error' => 'Masa percobaan sudah berakhir. Masukkan Kode Aktivasi untuk melanjutkan.']);
        }
        return $next($request);
    }
}
