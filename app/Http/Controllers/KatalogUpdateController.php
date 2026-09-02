<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\KatalogMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            $required = ['versi','tanggal_generate','jumlah_baris','checksum','checksum_algo'];
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
            $expectedHeader = ['kode','id_barang_arkas','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori'];
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
                        DB::table('kode_barang')->upsert($batch, ['id_barang_arkas'], ['kode','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori','updated_at']);
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
                    DB::table('kode_barang')->upsert($batch, ['id_barang_arkas'], ['kode','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori','updated_at']);
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

            $jumlahAkhir = DB::table('kode_barang')->count();

            return redirect()->route('pengaturan.katalog')->with('success', "Update katalog berhasil: {$updated} diperbarui, {$inserted} baru ditambah, total sekarang {$jumlahAkhir} baris (versi {$manifest['versi']}). Backup pre-update: ".basename($safetyZip));

        } catch (\Throwable $e) {
            Log::error('Gagal update katalog: '.$e->getMessage());
            File::deleteDirectory($tmpDir);
            return redirect()->route('pengaturan.katalog')->withErrors(['error' => 'Gagal update katalog: '.$e->getMessage()]);
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
