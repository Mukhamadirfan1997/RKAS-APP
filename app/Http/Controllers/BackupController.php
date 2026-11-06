<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    protected string $dir = '';

    public function __construct()
    {
        $this->dir = storage_path('app/backups');
    }

    /**
     * Halaman Backup & Restore.
     */
    public function index(Request $request)
    {
        File::ensureDirectoryExists($this->dir);

        $allFiles = collect(File::files($this->dir))
            ->filter(fn ($f) => $f->getExtension() === 'zip')
            ->map(function ($f) {
                $nama = $f->getFilename();
                if (str_starts_with($nama, 'rkas-backup-')) {
                    $jenis = 'Manual';
                    $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30';
                    $desc = 'Buat Backup Baru — untuk flashdisk';
                } elseif (str_starts_with($nama, 'rkas-pengesahan-')) {
                    $jenis = 'Pengesahan';
                    $badge = 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/30';
                    $desc = 'Arsip resmi saat Disahkan';
                } elseif (str_starts_with($nama, 'rkas-auto-')) {
                    $jenis = 'Otomatis';
                    $badge = 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/30';
                    $desc = 'Harian otomatis';
                } else {
                    $jenis = 'Safety';
                    $badge = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-600';
                    $desc = 'Cadangan safety (pre-restore/katalog)';
                }

                return ['nama' => $nama, 'ukuran' => $f->getSize(), 'waktu' => $f->getMTime(), 'jenis' => $jenis, 'badge' => $badge, 'desc' => $desc];
            })
            ->sortByDesc('waktu')
            ->values();

        $jumlahBackup = $allFiles->count();
        $totalUkuran = $allFiles->sum('ukuran');
        $maksUnggahMb = BackupService::batasUnggahMb();

        // Rekomendasi: file terbaru yang harus diambil user (prioritas: Manual > Pengesahan > Otomatis)
        $rekomendasi = $allFiles->firstWhere(fn ($x) => $x['jenis'] === 'Manual')
            ?? $allFiles->firstWhere(fn ($x) => $x['jenis'] === 'Pengesahan')
            ?? $allFiles->firstWhere(fn ($x) => $x['jenis'] === 'Otomatis')
            ?? $allFiles->first();

        // Pagination 20/halaman agar daftar tidak membebani render
        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $allFiles->forPage($currentPage, $perPage)->values();
        $files = new LengthAwarePaginator($currentItems, $allFiles->count(), $perPage, $currentPage, ['path' => $request->url(), 'query' => $request->query()]);

        return view('backup.index', compact('files', 'jumlahBackup', 'totalUkuran', 'rekomendasi', 'maksUnggahMb'));
    }

    /**
     * Buat backup database SQLite menjadi .zip (via VACUUM INTO agar konsisten).
     */
    public function create()
    {
        try {
            File::ensureDirectoryExists($this->dir);
            DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 30000');

            $stamp = now()->format('Y-m-d_H-i-s');
            $zipPath = $this->dir.'/rkas-backup-'.$stamp.'.zip';

            BackupService::snapshotDbZip($zipPath);
            BackupService::pruneAllSafety();

            $this->catatAudit('backup.create', $zipPath);

            return redirect()->route('backup.index')->with('success', 'Backup berhasil dibuat: '.basename($zipPath));
        } catch (\Exception $e) {
            Log::error('Gagal membuat backup: '.$e->getMessage());

            return redirect()->route('backup.index')->withErrors(['error' => 'Gagal membuat backup: '.$e->getMessage()]);
        }
    }

    /**
     * Unduh file backup.
     */
    public function download($filename)
    {
        $path = $this->pathZonaAman($filename);
        if (! $path) {
            abort(404);
        }

        return response()->download($path);
    }

    /**
     * Restore database dari file backup .zip yang diunggah.
     */
    public function restore(Request $request)
    {
        // PHP membuang body bila melebihi post_max_size → beri pesan jelas, bukan "pilih berkas".
        $batasUnggah = BackupService::batasUnggahByte();
        $maksMb = BackupService::batasUnggahMb();
        $konten = (int) $request->server('CONTENT_LENGTH', 0);
        if ($konten > 0 && $batasUnggah > 0 && $konten > $batasUnggah) {
            return redirect()->route('backup.index')->withErrors([
                'error' => "Ukuran berkas backup melebihi batas server {$maksMb} MB.",
            ]);
        }

        $request->validate([
            'file' => 'required|file|mimes:zip|max:'.($maksMb * 1024),
        ], [
            'file.max' => "Ukuran berkas backup melebihi batas server {$maksMb} MB.",
        ]);

        return $this->restoreFromZip($request->file('file')->getRealPath());
    }

    /**
     * Restore database dari file backup yang sudah ada di daftar.
     */
    public function restoreExisting($filename)
    {
        $path = $this->pathZonaAman($filename);
        if (! $path) {
            abort(404);
        }

        return $this->restoreFromZip($path);
    }

    private function restoreFromZip(string $zipPath)
    {
        try {
            $safetyZip = BackupService::restoreFromZip($zipPath);

            $this->catatAudit('backup.restore', $safetyZip);

            return redirect()->route('backup.index')->with('success', 'Restore berhasil. Data telah dikembalikan dari backup.');
        } catch (\Exception $e) {
            Log::error('Gagal restore: '.$e->getMessage());

            return redirect()->route('backup.index')->withErrors(['error' => 'Gagal restore: '.$e->getMessage()]);
        }
    }

    /**
     * Hapus file backup.
     */
    public function destroy($filename)
    {
        $path = $this->pathZonaAman($filename);
        if (! $path) {
            abort(404);
        }

        File::delete($path);

        return redirect()->route('backup.index')->with('success', "Backup '{$filename}' dihapus.");
    }

    /**
     * Pastikan filename hanya bertipe zip yang ada di folder backup (anti path traversal).
     */
    private function pathZonaAman(string $filename): ?string
    {
        if (basename($filename) !== $filename || ! str_ends_with($filename, '.zip')) {
            return null;
        }

        $path = $this->dir.'/'.$filename;
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function isValidSqlite(string $path): bool
    {
        return BackupService::isValidSqlite($path);
    }

    private function catatAudit(string $action, ?string $file): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => 'Backup',
            'auditable_id' => null,
            'description' => $action.' — '.basename((string) $file),
            'old_values' => null,
            'new_values' => ['file' => $file ? basename($file) : null],
        ]);
    }
}
