<?php

namespace App\Http\Controllers;

use App\Models\PengaturanSekolah;
use App\Services\JaringanService;

class TentangController extends Controller
{
    public function index(JaringanService $jaringan)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah([
            'nama_sekolah' => null,
            'kecamatan' => null,
            'kabupaten_kota' => null,
        ]);

        // Single source of truth: config/karsa.php (prioritas APP_VERSION env
        // dari lib.rs, fallback tauri.conf.json untuk dev). Jangan duplikasi
        // logika baca file di sini — agar production & dev konsisten.
        $version = config('karsa.version');

        // Changelog — dibaca dari berkas SETIAP halaman dibuka (tidak di-cache),
        // sehingga isi halaman selalu mengikuti CHANGELOG.md yang terpasang.
        // Lokasi berkas dibuat bisa diatur lewat config agar perilaku
        // "berkas tidak ada" tetap bisa diuji.
        //
        // Yang DITAMPILKAN hanya beberapa pembaruan terakhir (lihat
        // JUMLAH_RIWAYAT_TAMPIL) supaya halaman Tentang tidak memanjang.
        // Riwayat lengkap tetap utuh di CHANGELOG.md dan bisa diunduh lewat
        // route tentang.changelog.
        $changelogLengkap = $this->bacaChangelog();
        $changelogTotal = $this->hitungVersi($changelogLengkap);
        $changelogRaw = $this->potongChangelog($changelogLengkap, self::JUMLAH_RIWAYAT_TAMPIL);
        $changelogHtml = $this->renderMarkdownSimple($changelogRaw);
        $changelogDitampilkan = $this->hitungVersi($changelogRaw);

        // --- Informasi server internal (port dinamis) ---
        // Port TIDAK boleh disimpan di config: src-tauri/src/lib.rs memilih port
        // bebas setiap aplikasi dibuka (find_free_port), sehingga nilai tersimpan
        // akan cepat kedaluwarsa. Satu-satunya sumber akurat adalah request yang
        // sedang dilayani (Host header dari WebView2 atau browser).
        $request = request();
        $alamatServer = $request->getSchemeAndHttpHost();
        $portServer = (int) $request->getPort();
        $hostServer = $request->getHost();
        // lib.rs hanya menyuntik KARSA_DATA_DIR pada aplikasi terpasang (Tauri).
        $modeDesktop = trim((string) getenv('KARSA_DATA_DIR')) !== '';
        $versiPhp = PHP_VERSION;
        $versiLaravel = app()->version();

        // --- Akses Jaringan (LAN) ---
        // Semua angka dihitung ulang setiap halaman dibuka: IP LAN mengikuti
        // DHCP, jumlah klien mengikuti sesi yang masih aktif.
        $lan = $jaringan->ringkasan($portServer);
        // Host binding sebenarnya (dari lib.rs) — bukan tebakan dari Host header.
        $hostBinding = trim((string) getenv('KARSA_LISTEN_HOST'));
        if ($hostBinding === '') {
            $hostBinding = '0.0.0.0';
        }
        // Pengaturan hanya boleh diubah dari komputer utama (klien LAN tidak bisa).
        $dariKomputerUtama = $jaringan->ipLokal($request->ip());

        return view('tentang.index', compact(
            'sekolah',
            'version',
            'changelogHtml',
            'changelogTotal',
            'changelogDitampilkan',
            'alamatServer',
            'portServer',
            'hostServer',
            'modeDesktop',
            'versiPhp',
            'versiLaravel',
            'lan',
            'hostBinding',
            'dariKomputerUtama'
        ));
    }

    /**
     * Banyaknya pembaruan yang ditampilkan di halaman Tentang.
     * Sisanya tetap tersimpan di CHANGELOG.md dan bisa diunduh.
     */
    private const JUMLAH_RIWAYAT_TAMPIL = 3;

    /**
     * Hitung jumlah entri versi (`## [x.y.z]`) pada teks changelog.
     */
    private function hitungVersi(string $md): int
    {
        return (int) preg_match_all('/^##\s+\[[^\]]+\]/m', $md);
    }

    /**
     * Ambil hanya N pembaruan terakhir dari changelog, beserta judul
     * (baris sebelum entri versi pertama) sebagai pengantar.
     */
    private function potongChangelog(string $md, int $maks): string
    {
        if (trim($md) === '') {
            return '';
        }

        $bagian = preg_split('/(?=^##\s+\[)/m', $md, -1, PREG_SPLIT_NO_EMPTY);
        if ($bagian === false || $bagian === []) {
            return $md;
        }

        // preg_split di atas: elemen pertama = judul + pengantar (tanpa entri versi).
        $judul = str_starts_with(ltrim($bagian[0]), '## [') ? '' : array_shift($bagian);
        $entri = array_slice($bagian, 0, max(1, $maks));

        return rtrim(trim((string) $judul)."\n\n".implode('', $entri));
    }

    /**
     * Berkas mentah CHANGELOG.md — dipakai tautan "Unduh CHANGELOG.md".
     *
     * Dikirim sebagai lampiran (attachment) supaya tidak menimpa tampilan
     * aplikasi di WebView2; pada mode desktop, tautan ini dipetakan oleh
     * resources/js/app.js ke dialog "Simpan Berkas KARSA".
     */
    public function changelogMentah()
    {
        $isi = $this->bacaChangelog();
        if (trim($isi) === '') {
            abort(404, 'Berkas CHANGELOG.md tidak ditemukan pada instalasi ini.');
        }

        return response($isi, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="CHANGELOG.md"',
        ]);
    }

    /**
     * Baca berkas riwayat versi. String kosong = berkas tidak ada / kosong,
     * yang oleh renderMarkdownSimple ditampilkan sebagai pesan ramah.
     */
    private function bacaChangelog(): string
    {
        $path = (string) config('karsa.changelog_path');

        return $path !== '' && is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * Render markdown sederhana untuk changelog — cukup heading, list, bold, code.
     * Tidak pakai library eksternal agar tetap offline.
     */
    private function renderMarkdownSimple(string $md): string
    {
        if (trim($md) === '') {
            return '<p class="text-sm text-slate-500">Belum ada riwayat versi.</p>';
        }
        $lines = explode("\n", $md);
        $html = '';
        $inList = false;
        $inPre = false;
        foreach ($lines as $line) {
            $trim = rtrim($line);
            // code block ```
            if (str_starts_with(trim($trim), '```')) {
                if ($inPre) {
                    $html .= '</pre>';
                    $inPre = false;
                } else {
                    // close list before pre
                    if ($inList) {
                        $html .= '</ul>';
                        $inList = false;
                    }
                    $html .= '<pre class="bg-slate-900 text-slate-100 rounded-lg p-4 text-xs overflow-auto my-3">';
                    $inPre = true;
                }

                continue;
            }
            if ($inPre) {
                $html .= htmlspecialchars($trim, ENT_QUOTES, 'UTF-8')."\n";

                continue;
            }
            if (preg_match('/^###\s+(.*)$/', $trim, $m)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 mt-5 mb-2">'.$this->inlineMd($m[1]).'</h3>';
            } elseif (preg_match('/^##\s+(.*)$/', $trim, $m)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h2 class="text-base font-extrabold text-slate-800 dark:text-white mt-6 mb-3 pb-2 border-b border-slate-200 dark:border-slate-700">'.$this->inlineMd($m[1]).'</h2>';
            } elseif (preg_match('/^#\s+(.*)$/', $trim, $m)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h1 class="text-lg font-extrabold text-slate-800 dark:text-white mt-2 mb-3">'.$this->inlineMd($m[1]).'</h1>';
            } elseif (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
                if (! $inList) {
                    $html .= '<ul class="list-disc pl-5 space-y-1.5 text-sm text-slate-600 dark:text-slate-300 my-2">';
                    $inList = true;
                }
                $html .= '<li>'.$this->inlineMd($m[1]).'</li>';
            } elseif (trim($trim) === '') {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                // skip empty
            } else {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed my-2">'.$this->inlineMd($trim).'</p>';
            }
        }
        if ($inList) {
            $html .= '</ul>';
        }
        if ($inPre) {
            $html .= '</pre>';
        }

        return $html;
    }

    private function inlineMd(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // bold **text**
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong class="font-bold text-slate-800 dark:text-slate-100">$1</strong>', $text);
        // inline code `code`
        $text = preg_replace('/`([^`]+)`/', '<code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-xs font-mono">$1</code>', $text);
        // link [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">$1</a>', $text);

        return $text;
    }
}
