<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kompresi gzip untuk respons dinamis (HTML halaman, JSON API).
 *
 * Kenapa perlu: PHP built-in server tidak pernah meng-gzip apa pun. Untuk
 * aplikasi lokal dampaknya kecil, tetapi klien LAN (komputer kedua lewat WiFi)
 * mengunduh halaman 70–100 KB pada setiap klik; dengan gzip menjadi ±10 KB.
 *
 * Aturan aman:
 *  - hanya bila klien mengirim `Accept-Encoding: gzip`,
 *  - hanya status 200 dan isi > 1 KB (di bawah itu gzip malah membesar),
 *  - hanya tipe teks (HTML/JSON/JS/SVG) — PNG/WOFF2/PDF tidak disentuh,
 *  - tidak menimpa Content-Encoding yang sudah ada (mis. unduhan berkas),
 *  - StreamedResponse/BinaryFileResponse (unduhan PDF/Excel/backup) dilewati.
 */
class KompresiResponse
{
    private const MINIMAL_BYTE = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->bolehDikompresi($request, $response)) {
            return $response;
        }

        $isi = $response->getContent();
        if ($isi === false || $isi === null || $isi === '') {
            return $response;
        }

        $gzip = gzencode($isi, 6);
        if ($gzip === false || strlen($gzip) >= strlen($isi)) {
            return $response;
        }

        $response->setContent($gzip);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($gzip));
        // Jangan hilangkan nilai Vary lain yang mungkin sudah dipasang Laravel.
        $response->setVary('Accept-Encoding', false);

        return $response;
    }

    private function bolehDikompresi(Request $request, Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        if (! $request->isMethodCacheable() && $request->getMethod() !== 'HEAD') {
            return false;
        }

        if (! $this->klienDukungGzip($request)) {
            return false;
        }

        $ukuran = $response->headers->get('Content-Length');
        if (is_numeric($ukuran) && (int) $ukuran < self::MINIMAL_BYTE) {
            return false;
        }

        $tipe = strtolower((string) $response->headers->get('Content-Type'));

        $tipeTeks = str_starts_with($tipe, 'text/')
            || str_contains($tipe, 'json')
            || str_contains($tipe, 'javascript')
            || str_contains($tipe, 'svg')
            || str_contains($tipe, 'xml');

        if (! $tipeTeks) {
            return false;
        }

        $isi = $response->getContent();

        return is_string($isi) && strlen($isi) >= self::MINIMAL_BYTE;
    }

    private function klienDukungGzip(Request $request): bool
    {
        $terima = strtolower((string) $request->headers->get('Accept-Encoding'));

        if (! str_contains($terima, 'gzip')) {
            return false;
        }

        // Hormati penolakan eksplisit: "gzip;q=0".
        return ! preg_match('/gzip\s*;\s*q\s*=\s*0(?:[.,]0*)?\s*(?:,|$)/', $terima);
    }
}
