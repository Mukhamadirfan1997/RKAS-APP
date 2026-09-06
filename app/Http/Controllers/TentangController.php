<?php

namespace App\Http\Controllers;

use App\Models\PengaturanSekolah;
use Illuminate\Http\Request;

class TentangController extends Controller
{
    public function index()
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah([
            'nama_sekolah' => null,
            'kecamatan' => null,
            'kabupaten_kota' => null,
        ]);

        // Versi dinamis dari config/karsa.php (yang membaca src-tauri/tauri.conf.json)
        // Fallback baca langsung file bila config belum ter-load (mis. config cache lama)
        $version = config('karsa.version', '0.0.0');
        if ($version === '0.0.0' || empty($version)) {
            try {
                $conf = base_path('src-tauri/tauri.conf.json');
                if (is_file($conf)) {
                    $json = json_decode((string) file_get_contents($conf), true);
                    if (! empty($json['version'])) {
                        $version = $json['version'];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // Changelog — baca CHANGELOG.md root, fallback kosong
        $changelogPath = base_path('CHANGELOG.md');
        $changelogRaw = is_file($changelogPath) ? (string) file_get_contents($changelogPath) : '';
        $changelogHtml = $this->renderMarkdownSimple($changelogRaw);

        return view('tentang.index', compact('sekolah', 'version', 'changelogRaw', 'changelogHtml'));
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
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<pre class="bg-slate-900 text-slate-100 rounded-lg p-4 text-xs overflow-auto my-3">';
                    $inPre = true;
                }
                continue;
            }
            if ($inPre) {
                $html .= htmlspecialchars($trim, ENT_QUOTES, 'UTF-8') . "\n";
                continue;
            }
            if (preg_match('/^###\s+(.*)$/', $trim, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 mt-5 mb-2">' . $this->inlineMd($m[1]) . '</h3>';
            } elseif (preg_match('/^##\s+(.*)$/', $trim, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h2 class="text-base font-extrabold text-slate-800 dark:text-white mt-6 mb-3 pb-2 border-b border-slate-200 dark:border-slate-700">' . $this->inlineMd($m[1]) . '</h2>';
            } elseif (preg_match('/^#\s+(.*)$/', $trim, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h1 class="text-lg font-extrabold text-slate-800 dark:text-white mt-2 mb-3">' . $this->inlineMd($m[1]) . '</h1>';
            } elseif (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
                if (! $inList) { $html .= '<ul class="list-disc pl-5 space-y-1.5 text-sm text-slate-600 dark:text-slate-300 my-2">'; $inList = true; }
                $html .= '<li>' . $this->inlineMd($m[1]) . '</li>';
            } elseif (trim($trim) === '') {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                // skip empty
            } else {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed my-2">' . $this->inlineMd($trim) . '</p>';
            }
        }
        if ($inList) $html .= '</ul>';
        if ($inPre) $html .= '</pre>';
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
