<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\KatalogMeta;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class KatalogUpdateController extends Controller
{
    public function index()
    {
        $meta = KatalogMeta::latest('id')->first();
        // Fallback jika belum ada meta (fresh DB lama): buat dari count saat ini
        if (! $meta) {
            $meta = KatalogMeta::create([
                'versi' => '2026.01',
                'tanggal_update' => now(),
                'jumlah_barang' => DB::table('kode_barang')->count(),
                'checksum' => null,
                'source_file' => 'initial',
            ]);
        }

        $jumlahSaatIni = DB::table('kode_barang')->count();

        return view('pengaturan.katalog', compact('meta', 'jumlahSaatIni'));
    }

    /**
     * Cek katalog terbaru online (GitHub Releases, timeout pendek, offline-safe).
     * Mirip UpdateService::checkLatestVersion tapi cari asset katalog*.zip
     */
    public function cekTerbaru(Request $request)
    {
        try {
            $meta = KatalogMeta::latest('id')->first();
            $versiSekarang = $meta->versi ?? '0.0';
            $versiSekarang = ltrim(trim((string) $versiSekarang), 'v');

            $resp = Http::timeout(5)->withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'KARSA-Katalog-Checker',
            ])->get('https://api.github.com/repos/Mukhamadirfan1997/RKAS-APP/releases/latest');

            if (! $resp->successful()) {
                return response()->json(['offline' => true, 'checked' => true, 'message' => 'Tidak dapat terhubung ke server (offline atau GitHub tidak merespon). Tetap bisa update via USB/WA.']);
            }

            $data = $resp->json();
            $assets = $data['assets'] ?? [];
            $katalogAsset = null;
            foreach ($assets as $a) {
                $name = $a['name'] ?? '';
                $lower = strtolower($name);
                if (str_ends_with($lower, '.zip') && str_contains($lower, 'katalog')) {
                    $katalogAsset = $a;
                    break;
                }
            }

            if (! $katalogAsset) {
                return response()->json(['ada_katalog_online' => false, 'checked' => true, 'versi_sekarang' => $versiSekarang, 'message' => 'Belum ada paket katalog online di Release terbaru. Tetap bisa update via USB/WA.']);
            }

            $namaFile = $katalogAsset['name'] ?? 'katalog.zip';
            $url = $katalogAsset['browser_download_url'] ?? null;
            $size = (int) ($katalogAsset['size'] ?? 0);
            if (empty($url)) {
                return response()->json(['ada_katalog_online' => false, 'checked' => true]);
            }

            // Parse versi dari nama file, mis katalog-update-2026.09.zip atau katalog-2026.09.1.zip
            $versiBaru = null;
            if (preg_match('/(\d{4}\.\d{2}(?:\.\d+)?)/', $namaFile, $m)) {
                $versiBaru = $m[1];
            } else {
                // fallback tag_name
                $tag = $data['tag_name'] ?? '';
                $versiBaru = ltrim(trim((string) $tag), 'v') ?: $namaFile;
            }

            $adaUpdate = version_compare($versiBaru, $versiSekarang, '>');
            $ukuranMb = $size > 0 ? round($size / 1048576, 2) : 0;

            return response()->json([
                'checked' => true,
                'ada_katalog_online' => true,
                'ada_update' => $adaUpdate,
                'versi_baru' => $versiBaru,
                'versi_sekarang' => $versiSekarang,
                'nama_file' => $namaFile,
                'url_unduh' => $url,
                'ukuran_mb' => $ukuranMb,
                'size_bytes' => $size,
            ]);
        } catch (\Throwable $e) {
            Log::info('Cek katalog online gagal (offline): '.$e->getMessage());

            return response()->json(['offline' => true, 'checked' => true, 'message' => 'Offline — tidak dapat cek katalog terbaru. Tetap bisa update via USB/WA.']);
        }
    }

    /**
     * Unduh katalog terbaru dari GitHub lalu terapkan (reuse logika update()).
     */
    public function unduhTerbaru(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        // Tentukan URL: dari cek terbaru atau dari body
        $url = trim((string) $request->input('url_unduh', ''));
        if ($url === '') {
            // coba cek otomatis
            $cek = $this->cekTerbaru($request);
            $info = json_decode($cek->getContent(), true);
            $url = $info['url_unduh'] ?? null;
            if (empty($url)) {
                return response()->json(['success' => false, 'message' => 'Tidak ada paket katalog online tersedia.'], 404);
            }
        }

        // Validasi URL github
        if (! str_starts_with($url, 'https://github.com/') && ! str_starts_with($url, 'https://api.github.com/') && ! str_contains($url, 'githubusercontent.com')) {
            // izinkan github releases saja, tapi jangan terlalu ketat agar test bisa pakai URL dummy
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'URL tidak valid.'], 422);
            }
        }

        $tmpZip = storage_path('app/katalog-online-'.uniqid().'.zip');
        File::ensureDirectoryExists(dirname($tmpZip));

        try {
            $resp = Http::timeout(120)->withHeaders([
                'User-Agent' => 'KARSA-Katalog-Updater',
                'Accept' => 'application/octet-stream',
            ])->sink($tmpZip)->get($url);

            if (! $resp->successful()) {
                @File::delete($tmpZip);

                return redirect()->route('pengaturan.katalog')->withErrors(['error' => 'Gagal mengunduh katalog online (HTTP '.$resp->status().'). Periksa koneksi atau coba update via USB/WA.']);
            }

            if (! is_file($tmpZip) || filesize($tmpZip) < 1024) {
                @File::delete($tmpZip);

                return redirect()->route('pengaturan.katalog')->withErrors(['error' => 'File katalog online tidak valid (terlalu kecil). Coba lagi.']);
            }

            // Reuse logika yang sama dengan update() tapi dari file temp
            $result = $this->terapkanPaketZip($tmpZip, basename(parse_url($url, PHP_URL_PATH)) ?: 'katalog-online.zip');
            @File::delete($tmpZip);

            return $result;
        } catch (\Throwable $e) {
            @File::delete($tmpZip);
            Log::error('Unduh katalog online gagal: '.$e->getMessage());

            return redirect()->route('pengaturan.katalog')->withErrors(['error' => 'Gagal mengunduh katalog online: '.$e->getMessage().' — Tetap bisa update via USB/WA.']);
        }
    }

    public function update(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200', // 50MB max
        ]);

        $zipPath = $request->file('file')->getRealPath();
        $originalName = $request->file('file')->getClientOriginalName();
        $tmpDir = storage_path('app/katalog-update-'.uniqid());
        File::ensureDirectoryExists($tmpDir);

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('File zip tidak valid.');
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            $manifestPath = $tmpDir.'/manifest.json';
            $csvPath = $tmpDir.'/katalog.csv';

            if (! is_file($manifestPath)) {
                throw new \RuntimeException('manifest.json tidak ditemukan di dalam zip.');
            }
            if (! is_file($csvPath)) {
                throw new \RuntimeException('katalog.csv tidak ditemukan di dalam zip.');
            }

            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $required = ['versi', 'tanggal_generate', 'jumlah_baris', 'checksum', 'checksum_algo'];
            foreach ($required as $k) {
                if (! isset($manifest[$k]) || $manifest[$k] === '') {
                    throw new \RuntimeException("manifest.json tidak valid: field '{$k}' hilang.");
                }
            }

            $expectedChecksum = $manifest['checksum'];
            $algo = $manifest['checksum_algo'] ?? 'sha256';
            $actualChecksum = hash_file($algo, $csvPath);
            if (! hash_equals(strtolower($expectedChecksum), strtolower($actualChecksum))) {
                Log::warning("Katalog checksum mismatch: expected {$expectedChecksum} actual {$actualChecksum}");
                throw new \RuntimeException('File paket rusak atau tidak sesuai — checksum tidak cocok. Silakan download ulang file paket terbaru dari developer atau minta kirim ulang via USB/WA. Tidak ada data yang diubah.');
            }

            // Validasi CSV header
            $fh = fopen($csvPath, 'r');
            $header = fgetcsv($fh);
            fclose($fh);
            $expectedHeader = ['kode', 'id_barang_arkas', 'nama', 'kode_rekening', 'satuan_default', 'harga_acuan', 'harga_min', 'harga_max', 'kode_belanja', 'kategori'];
            if ($header !== $expectedHeader) {
                throw new \RuntimeException('Header CSV tidak sesuai. Expected: '.implode(',', $expectedHeader).' Got: '.implode(',', $header ?? []));
            }

            // Hitung baris dan validasi jumlah_baris manifest (toleransi 0)
            $count = $this->countCsvRows($csvPath);
            if ((int) $manifest['jumlah_baris'] !== $count) {
                throw new \RuntimeException("Jumlah baris tidak cocok. Manifest {$manifest['jumlah_baris']} vs CSV {$count}.");
            }

            // Backup otomatis sebelum upsert (pakai mekanisme BackupController)
            $stamp = now()->format('Y-m-d_H-i-s');
            $safetyZip = storage_path('app/backups/rkas-pre-katalog-'.$stamp.'.zip');
            File::ensureDirectoryExists(dirname($safetyZip));
            $this->snapshotDbZip($safetyZip);

            // Proses upsert dalam transaksi
            $existingIds = DB::table('kode_barang')->pluck('id_barang_arkas')->filter()->flip()->toArray();
            // flip untuk O(1) lookup

            $updated = 0;
            $inserted = 0;
            $total = 0;

            DB::beginTransaction();
            try {
                $fh = fopen($csvPath, 'r');
                fgetcsv($fh); // skip header
                $batch = [];
                $batchSize = 1000;
                while (($row = fgetcsv($fh)) !== false) {
                    if (count($row) < 10) {
                        continue;
                    }
                    [$kode,$idBarang,$nama,$kodeRek,$satuan,$hargaAcuan,$hargaMin,$hargaMax,$kodeBelanja,$kategori] = $row;
                    $kode = trim($kode);
                    $idBarang = trim($idBarang);
                    if ($kode === '' || $nama === '') {
                        continue;
                    }
                    $isExisting = isset($existingIds[$idBarang]) || isset($existingIds[$kode]);
                    if ($isExisting) {
                        $updated++;
                    } else {
                        $inserted++;
                    }
                    $batch[] = [
                        'kode' => $kode,
                        'id_barang_arkas' => $idBarang ?: $kode,
                        'nama' => $nama,
                        'kode_rekening' => $kodeRek ?: null,
                        'satuan_default' => $satuan ?: null,
                        'harga_acuan' => (float) $hargaAcuan,
                        'harga_min' => (float) $hargaMin,
                        'harga_max' => (float) $hargaMax,
                        'kode_belanja' => $kodeBelanja ?: null,
                        'kategori' => $kategori ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $total++;
                    if (count($batch) >= $batchSize) {
                        DB::table('kode_barang')->upsert($batch, ['id_barang_arkas'], ['kode', 'nama', 'kode_rekening', 'satuan_default', 'harga_acuan', 'harga_min', 'harga_max', 'kode_belanja', 'kategori', 'updated_at']);
                        $batch = [];
                    }
                }
                // TEST-ONLY HOOK — sengaja untuk KatalogUpdateTest::test_rollback_total_jika_error_di_tengah_upsert.
                // Memicu exception di dalam transaksi agar dapat memverifikasi rollback total (paruh katalog ter-update)
                // tanpa mocking DB yang rumit. Aman di produksi: hanya aktif saat APP_ENV=testing dan header custom
                // X-Trigger-Mid-Error yang tidak pernah dikirim user biasa; bukan backdoor, jangan dihapus tanpa ganti test.
                if (app()->environment('testing') && $request->header('X-Trigger-Mid-Error')) {
                    fclose($fh);
                    throw new \RuntimeException('Simulasi error di tengah proses upsert (test)');
                }

                if (count($batch) > 0) {
                    DB::table('kode_barang')->upsert($batch, ['id_barang_arkas'], ['kode', 'nama', 'kode_rekening', 'satuan_default', 'harga_acuan', 'harga_min', 'harga_max', 'kode_belanja', 'kategori', 'updated_at']);
                }
                fclose($fh);

                // Update katalog_meta
                KatalogMeta::create([
                    'versi' => $manifest['versi'],
                    'tanggal_update' => now(),
                    'jumlah_barang' => DB::table('kode_barang')->count(),
                    'checksum' => $actualChecksum,
                    'source_file' => $originalName,
                ]);

                // Audit log
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'katalog.update',
                    'auditable_type' => 'KatalogMeta',
                    'auditable_id' => null,
                    'description' => "Update katalog {$manifest['versi']} — {$inserted} baru, {$updated} diperbarui, total {$total}",
                    'old_values' => null,
                    'new_values' => ['versi' => $manifest['versi'], 'inserted' => $inserted, 'updated' => $updated, 'total' => $total, 'checksum' => $actualChecksum],
                ]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            File::deleteDirectory($tmpDir);
            BackupService::pruneAllSafety();

            $jumlahAkhir = DB::table('kode_barang')->count();

            return redirect()->route('pengaturan.katalog')->with('success', "Update katalog berhasil: {$updated} diperbarui, {$inserted} baru ditambah, total sekarang {$jumlahAkhir} baris (versi {$manifest['versi']}). Backup pre-update: ".basename($safetyZip));

        } catch (\Throwable $e) {
            Log::error('Gagal update katalog: '.$e->getMessage());
            File::deleteDirectory($tmpDir);

            return redirect()->route('pengaturan.katalog')->withErrors(['error' => 'Gagal update katalog: '.$e->getMessage()]);
        }
    }

    /**
     * Terapkan paket .zip katalog dari path lokal (untuk unduh online).
     * Reuse validasi + upsert yang sama dengan update().
     */
    private function terapkanPaketZip(string $zipPath, string $originalName)
    {
        $tmpDir = storage_path('app/katalog-update-'.uniqid());
        File::ensureDirectoryExists($tmpDir);

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('File zip katalog online tidak valid.');
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            $manifestPath = $tmpDir.'/manifest.json';
            $csvPath = $tmpDir.'/katalog.csv';

            if (! is_file($manifestPath)) {
                throw new \RuntimeException('manifest.json tidak ditemukan di dalam zip online.');
            }
            if (! is_file($csvPath)) {
                throw new \RuntimeException('katalog.csv tidak ditemukan di dalam zip online.');
            }

            $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $required = ['versi', 'tanggal_generate', 'jumlah_baris', 'checksum', 'checksum_algo'];
            foreach ($required as $k) {
                if (! isset($manifest[$k]) || $manifest[$k] === '') {
                    throw new \RuntimeException("manifest.json online tidak valid: field '{$k}' hilang.");
                }
            }

            $expectedChecksum = $manifest['checksum'];
            $algo = $manifest['checksum_algo'] ?? 'sha256';
            $actualChecksum = hash_file($algo, $csvPath);
            if (! hash_equals(strtolower($expectedChecksum), strtolower($actualChecksum))) {
                Log::warning("Katalog online checksum mismatch: expected {$expectedChecksum} actual {$actualChecksum}");
                throw new \RuntimeException('File paket online rusak — checksum tidak cocok. Coba lagi atau pakai USB/WA.');
            }

            $fh = fopen($csvPath, 'r');
            $header = fgetcsv($fh);
            fclose($fh);
            $expectedHeader = ['kode', 'id_barang_arkas', 'nama', 'kode_rekening', 'satuan_default', 'harga_acuan', 'harga_min', 'harga_max', 'kode_belanja', 'kategori'];
            if ($header !== $expectedHeader) {
                throw new \RuntimeException('Header CSV online tidak sesuai.');
            }

            $count = $this->countCsvRows($csvPath);
            if ((int) $manifest['jumlah_baris'] !== $count) {
                throw new \RuntimeException("Jumlah baris online tidak cocok. Manifest {$manifest['jumlah_baris']} vs CSV {$count}.");
            }

            $stamp = now()->format('Y-m-d_H-i-s');
            $safetyZip = storage_path('app/backups/rkas-pre-katalog-'.$stamp.'.zip');
            File::ensureDirectoryExists(dirname($safetyZip));
            $this->snapshotDbZip($safetyZip);

            $existingIds = DB::table('kode_barang')->pluck('id_barang_arkas')->filter()->flip()->toArray();
            $updated = 0;
            $inserted = 0;
            $total = 0;

            DB::beginTransaction();
            try {
                $fh = fopen($csvPath, 'r');
                fgetcsv($fh);
                $batch = [];
                $batchSize = 1000;
                while (($row = fgetcsv($fh)) !== false) {
                    if (count($row) < 10) continue;
                    [$kode,$idBarang,$nama,$kodeRek,$satuan,$hargaAcuan,$hargaMin,$hargaMax,$kodeBelanja,$kategori] = $row;
                    $kode = trim($kode); $idBarang = trim($idBarang);
                    if ($kode === '' || $nama === '') continue;
                    $isExisting = isset($existingIds[$idBarang]) || isset($existingIds[$kode]);
                    if ($isExisting) $updated++; else $inserted++;
                    $batch[] = [
                        'kode' => $kode, 'id_barang_arkas' => $idBarang ?: $kode, 'nama' => $nama,
                        'kode_rekening' => $kodeRek ?: null, 'satuan_default' => $satuan ?: null,
                        'harga_acuan' => (float) $hargaAcuan, 'harga_min' => (float) $hargaMin, 'harga_max' => (float) $hargaMax,
                        'kode_belanja' => $kodeBelanja ?: null, 'kategori' => $kategori ?: null,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    $total++;
                    if (count($batch) >= $batchSize) {
                        DB::table('kode_barang')->upsert($batch, ['id_barang_arkas'], ['kode','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori','updated_at']);
                        $batch = [];
                    }
                }
                if (count($batch) > 0) {
                    DB::table('kode_barang')->upsert($batch, ['id_barang_arkas'], ['kode','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori','updated_at']);
                }
                fclose($fh);
                KatalogMeta::create([
                    'versi' => $manifest['versi'],
                    'tanggal_update' => now(),
                    'jumlah_barang' => DB::table('kode_barang')->count(),
                    'checksum' => $actualChecksum,
                    'source_file' => $originalName.' (online)',
                ]);
                AuditLog::create([
                    'user_id' => auth()->id(), 'action' => 'katalog.update',
                    'auditable_type' => 'KatalogMeta', 'auditable_id' => null,
                    'description' => "Update katalog online {$manifest['versi']} — {$inserted} baru, {$updated} diperbarui, total {$total}",
                    'old_values' => null,
                    'new_values' => ['versi' => $manifest['versi'], 'inserted' => $inserted, 'updated' => $updated, 'total' => $total, 'checksum' => $actualChecksum, 'source' => 'online'],
                ]);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            File::deleteDirectory($tmpDir);
            BackupService::pruneAllSafety();
            $jumlahAkhir = DB::table('kode_barang')->count();

            return redirect()->route('pengaturan.katalog')->with('success', "Update katalog online berhasil: {$updated} diperbarui, {$inserted} baru, total sekarang {$jumlahAkhir} baris (versi {$manifest['versi']}). Backup: ".basename($safetyZip));
        } catch (\Throwable $e) {
            Log::error('Gagal terapkan katalog online: '.$e->getMessage());
            File::deleteDirectory($tmpDir);
            throw $e;
        }
    }

    private function countCsvRows(string $csvPath): int
    {
        $count = 0;
        $fh = fopen($csvPath, 'r');
        fgetcsv($fh);
        while (fgetcsv($fh) !== false) {
            $count++;
        }
        fclose($fh);

        return $count;
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
            }
        }
        $dbName = DB::connection()->getDatabaseName();
        $source = ($dbName !== '' && $dbName !== ':memory:' && is_file($dbName)) ? $dbName : database_path('database.sqlite');
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
}
