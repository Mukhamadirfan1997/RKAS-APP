<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
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
    public function index()
    {
        File::ensureDirectoryExists($this->dir);

        $files = collect(File::files($this->dir))
            ->filter(fn ($f) => $f->getExtension() === 'zip')
            ->map(fn ($f) => [
                'nama' => $f->getFilename(),
                'ukuran' => $f->getSize(),
                'waktu' => $f->getMTime(),
            ])
            ->sortByDesc('waktu')
            ->values();

        $jumlahBackup = $files->count();
        $totalUkuran = $files->sum('ukuran');

        return view('backup.index', compact('files', 'jumlahBackup', 'totalUkuran'));
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
            $tmp = $this->dir.'/rkas-tmp-'.$stamp.'.sqlite';
            $zipPath = $this->dir.'/rkas-backup-'.$stamp.'.zip';

            // Snapshot database yang konsisten (anti WAL)
            $this->buatSnapshot($tmp);

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Tidak dapat membuat file zip.');
            }
            $zip->addFile($tmp, 'database/database.sqlite');
            $zip->close();
            File::delete($tmp);

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
            $this->snapshotDbZip($safetyZip);

            // Hanya timpa file database aktif bila memang memakai file (bukan :memory:/testing)
            if (DB::connection()->getDatabaseName() === database_path('database.sqlite')) {
                DB::disconnect();
                copy($dbBaru, database_path('database.sqlite'));
            }

            $this->catatAudit('backup.restore', $safetyZip);

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

    /**
     * Buat snapshot file sqlite yang konsisten.
     * Prioritas: VACUUM INTO; bila sedang dalam transaksi (mis. saat pengujian),
     * fallback: checkpoint lalu salin file database langsung.
     */
    private function buatSnapshot(string $tmp): void
    {
        $inTransaction = DB::transactionLevel() > 0;
        if (! $inTransaction) {
            try {
                DB::connection()->getPdo()->exec("VACUUM INTO '".str_replace("'", "''", $tmp)."'");
                if (is_file($tmp) && filesize($tmp) > 0) {
                    return;
                }
            } catch (\Throwable $e) {
                // lanjut ke strategi copy
            }
        }

        // Fallback saat sedang dalam transaksi (mis. lingkungan pengujian):
        // salin file database langsung tanpa SQL (VACUUM & checkpoint dilarang di tengah transaksi).
        $source = database_path('database.sqlite');
        if (! is_file($source)) {
            throw new \RuntimeException('Snapshot gagal: file database tidak ditemukan.');
        }
        $io = @fopen($source, 'rb');
        $out = @fopen($tmp, 'wb');
        if ($io === false || $out === false) {
            if ($io !== false) {
                fclose($io);
            }
            throw new \RuntimeException('Snapshot gagal: tidak dapat membaca/menulis file.');
        }
        stream_copy_to_stream($io, $out);
        fclose($io);
        fclose($out);
        if (filesize($tmp) === 0) {
            throw new \RuntimeException('Snapshot gagal: file cadangan kosong.');
        }
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

    private function snapshotDbZip(string $zipPath): void
    {
        $tmp = $zipPath.'.sqlite';
        $this->buatSnapshot($tmp);
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($tmp, 'database/database.sqlite');
            $zip->close();
        }
        File::delete($tmp);
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
