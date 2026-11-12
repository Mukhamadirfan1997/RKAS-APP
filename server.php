<?php

/**
 * Router untuk PHP built-in server KARSA.
 *
 * Dipakai DUA lingkungan sekaligus:
 *  - Mode aplikasi terpasang (src-tauri/src/lib.rs menjalankan `php -S ... server.php`)
 *  - Mode pengembangan (`php artisan serve` otomatis memakai base_path('server.php')
 *    bila berkas ini ada — lihat Illuminate\Foundation\Console\ServeCommand)
 *
 * Alasan berkas ini ada: PHP built-in server menyajikan berkas statis tanpa
 * header cache dan tanpa kompresi sama sekali. Untuk aplikasi lokal itu memang
 * ringan, tetapi klien LAN (komputer kedua lewat WiFi) benar-benar mengunduh
 * ulang seluruh CSS/JS setiap halaman. Router ini:
 *  1. memberi Cache-Control panjang untuk aset ber-hash di /build/assets/
 *     (nama berkas berubah setiap build, jadi aman di-cache selamanya),
 *  2. mengirim ETag + Last-Modified dan menjawab 304 Not Modified,
 *  3. meng-gzip berkas teks (CSS/JS/SVG/JSON/TXT) bila klien mendukungnya.
 *
 * Berkas PHP dan berkas titik (.env, .htaccess, .user.ini) TIDAK pernah
 * disajikan langsung — permintaan seperti itu diteruskan ke Laravel.
 */
$root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? (__DIR__.'/public'), '/\\');
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($uri === '' || $uri === '/') {
    require $root.'/index.php';

    return true;
}

$berkas = $root.$uri;
$ekstensi = strtolower(pathinfo($berkas, PATHINFO_EXTENSION));

// Tolak: berkas PHP, berkas titik, dan path yang mencoba keluar dari /public.
if (
    str_contains($uri, "\0")
    || str_contains($uri, '..')
    || str_starts_with(basename($berkas), '.')
    || $ekstensi === 'php'
    || ! is_file($berkas)
) {
    require $root.'/index.php';

    return true;
}

sajikan_berkas($berkas, $uri);

return true;

/**
 * Kirim satu berkas statis lengkap dengan header cache, ETag, dan gzip.
 */
function sajikan_berkas(string $berkas, string $uri): void
{
    $mime = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'txt' => 'text/plain; charset=UTF-8',
        'md' => 'text/markdown; charset=UTF-8',
        'pdf' => 'application/pdf',
        'webmanifest' => 'application/manifest+json',
    ][strtolower(pathinfo($berkas, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

    $ukuran = (int) filesize($berkas);
    $mtime = (int) filemtime($berkas);
    $etag = '"'.dechex($mtime).'-'.dechex($ukuran).'"';

    // Aset hasil build punya hash di nama berkas (app-XXXXXXXX.js) → abadi.
    // Aset tanpa hash (ikon, logo) cukup satu hari agar perubahan ikut terbawa.
    $abadi = str_starts_with($uri, '/build/assets/');

    header('Cache-Control: '.($abadi ? 'public, max-age=31536000, immutable' : 'public, max-age=86400'));
    header('ETag: '.$etag);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
    header('Vary: Accept-Encoding');
    header('X-Content-Type-Options: nosniff');

    $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $ifModified = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');

    // Bandingkan toleran: sebagian klien mengirim ETag tanpa tanda kutip,
    // dengan prefix W/ (weak), atau berupa daftar dipisah koma.
    $cocokEtag = false;
    foreach (explode(',', $ifNoneMatch) as $kandidat) {
        $kandidat = trim($kandidat);
        if ($kandidat === '') {
            continue;
        }
        if ($kandidat === '*' || trim(str_replace('W/', '', $kandidat), '"') === trim($etag, '"')) {
            $cocokEtag = true;
            break;
        }
    }
    $cocokWaktu = $ifNoneMatch === '' && $ifModified !== '' && (int) strtotime($ifModified) >= $mtime;

    if ($cocokEtag || $cocokWaktu) {
        http_response_code(304);

        return;
    }

    header('Content-Type: '.$mime);

    $metodeKepala = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
    $teks = str_starts_with($mime, 'text/')
        || in_array($mime, ['application/javascript; charset=UTF-8', 'application/json', 'image/svg+xml', 'application/manifest+json'], true);
    $terimaGzip = stripos((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') !== false;

    if ($teks && $terimaGzip && $ukuran > 1024 && function_exists('gzencode')) {
        $isi = (string) file_get_contents($berkas);
        $gzip = gzencode($isi, 6);

        if ($gzip !== false && strlen($gzip) < strlen($isi)) {
            header('Content-Encoding: gzip');
            header('Content-Length: '.strlen($gzip));
            if (! $metodeKepala) {
                echo $gzip;
            }

            return;
        }
    }

    header('Content-Length: '.$ukuran);
    if (! $metodeKepala) {
        readfile($berkas);
    }
}
