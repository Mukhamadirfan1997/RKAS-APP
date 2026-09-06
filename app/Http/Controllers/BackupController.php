<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

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
                    $jenis = 'Manual'; $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30'; $desc = 'Buat Backup Baru — untuk flashdisk';
                } elseif (str_starts_with($nama, 'rkas-pengesahan-')) {
                    $jenis = 'Pengesahan'; $badge = 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/30'; $desc = 'Arsip resmi saat Disahkan';
                } elseif (str_starts_with($nama, 'rkas-auto-')) {
                    $jenis = 'Otomatis'; $badge = 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/30'; $desc = 'Harian otomatis';
                } else {
                    $jenis = 'Safety'; $badge = 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-600'; $desc = 'Cadangan safety (pre-restore/katalog)';
                }
                return ['nama' => $nama, 'ukuran' => $f->getSize(), 'waktu' => $f->getMTime(), 'jenis' => $jenis, 'badge' => $badge, 'desc' => $desc];
            })
            ->sortByDesc('waktu')
            ->values();

        $jumlahBackup = $allFiles->count();
        $totalUkuran = $allFiles->sum('ukuran');

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

        return view('backup.index', compact('files', 'jumlahBackup', 'totalUkuran', 'rekomendasi'));
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
        $request->validate([
            'file' => 'required|file|mimes:zip',
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
        $stamp = now()->format('Y-m-d_H-i-s');
        $tmpDir = $this->dir.'/restore-tmp-'.$stamp;
        File::ensureDirectoryExists($tmpDir);

        try {
            $extractPath = $tmpDir.'/extract';
            File::ensureDirectoryExists($extractPath);

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Zip tidak valid.');
            }
            $zip->extractTo($extractPath);
            $zip->close();

            // Cari file database.sqlite di dalam zip
            $dbFiles = collect(File::allFiles($extractPath))
                ->filter(fn ($f) => $f->getFilename() === 'database.sqlite');

            if ($dbFiles->isEmpty()) {
                throw new \RuntimeException('Isi zip tidak valid: tidak ada database/database.sqlite.');
            }

            $dbBaru = $dbFiles->first()->getPathname();
            if (! $this->isValidSqlite($dbBaru)) {
                throw new \RuntimeException('File database dalam zip bukan SQLite yang valid.');
            }

            // Simpan cadangan keselamatan DB saat ini sebelum menimpa
            $safetyZip = $this->dir.'/rkas-pre-restore-'.$stamp.'.zip';
            BackupService::snapshotDbZip($safetyZip);

            // Hanya timpa file database aktif bila memang memakai file (bukan :memory:/testing)
            // Mendukung DB di app_data_dir (Tauri produksi, writable tanpa admin) maupun install dir (dev)
            $dbName = DB::connection()->getDatabaseName();
            if ($dbName === ':memory:') {
                if (app()->environment('testing')) {
                    // Testing dengan :memory: tidak punya file aktif untuk ditimpa,
                    // tapi tetap salin ke file agar backup/restore flow dapat diuji tanpa fake success.
                    copy($dbBaru, database_path('database.sqlite'));
                } else {
                    throw new \RuntimeException('Restore gagal: koneksi database aktif bukan file database.sqlite standar ('.$dbName.')');
                }
            } elseif ($dbName !== '' && $dbName !== ':memory:') {
                DB::disconnect();
                // $dbName bisa berupa app_data_dir/database.sqlite (produksi) atau database/database.sqlite (dev)
                copy($dbBaru, $dbName);
            } else {
                throw new \RuntimeException('Restore gagal: koneksi database aktif bukan file database.sqlite standar ('.$dbName.')');
            }

            $this->catatAudit('backup.restore', $safetyZip);
            BackupService::pruneAllSafety();

            return redirect()->route('backup.index')->with('success', 'Restore berhasil. Data telah dikembalikan dari backup.');
        } catch (\Exception $e) {
            Log::error('Gagal restore: '.$e->getMessage());

            return redirect()->route('backup.index')->withErrors(['error' => 'Gagal restore: '.$e->getMessage()]);
        } finally {
            File::deleteDirectory($tmpDir);
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
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 16);
        fclose($handle);

        return $header === "SQLite format 3\x00";
    }

    // Delegasi ke BackupService untuk reuse di Pengesahan (jangan duplikat logika VACUUM)
    private function buatSnapshot(string $tmp): void
    {
        BackupService::buatSnapshot($tmp);
    }

    private function snapshotDbZip(string $zipPath): void
    {
        BackupService::snapshotDbZip($zipPath);
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
